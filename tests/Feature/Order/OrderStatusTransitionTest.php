<?php

namespace Tests\Feature\Order;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an order with one order item against a stock row that already
     * has `reserved` quantity set, mirroring the state left behind by
     * OrdersController::store()'s reserveProduct() step.
     */
    private function makeReservedOrder(int $stocked = 50, int $reserved = 10, int $orderedQuantity = 10): array
    {
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = 'M';
        $stock->quantity_stocked = $stocked;
        $stock->reserved = $reserved;
        $stock->save();

        $order = Order::factory()->create();

        $orderItem = new OrderItem();
        $orderItem->order_id = $order->id;
        $orderItem->stock_id = $stock->id;
        $orderItem->item_id = $item->id;
        $orderItem->product_name = $item->name;
        $orderItem->quantity = $orderedQuantity;
        $orderItem->price = 1000;
        $orderItem->total = 1000 * $orderedQuantity;
        $orderItem->save();

        return [$order, $stock, $orderItem];
    }

    public function testCancellingAnOrderReleasesReservedStock()
    {
        $admin = $this->createAdminUser();
        [$order, $stock] = $this->makeReservedOrder(stocked: 50, reserved: 10, orderedQuantity: 10);

        $response = $this->actingAs($admin, 'api')
            ->putJson('/api/order/general/change-status/' . $order->id, [
                'status' => 'Cancelled',
                'payment_status' => 'cancelled',
            ]);

        $response->assertStatus(200);

        $order->refresh();
        $stock->refresh();
        $this->assertSame('Cancelled', $order->order_status);
        $this->assertSame(0, $stock->reserved);
    }

    public function testMarkingAnOrderOnTransitMovesReservedStockToSold()
    {
        $admin = $this->createAdminUser();
        [$order, $stock] = $this->makeReservedOrder(stocked: 50, reserved: 10, orderedQuantity: 10);

        $response = $this->actingAs($admin, 'api')
            ->putJson('/api/order/general/change-status/' . $order->id, [
                'status' => 'On Transit',
                'payment_status' => 'paid',
            ]);

        $response->assertStatus(200);

        $order->refresh();
        $stock->refresh();
        $this->assertSame('Delivered', $order->order_status); // controller maps On Transit -> Delivered
        $this->assertSame(0, $stock->reserved);
        $this->assertSame(10, $stock->sold);
    }

    public function testStaffWithoutPermissionCannotChangeOrderStatus()
    {
        $staff = $this->createStaffUser();
        [$order, $stock] = $this->makeReservedOrder();

        $response = $this->actingAs($staff, 'api')
            ->putJson('/api/order/general/change-status/' . $order->id, [
                'status' => 'Cancelled',
                'payment_status' => 'cancelled',
            ]);

        $response->assertStatus(403);
        $order->refresh();
        $this->assertNotSame('Cancelled', $order->order_status);
    }

    public function testCustomerCanViewTheirOwnOrders()
    {
        $customer = $this->createCustomerUser();
        $order = Order::factory()->create();
        $order->user_id = $customer->id;
        $order->save();

        $response = $this->actingAs($customer, 'api')->getJson('/api/order/general/my-orders');

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => $order->id]);
    }
}
