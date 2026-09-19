<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Product costing (FIFO, per size).
 *
 *   stock_receipts / stock_receipt_lines   a delivery from a supplier: invoice, extra costs, what was received
 *   cost_layers                            one layer per delivery per product+size — what is still on the shelf and its cost
 *   cost_consumptions                      which layers each sold order line took its cost from
 *   stock_adjustments                      damage, loss, count differences
 *   order_items.cost_total                 the cost of what was sold, frozen at the moment of sale
 *
 * Money is decimal(14,2) like the rest of the books; a layer keeps its TOTAL cost (and the total
 * still remaining) rather than a per-unit price, so landed costs that do not divide evenly into
 * kobo never lose or gain a kobo: units taken from a layer cost a proportional share of what is
 * left, and the last unit takes the remainder.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->date('received_on');
            $table->string('supplier', 150);
            $table->string('invoice_number', 100)->nullable();
            $table->decimal('items_total', 14, 2);
            $table->decimal('extra_costs', 14, 2)->default(0);
            $table->string('extra_costs_note', 255)->nullable();
            $table->decimal('landed_total', 14, 2);
            // where the money came from; null = bought on credit (Accounts Payable)
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->enum('status', ['posted', 'void'])->default('posted');
            $table->string('void_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('payment_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['received_on', 'id']);
        });

        Schema::create('stock_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_receipt_id');
            $table->unsignedInteger('item_id');
            $table->string('size', 60)->default('');
            $table->string('color', 60)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 14, 2);
            // this line's share of the delivery's extra costs
            $table->decimal('extra_cost', 14, 2)->default(0);
            $table->timestamps();

            $table->foreign('stock_receipt_id')->references('id')->on('stock_receipts')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
        });

        Schema::create('cost_layers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            // '' when the product has no sizes
            $table->string('size', 60)->default('');
            $table->enum('source', ['receipt', 'opening', 'adjustment']);
            $table->unsignedBigInteger('stock_receipt_id')->nullable();
            $table->date('received_on');
            $table->unsignedInteger('qty_received');
            $table->unsignedInteger('qty_remaining');
            $table->decimal('cost_total', 14, 2);
            $table->decimal('cost_remaining', 14, 2);
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->foreign('stock_receipt_id')->references('id')->on('stock_receipts')->nullOnDelete();
            // FIFO reads the oldest layer with something left, per product + size
            $table->index(['item_id', 'size', 'qty_remaining', 'received_on', 'id'], 'cost_layers_fifo');
        });

        Schema::create('cost_consumptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('stock_adjustment_id')->nullable();
            // null only for a provisional consumption (sold more than the layers held)
            $table->unsignedBigInteger('cost_layer_id')->nullable();
            $table->unsignedInteger('item_id');
            $table->string('size', 60)->default('');
            $table->unsignedInteger('quantity');
            $table->decimal('cost', 14, 2);
            $table->boolean('provisional')->default(false);
            $table->timestamps();

            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('cost_layer_id')->references('id')->on('cost_layers')->restrictOnDelete();
            $table->index(['item_id', 'size', 'provisional']);
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->date('adjusted_on');
            $table->unsignedInteger('item_id');
            $table->unsignedBigInteger('item_stock_id');
            $table->string('size', 60)->default('');
            // negative = stock lost / damaged, positive = stock found
            $table->integer('quantity');
            $table->enum('reason', ['damage', 'loss', 'count_difference', 'other']);
            $table->string('note', 255)->nullable();
            // what the units were worth (FIFO for a loss; the unit cost you give for a gain)
            $table->decimal('cost', 14, 2)->default(0);
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->foreign('item_stock_id')->references('id')->on('item_stocks')->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // null = not costed (sold before costing went live, or not dispatched yet)
            $table->decimal('cost_total', 14, 2)->nullable()->after('total');
            $table->timestamp('costed_at')->nullable()->after('cost_total');
        });

        $this->accounts();
        $this->settings();
        $this->permission();
    }

    public function down()
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['cost_total', 'costed_at']);
        });
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('cost_consumptions');
        Schema::dropIfExists('cost_layers');
        Schema::dropIfExists('stock_receipt_lines');
        Schema::dropIfExists('stock_receipts');
        DB::table('settings')->whereIn('key', ['costing_enabled', 'costing_start_date'])->delete();
        $ids = DB::table('permissions')->where('name', 'view cost')->where('guard_name', 'api')->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** the accounts costing posts to become system accounts (they cannot be retyped or deactivated) */
    private function accounts(): void
    {
        DB::table('accounts')->where('code', '5000')->update([
            'name' => 'Cost of Goods Sold',
            'is_system' => true,
            'description' => 'Cost of the stock that was sold — posted automatically from FIFO product costs once costing is live',
        ]);
        DB::table('accounts')->where('code', '1100')->update([
            'is_system' => true,
            'description' => 'Value of stock on the shelf — moves only through Receive stock, sales and stock adjustments',
        ]);
        DB::table('accounts')->where('code', '6960')->update(['is_system' => true]);
        DB::table('accounts')->where('code', '3900')->update(['is_system' => true]);
        DB::table('accounts')->where('code', '2000')->update(['is_system' => true]);
    }

    private function settings(): void
    {
        foreach (['costing_enabled' => '0', 'costing_start_date' => ''] as $key => $value) {
            if (!DB::table('settings')->where('key', $key)->exists()) {
                DB::table('settings')->insert(['key' => $key, 'value' => $value]);
            }
        }
    }

    private function permission(): void
    {
        $now = now();
        if (!DB::table('permissions')->where('name', 'view cost')->where('guard_name', 'api')->exists()) {
            DB::table('permissions')->insert(['name' => 'view cost', 'guard_name' => 'api', 'created_at' => $now, 'updated_at' => $now]);
        }
        $adminId = DB::table('roles')->where('name', 'admin')->where('guard_name', 'api')->value('id');
        if ($adminId) {
            $missing = DB::table('permissions')->where('guard_name', 'api')
                ->whereNotIn('id', DB::table('role_has_permissions')->where('role_id', $adminId)->select('permission_id'))->pluck('id');
            foreach ($missing as $permissionId) {
                DB::table('role_has_permissions')->insert(['permission_id' => $permissionId, 'role_id' => $adminId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
