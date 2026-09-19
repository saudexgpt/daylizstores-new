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
        Schema::create('item_stocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('item_id');
            $table->string('color')->nullable();
            $table->string('size')->nullable();
            $table->integer('quantity_stocked');
            $table->integer('reserved')->default(0);
            $table->integer('sold')->default(0);
            $table->integer('damaged')->nullable();
            $table->integer('balance')->nullable();
            $table->integer('cancelled_quantity_reserved')->default(0);
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();

            $table->index(['quantity_stocked', 'sold'], 'quantity_sold');
            $table->index(['item_id', 'created_at'], 'item_id_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('item_stocks');
    }
};
