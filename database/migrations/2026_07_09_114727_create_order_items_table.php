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
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id'); // BIGINT UNSIGNED in the live database
            $table->integer('order_id')->index('order_id');
            $table->integer('stock_id')->index('stock_id');
            $table->integer('item_id')->nullable();
            $table->string('product_name');
            $table->integer('quantity');
            $table->double('price', 10, 2);
            $table->double('total', 10, 2);
            $table->integer('total_updated')->nullable()->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('order_items');
    }
};
