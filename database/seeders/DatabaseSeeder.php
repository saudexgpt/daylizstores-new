<?php

namespace Database\Seeders;

use App\Laravue\Models\Role;
use App\Laravue\Models\User;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a usable dev/staging dataset from migrations alone.
     *
     * @return void
     */
    public function run()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'api']);

        $admin = User::factory()->staff()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->syncRoles($adminRole);

        Category::factory(5)->create()->each(function (Category $category) {
            Item::factory(4)->create(['category_id' => $category->id])->each(function (Item $item) {
                ItemPrice::create([
                    'item_id' => $item->id,
                    'amount' => fake()->randomFloat(2, 500, 20000),
                ]);
                $itemStock = new ItemStock();
                $itemStock->item_id = $item->id;
                $itemStock->quantity_stocked = fake()->numberBetween(20, 200);
                $itemStock->save();
            });
        });

        User::factory(10)->customer()->create();
    }
}
