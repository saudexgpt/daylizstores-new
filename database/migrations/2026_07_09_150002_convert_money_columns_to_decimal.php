<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // `double` cannot exactly represent decimal currency values, which
        // risks rounding drift in aggregates (SUM(total), discount math).
        // MySQL casts/rounds existing values in place when the column type changes.
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->decimal('delivery_cost', 10, 2)->default(0)->change();
            $table->decimal('total', 10, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->change();
            $table->decimal('total', 10, 2)->change();
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('cost', 10, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->double('amount', 10, 2)->change();
            $table->double('delivery_cost', 10, 2)->default(0)->change();
            $table->double('total', 10, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->double('price', 10, 2)->change();
            $table->double('total', 10, 2)->change();
        });

        Schema::table('item_prices', function (Blueprint $table) {
            $table->double('amount', 10, 2)->change();
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->double('amount', 10, 2)->change();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->double('cost', 10, 2)->default(0)->change();
        });
    }
};
