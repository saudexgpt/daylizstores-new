<?php

namespace Tests\Feature\Order;

use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OrderPlacementTest extends TestCase
{
    use RefreshDatabase;

    private function makeItemWithStock(int $quantity = 50, float $price = 1000): array
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

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

    public function testGuestCanPlaceAnOrder()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();

        $payload = [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'guest@example.com',
            'name' => 'Guest Customer',
            'phone' => '08033334444',
            'address' => '1 Test Street',
            'nearest_bustop' => 'Test Bustop',
            'notes' => '',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 2000,
            'total' => 2500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                [
                    'id' => $item->id,
                    'stock_id' => $stock->id,
                    'name' => $item->name,
                    'quantity' => 2,
                    'rate' => 1000,
                ],
            ],
        ];

        $response = $this->postJson('/api/order/store', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'success']);

        $this->assertDatabaseHas('users', ['email' => 'guest@example.com']);
        $this->assertDatabaseHas('orders', ['order_uniq_id' => $payload['order_uniq_id']]);
        $this->assertDatabaseHas('order_items', ['stock_id' => $stock->id, 'quantity' => 2]);

        // total is recomputed server-side from order items (2 * 1000), not
        // trusted from the client-supplied `total` of 2500.
        $this->assertDatabaseHas('orders', [
            'order_uniq_id' => $payload['order_uniq_id'],
            'total' => 2000,
        ]);
    }

    public function testPlacingAnOrderReservesStock()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock(50);

        $payload = [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'guest2@example.com',
            'name' => 'Guest Two',
            'phone' => '08055556666',
            'address' => '2 Test Street',
            'nearest_bustop' => 'Test Bustop',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 3000,
            'total' => 3500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                [
                    'id' => $item->id,
                    'stock_id' => $stock->id,
                    'name' => $item->name,
                    'quantity' => 5,
                    'rate' => 1000,
                ],
            ],
        ];

        $this->postJson('/api/order/store', $payload)->assertStatus(200);

        $stock->refresh();
        $this->assertSame(5, $stock->reserved);
    }

    public function testGuestCannotPlaceOrderWithInvalidEmail()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();

        $response = $this->postJson('/api/order/store', [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'not-an-email',
            'name' => 'Guest',
            'phone' => '08011112222',
            'address' => '1 Test Street',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 1000,
            'total' => 1500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
        $this->assertStringContainsString('Please provide valid order details', $response->json('message'));
    }

    public function testOrderPlacementRejectsNegativeAmount()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();

        $response = $this->postJson('/api/order/store', [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'guest3@example.com',
            'name' => 'Guest Three',
            'phone' => '08099998888',
            'address' => '1 Test Street',
            'location' => ['Lagos'],
            'delivery_cost' => -500,
            'amount' => -1000,
            'total' => -1500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount', 'total', 'delivery_cost']);
    }

    public function testOrderPlacementIsBlockedWhenOrderingIsDisabled()
    {
        Storage::fake('public');
        $setting = new \App\Models\Setting\Setting();
        $setting->key = 'can_make_order';
        $setting->value = 'false';
        $setting->save();

        [$item, $stock] = $this->makeItemWithStock();

        $response = $this->postJson('/api/order/store', [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'guest4@example.com',
            'name' => 'Guest Four',
            'phone' => '08012312312',
            'address' => '1 Test Street',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 1000,
            'total' => 1500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);

        $response->assertStatus(500);
        $response->assertJsonFragment(['message' => 'Order placement is disabled for now']);
    }

    public function testDuplicateOrderUniqIdIsNotDoubleProcessed()
    {
        $this->seedBaselineSettings();
        Storage::fake('public');
        [$item, $stock] = $this->makeItemWithStock();
        $uniqId = (string) Str::uuid();

        $payload = [
            'order_uniq_id' => $uniqId,
            'email' => 'guest5@example.com',
            'name' => 'Guest Five',
            'phone' => '08000001111',
            'address' => '1 Test Street',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 1000,
            'total' => 1500,
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ];

        $this->postJson('/api/order/store', $payload)->assertStatus(200);
        $second = $this->postJson('/api/order/store', $payload);

        $second->assertStatus(200);
        $second->assertJsonFragment(['message' => 'order_made_already']);
        $this->assertSame(1, \App\Models\Order\Order::where('order_uniq_id', $uniqId)->count());
    }
}
