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

class ReleaseStalePaystackReservationsTest extends TestCase
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

    private function createPendingPaystackOrder($item, $stock, int $quantity = 2): Order
    {
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/xyz'],
            ], 200),
        ]);

        $payload = [
            'order_uniq_id' => (string) Str::uuid(),
            'email' => 'stale-paystack@example.com',
            'name' => 'Stale Paystack Customer',
            'phone' => '08033334444',
            'address' => '1 Test Street',
            'nearest_bustop' => 'Test Bustop',
            'notes' => '',
            'location' => ['Lagos'],
            'delivery_cost' => 500,
            'amount' => $quantity * 1000,
            'total' => $quantity * 1000,
            'cart_items' => [
                ['id' => $item->id, 'stock_id' => $stock->id, 'name' => $item->name, 'quantity' => $quantity, 'rate' => 1000],
            ],
        ];

        $this->postJson('/api/order/paystack/initialize', $payload)->assertStatus(200);

        return Order::where('order_uniq_id', $payload['order_uniq_id'])->first();
    }

    public function testReleasesReservedStockForStalePendingOrderThatNeverConfirmed()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        $order = $this->createPendingPaystackOrder($item, $stock, 2);
        $order->created_at = now()->subMinutes(60);
        $order->save();
        $stock->refresh();
        $this->assertSame(2, $stock->reserved);

        // Paystack has no record of this reference actually completing.
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'abandoned'],
            ], 200),
        ]);

        $this->artisan('order:release-stale-paystack');

        $stock->refresh();
        $order->refresh();
        $this->assertSame(0, $stock->reserved);
        $this->assertSame('cancelled', $order->payment_status);
        $this->assertSame('Cancelled', $order->order_status);
    }

    public function testRecoversOrderWhenPaystackConfirmsSuccessOnRecheck()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        $order = $this->createPendingPaystackOrder($item, $stock, 2);
        $order->created_at = now()->subMinutes(60);
        $order->save();

        // The webhook/callback never reached us, but Paystack's own records
        // show the charge genuinely succeeded — must recover, not release.
        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'amount' => (int) round($order->total * 100)],
            ], 200),
        ]);

        $this->artisan('order:release-stale-paystack');

        $stock->refresh();
        $order->refresh();
        $this->assertSame(2, $stock->reserved);
        $this->assertSame('paid', $order->payment_status);
    }

    public function testLeavesRecentPendingOrdersAlone()
    {
        $this->seedBaselineSettings();
        [$item, $stock] = $this->makeItemWithStock();

        // Created moments ago — still well within the grace period, so this
        // must not be touched even though Paystack can't confirm it yet.
        $order = $this->createPendingPaystackOrder($item, $stock, 2);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => false,
                'message' => 'Transaction not found',
            ], 200),
        ]);

        $this->artisan('order:release-stale-paystack');

        $stock->refresh();
        $order->refresh();
        $this->assertSame(2, $stock->reserved);
        $this->assertSame('pending', $order->payment_status);
    }
}
