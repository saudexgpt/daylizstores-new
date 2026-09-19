<?php

namespace Tests\Feature\Stock;

use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCrudTest extends TestCase
{
    use RefreshDatabase;

    public function testAnyoneCanBrowseEnabledItems()
    {
        $category = Category::factory()->create();
        Item::factory()->create(['category_id' => $category->id, 'enabled' => true]);

        $response = $this->getJson('/api/get-items');

        $response->assertStatus(200);
    }

    public function testStaffWithoutPermissionCannotCreateItem()
    {
        $staff = $this->createStaffUser();
        $category = Category::factory()->create();

        $response = $this->actingAs($staff, 'api')->postJson('/api/stock/general-items/store', [
            'name' => 'New Product',
            'category_id' => $category->id,
            'description' => 'A product',
            'amount' => 1500,
            'images' => [],
            'deletedImages' => [],
            'discounts' => [],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('items', ['name' => 'New Product']);
    }

    public function testAdminCanCreateItem()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();

        $response = $this->actingAs($admin, 'api')->postJson('/api/stock/general-items/store', [
            'name' => 'New Product',
            'category_id' => $category->id,
            'description' => 'A product',
            'amount' => 1500,
            'images' => [],
            'deletedImages' => [],
            'discounts' => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['name' => 'New Product', 'category_id' => $category->id]);
        $this->assertDatabaseHas('item_prices', ['amount' => 1500]);
    }

    public function testAdminCanUpdateItem()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id, 'name' => 'Old Name']);

        $response = $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/update/' . $item->id, [
            'name' => 'Updated Name',
            'category_id' => $category->id,
            'description' => 'Updated description',
            'amount' => 2000,
            'images' => [],
            'deletedImages' => [],
            'discounts' => [],
            'deletedDiscounts' => [],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'Updated Name']);
    }

    public function testAdminCanStockUpAnItem()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin, 'api')->putJson('/api/stock/general-items/stockup/' . $item->id, [
            'sub_batches' => [
                ['quantity' => 50, 'color' => 'others', 'other_color' => 'Red', 'size' => 'M'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('item_stocks', [
            'item_id' => $item->id,
            'quantity_stocked' => 50,
            'color' => 'Red',
            'size' => 'M',
        ]);
    }

    public function testAdminCanToggleItemStatus()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id, 'enabled' => true]);

        $response = $this->actingAs($admin, 'api')
            ->putJson('/api/stock/general-items/toggle-status/' . $item->id, [
                'value' => 0,
                'action' => 'disabled',
            ]);

        $response->assertStatus(204);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'enabled' => 0]);
    }

    public function testAdminCanDeleteItem()
    {
        $admin = $this->createAdminUser();
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($admin, 'api')->deleteJson('/api/stock/general-items/delete/' . $item->id);

        $response->assertStatus(204);
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    }

    public function testCustomerCannotDeleteItem()
    {
        $customer = $this->createCustomerUser();
        $category = Category::factory()->create();
        $item = Item::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($customer, 'api')->deleteJson('/api/stock/general-items/delete/' . $item->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('items', ['id' => $item->id, 'deleted_at' => null]);
    }
}
