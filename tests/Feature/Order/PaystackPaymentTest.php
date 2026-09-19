<?php

namespace Tests\Feature\Order;

use App\Models\Order\Order;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaystackPaymentTest extends TestCase
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

    private function checkoutPayload(array $overrides = []): array
    {
        return array_merge([
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'paystack-guest@example.com',
            'name' => 'Paystack Guest',
            'phone' => '08033334444',
            'address' => '1 Test Street',
            'nearest_bustop' => 'Test Bustop',
            'notes' => '',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => 2000,
            'total' => 2000,
        ], $overrides);
    }

    public function testInitializeCreatesAPendingOrderAndReturnsAuthorizationUrl()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/abc123',
                    'access_code' => 'abc123',
                    'reference' => 'will-be-overwritten-by-request-body-in-real-call',
                ],
            ], 200),
        ]);

        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 2, 'rate' => 1000],
            ],
        ]);

        $response = $this->postJson('/api/order/paystack/initialize', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['message' => 'success', 'authorization_url' => 'https://checkout.paystack.com/abc123']);

        $this->assertDatabaseHas('orders', [
            'order_uniq_id' => $payload['order_uniq_id'],
            'payment_method' => 'Paystack',
            'payment_status' => 'pending',
        ]);
        // stock is reserved immediately, same as the manual bank-transfer path
        $stock->refresh();
        $this->assertSame(2, $stock->reserved);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.paystack.co/transaction/initialize'
                && $request['amount'] === 200000 // 2000 naira in kobo
                && $request['email'] === 'paystack-guest@example.com';
        });
    }

    public function testInitializeRejectsWhenOnlinePaymentIsDisabledInSettings()
    {
        $this->seedBaselineSettings();
        \App\Models\Setting\Setting::where('key', 'online_payment_enabled')->update(['value' => 'false']);
        [$item, $stock] = $this->makeItemWithStock();

        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);

        $response = $this->postJson('/api/order/paystack/initialize', $payload);

        $response->assertStatus(500);
        $this->assertDatabaseMissing('orders', ['order_uniq_id' => $payload['order_uniq_id']]);
        // No order was created, so nothing should have reserved stock either.
        $stock->refresh();
        $this->assertSame(0, $stock->reserved);
    }

    public function testInitializeFailsCleanlyWhenPaystackRejectsTheRequest()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        Http::fake([
            'api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401),
        ]);

        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);

        $response = $this->postJson('/api/order/paystack/initialize', $payload);
        $response->assertStatus(500);
    }

    public function testCallbackMarksOrderPaidWhenPaystackVerifiesSuccess()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
            ], 200),
        ]);

        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 2, 'rate' => 1000],
            ],
        ]);
        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);

        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->first();
        $this->assertNotNull($order->payment_reference);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => (int) round($order->total * 100)],
            ], 200),
        ]);

        $response = $this->get('/api/order/paystack/callback?reference=' . $order->payment_reference);

        $response->assertRedirect();
        $this->assertStringContainsString('payment=success', $response->headers->get('Location'));
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
    }

    public function testCallbackDoesNotMarkOrderPaidWhenAmountDoesNotMatch()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
            ], 200),
        ]);

        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 2, 'rate' => 1000],
            ],
        ]);
        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->first();

        // Paystack says "success" but for a much smaller amount than the
        // order actually totals — must not be trusted.
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => 100],
            ], 200),
        ]);

        $response = $this->get('/api/order/paystack/callback?reference=' . $order->payment_reference);

        $response->assertRedirect();
        $this->assertStringContainsString('payment=failed', $response->headers->get('Location'));
        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
    }

    public function testWebhookRejectsAnInvalidSignature()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
            ], 200),
        ]);
        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);
        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->first();

        $response = $this->postJson('/api/order/paystack/webhook', [
            'event' => 'charge.success',
            'data' => ['reference' => $order->payment_reference, 'amount' => (int) round($order->total * 100), 'status' => 'success'],
        ], ['x-paystack-signature' => 'not-the-real-signature']);

        $response->assertStatus(401);
        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
    }

    public function testWebhookMarksOrderPaidWithAValidSignature()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        config(['services.paystack.secret_key' => 'sk_test_dummy']);

        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
            ], 200),
        ]);
        $payload = $this->checkoutPayload([
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => 1, 'rate' => 1000],
            ],
        ]);
        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);
        $order = Order::where('order_uniq_id', $payload['order_uniq_id'])->first();

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => (int) round($order->total * 100)],
            ], 200),
        ]);

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => $order->payment_reference, 'amount' => (int) round($order->total * 100), 'status' => 'success'],
        ]);
        $signature = hash_hmac('sha512', $body, 'sk_test_dummy');

        $response = $this->call('POST', '/api/order/paystack/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_x-paystack-signature' => $signature,
        ], $body);

        $response->assertStatus(200);
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
    }
}
