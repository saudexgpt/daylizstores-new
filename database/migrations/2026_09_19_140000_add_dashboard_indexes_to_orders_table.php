<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin dashboard sums revenue over paid, non-cancelled orders and counts orders in
 * the last few days. On the live data (~150k orders) those queries had to read every row,
 * because nothing indexed orders.created_at and `total` lived only in the table:
 * the endpoint took ~4 seconds.
 *
 *  - orders(created_at): the 30-day revenue and the 14-day trend become range scans;
 *  - orders(payment_status, order_status, total): the all-time revenue sum is answered
 *    from the index alone, without touching the table.
 */
return new class extends Migration
{
    public function up()
    {
        if (!$this->hasIndex('orders_created_at_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('created_at', 'orders_created_at_index');
            });
        }
        if (!$this->hasIndex('orders_revenue_index')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->index(['payment_status', 'order_status', 'total'], 'orders_revenue_index');
            });
        }
    }

    public function down()
    {
        foreach (['orders_created_at_index', 'orders_revenue_index'] as $name) {
            if ($this->hasIndex($name)) {
                Schema::table('orders', function (Blueprint $table) use ($name) {
                    $table->dropIndex($name);
                });
            }
        }
    }

    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('orders') as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
