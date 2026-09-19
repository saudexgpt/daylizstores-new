<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brings real production data in line with the constraints the later migrations add.
 * Named to sort just before 2026_07_09_150000 so it runs ahead of them, and written to be
 * safe to run on any database (it does nothing where there is nothing to fix).
 *
 * Found by rehearsing the migrations on a copy of the live database:
 *
 *  1. Orders whose customer has been deleted. Users are hard-deleted by the admin panel
 *     (the `users.deleted_at` column exists but the model never used it), so a delivered,
 *     paid order can point at a user id that no longer exists — two of them do (orders
 *     42645 and 43007, user 3). The foreign key orders.user_id -> users.id could not be
 *     added over them, and deleting real sales history is not an option. Each missing
 *     user id gets a locked placeholder account ("Deleted customer") instead, so the
 *     orders keep their totals, dates and numbers and simply show that name.
 *
 *  2. Orders with no order number (37 historical rows, 2024–2025). They can't be looked up
 *     or tracked. Every order since the numbering change is "DS" + its id, so these get
 *     the same. (Rows already numbered are never touched.)
 */
return new class extends Migration
{
    public function up()
    {
        $this->createPlaceholdersForDeletedUsers();
        $this->numberUnnumberedOrders();
    }

    public function down()
    {
        // Deliberately irreversible: which orders had no number, and which accounts are
        // placeholders, is recoverable from the data (email ends in @deleted.invalid).
    }

    private function createPlaceholdersForDeletedUsers(): void
    {
        $references = [
            'orders' => 'user_id',
            'item_reviews' => 'user_id',
            'location_user' => 'user_id',
        ];

        foreach ($references as $table => $column) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $missing = DB::table($table)
                ->leftJoin('users', 'users.id', '=', "$table.$column")
                ->whereNull('users.id')
                ->whereNotNull("$table.$column")
                ->distinct()
                ->pluck("$table.$column");

            foreach ($missing as $userId) {
                if (DB::table('users')->where('id', $userId)->exists()) {
                    continue; // created a moment ago for another table
                }
                DB::table('users')->insert([
                    'id' => $userId,
                    'name' => 'Deleted customer',
                    'email' => "deleted-user-{$userId}@deleted.invalid",
                    // random and never disclosed: this account cannot be signed into
                    'password' => Hash::make(Str::random(48)),
                    'role' => 'customer',
                    'password_status' => 'custom',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function numberUnnumberedOrders(): void
    {
        $orders = DB::table('orders')->whereNull('order_number')->orWhere('order_number', '')->pluck('id');

        foreach ($orders as $id) {
            $number = 'DS' . $id;
            // never collide with an existing number (the column is about to become unique)
            if (DB::table('orders')->where('order_number', $number)->exists()) {
                $number .= '-' . Str::upper(Str::random(4));
            }
            DB::table('orders')->where('id', $id)->update(['order_number' => $number]);
        }
    }
};
