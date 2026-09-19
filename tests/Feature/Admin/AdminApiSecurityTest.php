<?php

namespace Tests\Feature\Admin;

use App\Models\ItemReview;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemMedia;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the admin-API hardening: every case here was a real
 * hole (or a crash) in the endpoints the admin panel calls.
 */
class AdminApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    /** A non-admin staff member who has been granted only "manage user". */
    private function userManager()
    {
        $permission = Permission::firstOrCreate(['name' => 'manage user', 'guard_name' => 'api']);
        $staff = $this->createStaffUser();
        $staff->givePermissionTo($permission);

        return $staff;
    }

    private function makeOrderWithStock(string $orderStatus = 'Pending', int $stocked = 50, int $reserved = 10, int $quantity = 10): array
    {
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id]);

        $stock = new ItemStock();
        $stock->item_id = $item->id;
        $stock->color = 'Black';
        $stock->size = 'M';
        $stock->quantity_stocked = $stocked;
        $stock->reserved = $reserved;
        $stock->save();

        $order = Order::factory()->create();
        $order->order_status = $orderStatus;
        $order->payment_status = 'pending';
        $order->save();

        $line = new OrderItem();
        $line->order_id = $order->id;
        $line->stock_id = $stock->id;
        $line->item_id = $item->id;
        $line->product_name = $item->name;
        $line->quantity = $quantity;
        $line->price = 1000;
        $line->total = 1000 * $quantity;
        $line->save();

        return [$order, $stock, $item];
    }

    // ------------------------------------------------------------------ users

    public function testAdminAccountsCannotBeDeleted()
    {
        $admin = $this->createAdminUser();
        $otherAdmin = $this->createAdminUser();

        $this->actingAs($admin, 'api')->deleteJson('/api/users/' . $otherAdmin->id)->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'deleted_at' => null]);
    }

    public function testAdminCannotDeleteOwnAccountButCanDeleteOthers()
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser();

        $this->actingAs($admin, 'api')->deleteJson('/api/users/' . $admin->id)->assertStatus(403);
        $this->assertNotNull(\App\Laravue\Models\User::find($admin->id));

        $this->actingAs($admin, 'api')->deleteJson('/api/users/' . $customer->id)->assertStatus(204);
        $this->assertNull(\App\Laravue\Models\User::find($customer->id));
    }

    public function testACustomerWithOrdersCannotBeDeleted()
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser();
        $order = Order::factory()->create();
        $order->user_id = $customer->id;
        $order->save();

        $this->actingAs($admin, 'api')->deleteJson('/api/users/' . $customer->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This account has placed orders, so it cannot be deleted. Its order history must be kept.');
        $this->assertNotNull(\App\Laravue\Models\User::find($customer->id));
        $this->assertNotNull(Order::find($order->id));
    }

    public function testUserManagerCannotMintAnAdmin()
    {
        $manager = $this->userManager();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

        $response = $this->actingAs($manager, 'api')->postJson('/api/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'phone' => '08000000000',
            'password' => 'password123', 'confirmPassword' => 'password123', 'role' => 'admin',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function testUserManagerCannotEditOrResetAnAdmin()
    {
        $manager = $this->userManager();
        $admin = $this->createAdminUser(['email' => 'boss@example.com']);

        $this->actingAs($manager, 'api')->putJson('/api/users/' . $admin->id, [
            'name' => 'Boss', 'email' => 'attacker@example.com', 'phone' => '08000000000',
        ])->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'email' => 'boss@example.com']);

        $this->actingAs($manager, 'api')->putJson('/api/users/reset-password/' . $admin->id)->assertStatus(403);
    }

    public function testCreatingOrEditingAUserRejectsADuplicateEmail()
    {
        $admin = $this->createAdminUser();
        $existing = $this->createCustomerUser(['email' => 'taken@example.com']);
        $other = $this->createCustomerUser(['email' => 'other@example.com']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);

        $this->actingAs($admin, 'api')->postJson('/api/users', [
            'name' => 'Dup', 'email' => 'taken@example.com', 'phone' => '08000000000',
            'password' => 'password123', 'confirmPassword' => 'password123', 'role' => 'manager',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->actingAs($admin, 'api')->putJson('/api/users/' . $other->id, [
            'name' => 'Other', 'email' => 'taken@example.com', 'phone' => '08000000000',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        // keeping your own email is fine
        $this->actingAs($admin, 'api')->putJson('/api/users/' . $existing->id, [
            'name' => 'Renamed', 'email' => 'taken@example.com', 'phone' => '08000000000',
        ])->assertStatus(200);
    }

    public function testProfileUpdateSavesTheAddress()
    {
        $customer = $this->createCustomerUser(['email' => 'addr@example.com']);

        $this->actingAs($customer, 'api')->putJson('/api/users/' . $customer->id, [
            'name' => 'Ada', 'email' => 'addr@example.com', 'phone' => '08000000000', 'address' => '12 Marina Road',
        ])->assertStatus(200);

        $this->assertSame('12 Marina Road', $customer->fresh()->address);
    }

    public function testCustomerListCarriesOrderCounts()
    {
        $admin = $this->createAdminUser();
        $customer = $this->createCustomerUser();
        $order = Order::factory()->create();
        $order->user_id = $customer->id;
        $order->save();

        $response = $this->actingAs($admin, 'api')->getJson('/api/users?role=customer&keyword=' . urlencode($customer->email));

        $response->assertStatus(200);
        $this->assertSame(1, $response->json('data.0.orders_count'));
        $this->assertNotNull($response->json('data.0.joined_at'));
    }

    public function testOnlyAnAdminCanAssignRolesAndNotToThemselves()
    {
        $admin = $this->createAdminUser();
        $manager = $this->userManager();
        $customer = $this->createCustomerUser();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);

        $this->actingAs($manager, 'api')->putJson('/api/users/assign-role/' . $customer->id, ['role' => 'manager'])->assertStatus(403);
        $this->actingAs($admin, 'api')->putJson('/api/users/assign-role/' . $customer->id, ['role' => 'no-such-role'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/users/assign-role/' . $admin->id, ['role' => 'manager'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/users/assign-role/' . $customer->id, ['role' => 'manager'])->assertStatus(200);
        $this->assertTrue($customer->fresh()->hasRole('manager'));
    }

    public function testChangingAPasswordRequiresTheCurrentPasswordOfThatAccount()
    {
        $customer = $this->createCustomerUser(['email' => 'pw@example.com', 'password' => 'old-password-1']);

        $body = fn ($old) => [
            'user_id' => $customer->id, 'email' => 'pw@example.com', 'password' => $old,
            'new_password' => 'brand-new-pass-2', 'c_password' => 'brand-new-pass-2',
        ];

        $this->actingAs($customer, 'api')->postJson('/api/users/update-password', $body('wrong-password'))->assertStatus(401);
        $this->actingAs($customer, 'api')->postJson('/api/users/update-password', array_merge($body('old-password-1'), ['new_password' => 'short', 'c_password' => 'short']))->assertStatus(422);
        $this->actingAs($customer, 'api')->postJson('/api/users/update-password', $body('old-password-1'))->assertStatus(200);

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-pass-2', $customer->fresh()->password));
    }

    public function testAnotherCustomerCannotChangeMyPassword()
    {
        $victim = $this->createCustomerUser(['email' => 'victim@example.com', 'password' => 'victim-pass-1']);
        $attacker = $this->createCustomerUser(['email' => 'attacker@example.com', 'password' => 'attacker-pass-1']);

        $this->actingAs($attacker, 'api')->postJson('/api/users/update-password', [
            'user_id' => $victim->id, 'email' => 'attacker@example.com', 'password' => 'attacker-pass-1',
            'new_password' => 'hijacked-pass-99', 'c_password' => 'hijacked-pass-99',
        ])->assertStatus(403);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('victim-pass-1', $victim->fresh()->password));
    }

    public function testUserListPageSizeIsCapped()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'api')->getJson('/api/users?limit=99999');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('meta.per_page'));
    }

    public function testUserNotificationsAlwaysReturnsAnArray()
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'api')->getJson('/api/user-notifications')
            ->assertStatus(200)
            ->assertJson(['notifications' => []]);
    }

    // --------------------------------------------------------------- products

    public function testProductPayloadIsValidated()
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'category_id', 'amount']);

        $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [
            'name' => 'Ghost', 'category_id' => 999999, 'amount' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors(['category_id', 'amount']);
    }

    public function testProductPayloadWithoutOptionalArraysStillWorks()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();

        // `images`, `discounts` etc. omitted used to crash with a TypeError (count(null))
        $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [
            'name' => 'Minimal', 'category_id' => $category->id, 'amount' => 100,
        ])->assertStatus(200);
    }

    public function testProductsWhoseNamesProduceTheSameSlugGetDistinctSlugs()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();

        // "Twin Product" and "twin-product" are different names (so both are allowed
        // by the unique index on items.name) but slugify identically.
        foreach (['Twin Product', 'twin-product'] as $name) {
            $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [
                'name' => $name, 'category_id' => $category->id, 'amount' => 100,
            ])->assertStatus(200);
        }

        $slugs = Item::whereIn('name', ['Twin Product', 'twin-product'])->pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique());
    }

    public function testDuplicateProductNameIsAValidationErrorNotAServerError()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        Item::factory()->create(['category_id' => $category->id, 'name' => 'Already Here']);

        $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [
            'name' => 'Already Here', 'category_id' => $category->id, 'amount' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function testAnItemCannotStealOrDeleteAnotherItemsImages()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $mine = Item::factory()->create(['category_id' => $category->id]);
        $theirs = Item::factory()->create(['category_id' => $category->id]);

        $theirMedia = new ItemMedia();
        $theirMedia->item_id = $theirs->id;
        $theirMedia->link = '/storage/items/theirs.jpg';
        $theirMedia->thumbnail = '/storage/items/thumbnails/theirs.jpg';
        $theirMedia->save();

        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/update/' . $mine->id, [
            'name' => $mine->name, 'category_id' => $category->id, 'amount' => 100,
            'images' => [$theirMedia->id], 'deletedImages' => [$theirMedia->id],
        ])->assertStatus(200);

        $theirMedia->refresh();
        $this->assertSame($theirs->id, (int) $theirMedia->item_id, 'image was reassigned to another product');
        $this->assertNotNull(ItemMedia::find($theirMedia->id), 'image of another product was deleted');
    }

    public function testStockingRejectsNegativeAndZeroQuantities()
    {
        $admin = $this->createAdminUser();
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id]);

        foreach ([-5, 0] as $bad) {
            $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, [
                'sub_batches' => [['quantity' => $bad, 'color' => 'Red', 'size' => 'M']],
            ])->assertStatus(422);
        }
        $this->assertDatabaseMissing('item_stocks', ['item_id' => $item->id]);
    }

    public function testStockingAPlainProductNeedsNoColourOrSize()
    {
        $admin = $this->createAdminUser();
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id]);

        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, [
            'sub_batches' => [['quantity' => 7, 'color' => null, 'size' => null]],
        ])->assertStatus(200);
        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, [
            'sub_batches' => [['quantity' => 3, 'color' => null, 'size' => null]],
        ])->assertStatus(200);

        $this->assertSame(1, ItemStock::where('item_id', $item->id)->count());
        $this->assertSame(10, (int) ItemStock::where('item_id', $item->id)->value('quantity_stocked'));
    }

    public function testStockingAccumulatesOnTheSameColourAndSize()
    {
        $admin = $this->createAdminUser();
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id]);
        $payload = ['sub_batches' => [['quantity' => 5, 'color' => 'Red', 'size' => 'M']]];

        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, $payload)->assertStatus(200);
        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, $payload)->assertStatus(200);

        $this->assertSame(10, (int) ItemStock::where('item_id', $item->id)->sum('quantity_stocked'));
        $this->assertSame(1, ItemStock::where('item_id', $item->id)->count());
    }

    public function testToggleStatusValidatesItsValue()
    {
        $admin = $this->createAdminUser();
        $item = Item::factory()->create(['category_id' => Category::factory()->create()->id, 'enabled' => true]);

        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/toggle-status/' . $item->id, ['value' => 'banana'])->assertStatus(422);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'enabled' => 1]);
    }

    public function testCategoryNameIsRequiredAndUniqueOnRename()
    {
        $admin = $this->createAdminUser();
        Category::factory()->create(['name' => 'Shoes']);
        $bags = Category::factory()->create(['name' => 'Bags']);

        $this->actingAs($admin, 'api')->postJson('/api/stock/item-category/store', ['name' => ''])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/stock/item-category/update/' . $bags->id, ['name' => 'Shoes'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/stock/item-category/update/' . $bags->id, ['name' => 'Handbags'])->assertStatus(200);
    }

    public function testACategoryWithProductsCannotBeDeleted()
    {
        $admin = $this->createAdminUser();
        $used = Category::factory()->create();
        $empty = Category::factory()->create();
        Item::factory()->create(['category_id' => $used->id]);

        $this->actingAs($admin, 'api')->deleteJson('/api/stock/item-category/delete/' . $used->id)
            ->assertStatus(422);
        $this->assertNotNull(Category::find($used->id));

        $this->actingAs($admin, 'api')->deleteJson('/api/stock/item-category/delete/' . $empty->id)
            ->assertStatus(204);
        $this->assertNull(Category::find($empty->id));
    }

    public function testUploadRequiresTheProductPermission()
    {
        $customer = $this->createCustomerUser();

        $this->actingAs($customer, 'api')->postJson('/api/upload-file', [
            'file' => \Illuminate\Http\UploadedFile::fake()->image('avatar.png'),
        ])->assertStatus(403);
    }

    public function testTheRemovedGetDeleteRouteIsGone()
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin, 'api')->getJson('/api/stock/general-items/delete-item-tax')->assertStatus(404);
    }

    // ---------------------------------------------------------------- reviews

    public function testReviewFieldIsAClosedSet()
    {
        [$order, , $item] = $this->makeOrderWithStock('Delivered');

        // `is_published` (moderation) and `user_id` used to be writable through `field`
        foreach (['is_published', 'user_id', 'item_id'] as $field) {
            $this->postJson('/api/give-product-review', [
                'user_id' => $order->user_id, 'item_id' => $item->id, 'field' => $field, 'value' => 1,
            ])->assertStatus(422);
        }
        $this->assertSame(0, ItemReview::count());
    }

    public function testOnlyCustomersWhoReceivedTheProductCanReviewIt()
    {
        [$deliveredOrder, , $item] = $this->makeOrderWithStock('Delivered');
        $stranger = $this->createCustomerUser();

        $this->postJson('/api/give-product-review', [
            'user_id' => $stranger->id, 'item_id' => $item->id, 'field' => 'star', 'value' => 5,
        ])->assertStatus(403);

        $this->postJson('/api/give-product-review', [
            'user_id' => $deliveredOrder->user_id, 'item_id' => $item->id, 'field' => 'star', 'value' => 6,
        ])->assertStatus(422);

        $this->postJson('/api/give-product-review', [
            'user_id' => $deliveredOrder->user_id, 'item_id' => $item->id, 'field' => 'star', 'value' => 4,
        ])->assertStatus(200);

        $review = ItemReview::where('item_id', $item->id)->first();
        $this->assertSame(4, (int) $review->star);
    }

    public function testACustomerCannotRepublishAReviewAnAdminTookDown()
    {
        $admin = $this->createAdminUser();
        [$order, , $item] = $this->makeOrderWithStock('Delivered');
        $this->postJson('/api/give-product-review', [
            'user_id' => $order->user_id, 'item_id' => $item->id, 'field' => 'star', 'value' => 1,
        ])->assertStatus(200);
        $review = ItemReview::where('item_id', $item->id)->first();

        // admin unpublishes it (reviews are live by default; moderation is after the fact)
        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/approve/' . $review->id, ['value' => 0])->assertStatus(200);
        $this->assertFalse((bool) $review->fresh()->is_published);

        // neither a direct attempt nor editing the review brings it back
        $this->postJson('/api/give-product-review', [
            'user_id' => $order->user_id, 'item_id' => $item->id, 'field' => 'is_published', 'value' => 1,
        ])->assertStatus(422);
        $this->postJson('/api/give-product-review', [
            'user_id' => $order->user_id, 'item_id' => $item->id, 'field' => 'comment', 'value' => 'edited',
        ])->assertStatus(200);
        $this->assertFalse((bool) $review->fresh()->is_published);
    }

    public function testAdminCanApproveAReview()
    {
        $admin = $this->createAdminUser();
        [$order, , $item] = $this->makeOrderWithStock('Delivered');
        $review = new ItemReview();
        $review->user_id = $order->user_id;
        $review->item_id = $item->id;
        $review->star = 5;
        $review->save();

        $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/approve/' . $review->id, ['value' => 1])
            ->assertStatus(200)
            ->assertJsonPath('item_review.id', $review->id);
        $this->assertTrue((bool) $review->fresh()->is_published);
    }

    // ----------------------------------------------------------------- orders

    public function testOrderStatusAndPaymentStatusMustBeKnownValues()
    {
        $admin = $this->createAdminUser();
        [$order] = $this->makeOrderWithStock();

        $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Teleported'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Pending', 'payment_status' => 'whatever'])->assertStatus(422);
        $this->assertSame('Pending', $order->fresh()->order_status);
    }

    public function testSendingAnOrderOutTwiceMovesStockOnlyOnce()
    {
        $admin = $this->createAdminUser();
        [$order, $stock] = $this->makeOrderWithStock('Pending', stocked: 50, reserved: 30, quantity: 10);

        foreach ([1, 2, 3] as $attempt) {
            $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $order->id, [
                'status' => 'On Transit', 'payment_status' => 'paid',
            ])->assertStatus(200);
        }

        $stock->refresh();
        $this->assertSame(10, (int) $stock->sold, 'sold must only be incremented once');
        $this->assertSame(20, (int) $stock->reserved, 'other orders\' reservations must be left alone');
    }

    public function testADeliveredOrderCannotBeCancelledAndACancelledOneCannotBeReopened()
    {
        $admin = $this->createAdminUser();
        [$delivered, $stockA] = $this->makeOrderWithStock('Delivered', stocked: 50, reserved: 0);
        [$cancelled] = $this->makeOrderWithStock('Cancelled', stocked: 50, reserved: 0);

        $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $delivered->id, ['status' => 'Cancelled'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $cancelled->id, ['status' => 'Pending'])->assertStatus(422);

        $this->assertSame('Delivered', $delivered->fresh()->order_status);
        $this->assertSame('Cancelled', $cancelled->fresh()->order_status);
    }

    public function testCancellingTwiceReleasesReservedStockOnce()
    {
        $admin = $this->createAdminUser();
        // another order holds 20 of the same stock row's reservation
        [$order, $stock] = $this->makeOrderWithStock('Pending', stocked: 50, reserved: 30, quantity: 10);

        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Cancelled', 'payment_status' => 'cancelled'])->assertStatus(200);
        }

        $this->assertSame(20, (int) $stock->fresh()->reserved);
    }

    public function testOmittingPaymentStatusKeepsTheExistingOne()
    {
        $admin = $this->createAdminUser();
        [$order] = $this->makeOrderWithStock();
        $order->payment_status = 'paid';
        $order->save();

        $this->actingAs($admin, 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Pending'])->assertStatus(200);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function testOrderListPageSizeIsCapped()
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin, 'api')->getJson('/api/order/general?limit=50000');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('orders.per_page'));
    }

    // -------------------------------------------------------------- dashboard

    public function testAdminDashboardReturnsSummaryTrendAndLowStock()
    {
        $admin = $this->createAdminUser();
        [, $stock, $item] = $this->makeOrderWithStock('Pending', stocked: 5, reserved: 0, quantity: 1);

        $response = $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin');

        $response->assertStatus(200)->assertJsonStructure([
            'data_summary' => ['products', 'delivered_orders', 'pending_orders', 'transit_orders', 'cancelled_orders', 'awaiting_payment', 'revenue_total', 'revenue_30_days'],
            'trend' => [['date', 'orders', 'revenue']],
            'low_stock' => [['item_id', 'name', 'total_balance']],
        ]);
        $this->assertCount(14, $response->json('trend'));
        $this->assertSame($item->id, $response->json('low_stock.0.item_id'));
    }

    public function testDashboardIsCachedAndTheRefreshButtonBypassesTheCache()
    {
        $admin = $this->createAdminUser();
        $before = $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin')->json('data_summary.pending_orders');

        $this->makeOrderWithStock('Pending');

        // within the minute: the cached figure, so the new order is not counted yet
        $this->assertSame($before, $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin')->json('data_summary.pending_orders'));
        // Refresh (?fresh=1): recomputed, and re-primes the cache
        $this->assertSame($before + 1, $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin?fresh=1')->json('data_summary.pending_orders'));
        $this->assertSame($before + 1, $this->actingAs($admin, 'api')->getJson('/api/dashboard/admin')->json('data_summary.pending_orders'));
    }

    public function testTheThreePreviouslyMissingPermissionsExistAndGateTheirRoutes()
    {
        $names = ['view admin dashboard', 'backup database', 'assign order to location'];
        foreach ($names as $name) {
            $this->assertDatabaseHas('permissions', ['name' => $name, 'guard_name' => 'api']);
        }

        $staff = $this->createStaffUser();

        // without the permission: refused; with it: allowed (they could not be granted before —
        // a permission that isn't in the table can't be ticked in Roles & permissions)
        $this->actingAs($staff, 'api')->getJson('/api/dashboard/admin')->assertStatus(403);
        $this->actingAs($staff, 'api')->getJson('/api/reports/backups')->assertStatus(403);

        $staff->givePermissionTo(Permission::findByName('view admin dashboard', 'api'));
        $staff->givePermissionTo(Permission::findByName('backup database', 'api'));

        $this->actingAs($staff, 'api')->getJson('/api/dashboard/admin')->assertStatus(200);
        $this->actingAs($staff, 'api')->getJson('/api/reports/backups')->assertStatus(200);
    }

    public function testThePermissionsMigrationIsIdempotentAndGivesTheAdminRoleEverything()
    {
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        // a permission the admin role was missing on the live database
        Permission::firstOrCreate(['name' => 'manage location', 'guard_name' => 'api']);
        $migration = require database_path('migrations/2026_09_19_150000_add_missing_permissions.php');

        $migration->up();
        $migration->up(); // running it twice must change nothing

        $this->assertSame(1, Permission::where('name', 'backup database')->where('guard_name', 'api')->count());
        $this->assertSame(
            Permission::where('guard_name', 'api')->count(),
            $admin->fresh()->permissions()->count(),
            'the admin role must hold every permission'
        );
    }

    public function testCustomerCannotReachTheLowStockEndpoint()
    {
        $customer = $this->createCustomerUser();

        $this->actingAs($customer, 'api')->getJson('/api/dashboard/admin/running-out-of-stock-products')->assertStatus(403);
    }
}
