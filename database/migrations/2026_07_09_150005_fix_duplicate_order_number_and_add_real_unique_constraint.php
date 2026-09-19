<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        // Historical invoice-numbering bug: order id 445 and order id 4452 both
        // ended up with order_number = 'DLZ4452172' (verified against production
        // data — both are real, paid/delivered orders for different customers).
        // The existing unique(order_number, deleted_at) never actually caught this,
        // because MySQL treats NULL as distinct in unique indexes and both rows
        // are active (deleted_at IS NULL). Disambiguate the older order, then
        // enforce a real uniqueness guarantee on order_number going forward.
        DB::table('orders')
            ->where('id', 445)
            ->where('order_number', 'DLZ4452172')
            ->update(['order_number' => 'DLZ4452172-DUP']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number', 'deleted_at']);
            $table->unique('order_number');
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
            $table->dropUnique(['order_number']);
            $table->unique(['order_number', 'deleted_at']);
        });

        DB::table('orders')
            ->where('id', 445)
            ->where('order_number', 'DLZ4452172-DUP')
            ->update(['order_number' => 'DLZ4452172']);
    }
};
