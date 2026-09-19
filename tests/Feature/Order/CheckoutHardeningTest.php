<?php

namespace Tests\Feature\Order;

use App\Laravue\Models\User;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Setting\Setting;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the checkout-flow audit: every hole and bug that was
 * found and fixed there has a test here that would fail if it came back.
 */
class CheckoutHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBaselineSettings();
        Storage::fake('public');
    }

    // ------------------------------------------------------------ helpers

    private function makeItemWithStock(int $quantity = 50, float $price = 1000, array $itemAttrs = []): array
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(array_merge(['category_id' => $category->id], $itemAttrs));

        $itemPrice = new ItemPrice();
        $itemPrice->item_id = $item->id;
        $itemPrice->amount = $price;
        $itemPrice->save();

        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = 'M';
        $stock->quantity_stocked = $quantity;
        $stock->save();

        return [$item, $stock];
    }

    private function line(ItemStock $stock, $quantity, array $extra = []): array
    {
        return array_merge([
            'id' => $stock->item_id,
            'stock_id' => $stock->id,
            'name' => 'client supplied name',
            'quantity' => $quantity,
            'rate' => 1,
        ], $extra);
    }

    private function payload(array $cart, array $overrides = []): array
    {
        return array_merge([
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'phone' => '08033334444',
            'address' => '1 Test Street',
            'nearest_bustop' => 'Test Bustop',
            'notes' => '',
            'location' => ['Lagos'],
            'delivery_cost' => 0,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => $cart,
        ], $overrides);
    }

    private function setSetting(string $key, string $value): void
    {
        $setting = Setting::where('key', $key)->first() ?: new Setting();
        $setting->key = $key;
        $setting->value = $value;
        $setting->save();
    }

    // ------------------------------------------------------ payment evidence

    public function testReceiptImageIsRequired()
    {
        [, $stock] = $this->makeItemWithStock();
        $payload = $this->payload([$this->line($stock, 1)]);
        unset($payload['receipt_image']);

        $this->postJson('/api/order/store', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['receipt_image']);
        $this->assertSame(0, Order::count());
    }

    public function testExecutableFileIsRejectedAsReceipt()
    {
        [, $stock] = $this->makeItemWithStock();

        // Wrong extension *and* wrong content.
        $this->postJson('/api/order/store', $this->payload(
            [$this->line($stock, 1)],
            ['receipt_image' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')]
        ))->assertStatus(422)->assertJsonValidationErrors(['receipt_image']);

        // Right-looking name and claimed MIME type, but the bytes are a PHP
        // script. (A real file, not UploadedFile::fake() — the fake derives
        // its MIME type from the filename, which would hide exactly this.)
        $tmp = tempnam(sys_get_temp_dir(), 'evil');
        file_put_contents($tmp, '<?php system($_GET["c"]); ?>');
        $disguised = new UploadedFile($tmp, 'evil.png', 'image/png', null, true);
        $this->postJson('/api/order/store', $this->payload(
            [$this->line($stock, 1)],
            ['receipt_image' => $disguised]
        ))->assertStatus(422)->assertJsonValidationErrors(['receipt_image']);
        @unlink($tmp);

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Order::count());
    }

    public function testValidReceiptIsStoredUnderAnUnguessableNameWithItsRealExtension()
    {
        [, $stock] = $this->makeItemWithStock();

        $response = $this->postJson('/api/order/store', $this->payload([$this->line($stock, 1)]));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'success']);
        $path = $response->json('order_details.receipt_image');
        $this->assertMatchesRegularExpression('#^/storage/receipt/[A-Za-z0-9]{40}\.(jpg|jpeg)$#', $path);
        Storage::disk('public')->assertExists(ltrim($path, '/'));
        // the folder is also made inert for Apache
        Storage::disk('public')->assertExists('storage/receipt/.htaccess');
    }

    // ------------------------------------------------------------ quantities

    public function testInvalidQuantitiesAreRejected()
    {
        [, $stock] = $this->makeItemWithStock();

        foreach ([-5, 0, 1.5, 'abc', 100000] as $bad) {
            $this->postJson('/api/order/store', $this->payload([$this->line($stock, $bad)]))
                ->assertStatus(422)
                ->assertJsonValidationErrors(['cart_items.0.quantity']);
        }
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $stock->fresh()->reserved);
    }

    public function testNegativeQuantityCannotOffsetAnotherLineOrUnreserveStock()
    {
        [, $big] = $this->makeItemWithStock(5, 100000);
        [, $cheap] = $this->makeItemWithStock(500, 1000);

        $this->postJson('/api/order/store', $this->payload([
            $this->line($big, 1),
            $this->line($cheap, -99),
        ]))->assertStatus(422);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $cheap->fresh()->reserved);
    }

    public function testEmptyOrMissingCartIsRejected()
    {
        $this->postJson('/api/order/store', $this->payload([]))
            ->assertStatus(422)->assertJsonValidationErrors(['cart_items']);

        $payload = $this->payload([]);
        unset($payload['cart_items']);
        $this->postJson('/api/order/store', $payload)
            ->assertStatus(422)->assertJsonValidationErrors(['cart_items']);

        $this->assertSame(0, Order::count());
    }

    public function testDuplicateStockLinesCannotSplitAnOrderPastTheStockCheck()
    {
        [, $stock] = $this->makeItemWithStock(3);

        $this->postJson('/api/order/store', $this->payload([
            $this->line($stock, 3),
            $this->line($stock, 3),
        ]))->assertStatus(422)->assertJsonValidationErrors(['cart_items.0.stock_id']);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, $stock->fresh()->reserved);
    }

    // ----------------------------------------- server-derived price and data

    public function testPriceAndItemComeFromTheStockRowNotFromTheClientsItemId()
    {
        [$cheapItem] = $this->makeItemWithStock(10, 100);
        [$expensiveItem, $expensiveStock] = $this->makeItemWithStock(10, 100000);

        // Cheap item's id paired with the expensive item's stock row.
        $response = $this->postJson('/api/order/store', $this->payload([
            $this->line($expensiveStock, 1, ['id' => $cheapItem->id]),
        ]));

        $response->assertStatus(200);
        $orderItem = OrderItem::first();
        $this->assertSame($expensiveItem->id, (int) $orderItem->item_id);
        $this->assertEquals(100000, $orderItem->price);
        $this->assertEquals(100000, Order::first()->total);
    }

    public function testClientSuppliedAmountTotalAndNameAreIgnored()
    {
        [$item, $stock] = $this->makeItemWithStock(10, 1000);

        $this->postJson('/api/order/store', $this->payload(
            [$this->line($stock, 2, ['name' => 'FREE PRODUCT', 'rate' => 1])],
            ['amount' => 1, 'total' => 1]
        ))->assertStatus(200);

        $order = Order::first();
        $this->assertEquals(2000, $order->amount);
        $this->assertEquals(2000, $order->total);
        $this->assertSame($item->name . ' - Black - M', OrderItem::first()->product_name);
    }

    // ---------------------------------------------------------- availability

    public function testDisabledProductCannotBeOrdered()
    {
        [, $stock] = $this->makeItemWithStock(10, 1000, ['enabled' => 0]);

        $response = $this->postJson('/api/order/store', $this->payload([$this->line($stock, 1)]));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'check_cart']);
        $this->assertSame(0, $response->json('details.0.balance'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $stock->fresh()->reserved);
    }

    public function testUnknownStockRowIsReportedUnavailableInsteadOfCrashing()
    {
        $response = $this->postJson('/api/order/store', $this->payload([
            ['id' => 1, 'stock_id' => 999999, 'name' => 'gone', 'quantity' => 1],
        ]));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'check_cart']);
        $this->assertSame(0, $response->json('details.0.balance'));
    }

    public function testOrderCannotExceedAvailableStock()
    {
        [, $stock] = $this->makeItemWithStock(3);

        $response = $this->postJson('/api/order/store', $this->payload([$this->line($stock, 5)]));

        $response->assertStatus(200)->assertJsonFragment(['message' => 'check_cart']);
        $this->assertSame(3, $response->json('details.0.balance'));
        $this->assertSame(0, Order::count());
        $this->assertSame(0, $stock->fresh()->reserved);
    }

    public function testValidateCartFlagsUnavailableLinesWithoutErroring()
    {
        [, $good] = $this->makeItemWithStock(10);
        [, $disabled] = $this->makeItemWithStock(10, 1000, ['enabled' => 0]);

        $response = $this->postJson('/api/order/validate-cart', ['cart_items' => [
            $this->line($good, 2),
            $this->line($disabled, 1),
            ['stock_id' => 999999, 'quantity' => 1, 'name' => 'stale'],
        ]]);

        $response->assertStatus(200)->assertJsonFragment(['limited_stock' => true]);
        $flagged = collect($response->json('details'))->pluck('updated_item.stock_id')->all();
        $this->assertEqualsCanonicalizing([$disabled->id, 999999], $flagged);
    }

    public function testValidateCartRejectsAnAbsurdlyLargeCart()
    {
        [, $stock] = $this->makeItemWithStock();
        $cart = [];
        for ($i = 1; $i <= 101; $i++) {
            $cart[] = ['stock_id' => $i, 'quantity' => 1];
        }
        $this->postJson('/api/order/validate-cart', ['cart_items' => $cart])->assertStatus(422);
    }

    // ------------------------------------------------- idempotency/throttling

    public function testASimultaneousDuplicateSubmissionIsTurnedAway()
    {
        [, $stock] = $this->makeItemWithStock();
        $payload = $this->payload([$this->line($stock, 1)]);

        // Another request for the same order id is "in flight".
        $held = Cache::lock('order-submit:' . $payload['order_uniq_id'], 30);
        $this->assertTrue($held->get());

        $this->postJson('/api/order/store', $payload)->assertStatus(409);
        $this->assertSame(0, Order::count());
        $held->release();
    }

    public function testOrderPlacementIsRateLimited()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/order/store', [])->assertStatus(422);
        }
        $this->postJson('/api/order/store', [])->assertStatus(429);
    }

    // ---------------------------------------------------------- customer data

    public function testGuestCheckoutCannotOverwriteAnExistingAccountsAddress()
    {
        [, $stock] = $this->makeItemWithStock();
        $customer = User::factory()->customer()->create([
            'email' => 'victim@example.com',
            'address' => 'Original Address',
        ]);

        $this->postJson('/api/order/store', $this->payload(
            [$this->line($stock, 1)],
            ['email' => 'victim@example.com', 'address' => 'Attacker Street']
        ))->assertStatus(200);

        $this->assertSame('Original Address', $customer->fresh()->address);
        $this->assertSame('Attacker Street', Order::first()->address);
    }

    // -------------------------------------------------- removed public routes

    public function testDangerousPublicMaintenanceRoutesAreGone()
    {
        $order = Order::factory()->create();
        // the stored (rounded) value — the factory's in-memory float sum can differ from it in the last digits
        $total = $order->fresh()->total;

        $this->postJson('/api/stabilize-order-total')->assertStatus(404);
        $this->getJson('/api/reverse-bulk-cancelled-order')->assertStatus(404);
        $this->postJson('/api/order/generate-order-number', ['email' => 'spam@example.com', 'name' => 'x'])->assertStatus(404);

        $this->assertEquals($total, $order->fresh()->total);
        $this->assertDatabaseMissing('users', ['email' => 'spam@example.com']);
    }

    // ------------------------------------------------- order access control

    public function testCustomerCanOnlyViewTheirOwnOrders()
    {
        $owner = $this->createCustomerUser();
        $stranger = $this->createCustomerUser();
        $admin = $this->createAdminUser();
        $order = Order::factory()->create();
        $order->user_id = $owner->id;
        $order->save();
        $url = '/api/order/general/show/' . $order->id;

        $this->actingAs($stranger, 'api')->getJson($url)->assertStatus(403);
        $this->actingAs($owner, 'api')->getJson($url)->assertStatus(200);
        $this->actingAs($admin, 'api')->getJson($url)->assertStatus(200);
    }

    public function testReversingACancellationNeedsPermissionAndACancelledOrderAndWorksOnce()
    {
        [, $stock] = $this->makeItemWithStock(50);
        $order = Order::factory()->create();
        $order->order_status = 'Cancelled';
        $order->payment_status = 'cancelled';
        $order->save();
        $orderItem = new OrderItem();
        $orderItem->order_id = $order->id;
        $orderItem->stock_id = $stock->id;
        $orderItem->item_id = $stock->item_id;
        $orderItem->product_name = 'x';
        $orderItem->quantity = 10;
        $orderItem->price = 1000;
        $orderItem->total = 10000;
        $orderItem->save();
        $url = '/api/order/general/reverse-cancellation/' . $order->id;

        $this->actingAs($this->createCustomerUser(), 'api')->putJson($url)->assertStatus(403);
        $this->actingAs($this->createStaffUser(), 'api')->putJson($url)->assertStatus(403);
        $this->assertSame(0, $stock->fresh()->reserved);

        $admin = $this->createAdminUser();
        $this->actingAs($admin, 'api')->putJson($url)->assertStatus(200);
        $this->assertSame(10, $stock->fresh()->reserved);
        $this->assertSame('CARP', $order->fresh()->order_status);

        // A second call must not inflate reserved stock again.
        $this->actingAs($admin, 'api')->putJson($url)->assertStatus(422);
        $this->assertSame(10, $stock->fresh()->reserved);
    }

    // -------------------------------------------------------- order tracking

    public function testOrderTrackingMatchesTheOrderOwnerEvenWhenAPhoneIsShared()
    {
        // An earlier account with the same phone must not shadow the real owner.
        User::factory()->customer()->create(['phone' => '08055550000', 'email' => 'first@example.com']);
        $owner = User::factory()->customer()->create(['phone' => '08055550000', 'email' => 'owner@example.com']);
        $order = Order::factory()->create();
        $order->user_id = $owner->id;
        $order->save();

        $this->postJson('/api/order/search', ['username' => '08055550000', 'order_number' => $order->order_number])
            ->assertStatus(200)->assertJsonFragment(['message' => 'success']);
        $this->postJson('/api/order/search', ['username' => 'owner@example.com', 'order_number' => $order->order_number])
            ->assertJsonFragment(['message' => 'success']);
        $this->postJson('/api/order/search', ['username' => 'first@example.com', 'order_number' => $order->order_number])
            ->assertJsonFragment(['message' => 'failed']);
        $this->postJson('/api/order/search', ['username' => 'owner@example.com'])->assertStatus(422);
    }

    public function testOrderTrackingIsRateLimited()
    {
        for ($i = 0; $i < 15; $i++) {
            $this->postJson('/api/order/search', ['username' => 'a@b.co', 'order_number' => 'DS1'])->assertStatus(200);
        }
        $this->postJson('/api/order/search', ['username' => 'a@b.co', 'order_number' => 'DS1'])->assertStatus(429);
    }

    // ------------------------------------------------------ params endpoint

    private function seedStorefrontSettings(string $canMakeOrder = 'true'): void
    {
        foreach ([
            'company_name' => 'Test Store',
            'company_contact' => 'contact',
            'currency' => '₦',
            'account_details' => '<p>BANK: Test</p>',
            'terms_and_conditions' => 'terms',
            'pickup_warning' => 'Goods unpicked after 7 days are at owner\'s risk.',
            'online_payment_enabled' => 'false',
        ] as $key => $value) {
            $this->setSetting($key, $value);
        }
        $this->setSetting('can_make_order', $canMakeOrder);
    }

    public function testPublicParamsOnlyExposeWhatTheStorefrontNeeds()
    {
        $this->seedStorefrontSettings();
        $this->makeItemWithStock(5, 1000, ['enabled' => 0]);

        $response = $this->getJson('/api/fetch-necessary-params')->assertStatus(200);
        $params = $response->json('params');

        $this->assertSame('<p>BANK: Test</p>', $params['account_details']);
        $this->assertSame("Goods unpicked after 7 days are at owner's risk.", $params['pickup_warning']);
        $this->assertTrue($params['can_make_order']);
        $this->assertFalse($params['online_payment_enabled']);
        $this->assertArrayHasKey('states', $params);
        foreach (['items', 'all_roles', 'warehouses', 'all_locations', 'order_statuses', 'company_contact'] as $private) {
            $this->assertArrayNotHasKey($private, $params, "$private must not be public");
        }
    }

    public function testSignedInUsersStillGetTheAdminParams()
    {
        $this->seedStorefrontSettings();
        $this->makeItemWithStock();

        $params = $this->actingAs($this->createAdminUser(), 'api')
            ->getJson('/api/fetch-necessary-params')->assertStatus(200)->json('params');

        foreach (['items', 'all_roles', 'warehouses', 'order_statuses', 'colors'] as $key) {
            $this->assertArrayHasKey($key, $params);
        }
    }

    public function testOrderingSwitchedOffInSettingsIsReportedAsOff()
    {
        // "false" is stored as text; a bare (bool) cast would call it true.
        $this->seedStorefrontSettings('false');

        $this->assertFalse($this->getJson('/api/fetch-necessary-params')->json('params.can_make_order'));
    }

    // ------------------------------------------------------------- products

    public function testDisabledProductIsHiddenFromGuestsButNotFromAdmins()
    {
        $this->makeItemWithStock(5, 1000, ['slug' => 'hidden-thing', 'enabled' => 0]);

        $this->getJson('/api/item-details?slug=hidden-thing')->assertStatus(200)->assertJson(['item' => null]);
        $this->actingAs($this->createAdminUser(), 'api')->getJson('/api/item-details?slug=hidden-thing')
            ->assertStatus(200)->assertJsonPath('item.slug', 'hidden-thing');
    }

    // ------------------------------------------------- Paystack failure paths

    public function testAFailedPaystackStartCancelsTheOrderAndReleasesItsStock()
    {
        [, $stock] = $this->makeItemWithStock(10);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);
        $payload = $this->payload([$this->line($stock, 3)]);
        unset($payload['receipt_image']);

        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(500);

        $order = Order::first();
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertSame(0, $stock->fresh()->reserved);
    }

    public function testAnUnreachablePaystackCancelsTheOrderAndReleasesItsStock()
    {
        [, $stock] = $this->makeItemWithStock(10);
        Http::fake(['api.paystack.co/*' => function () {
            throw new ConnectionException('timed out');
        }]);
        $payload = $this->payload([$this->line($stock, 3)]);
        unset($payload['receipt_image']);

        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(500);

        $this->assertSame('cancelled', Order::first()->payment_status);
        $this->assertSame(0, $stock->fresh()->reserved);
    }
}
