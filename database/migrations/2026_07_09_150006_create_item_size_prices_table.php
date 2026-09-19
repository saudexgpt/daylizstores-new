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
        Schema::create('item_size_prices', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('item_id')->index();
            $table->string('size');
            $table->double('amount', 10, 2);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['item_id', 'size']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('item_size_prices');
    }
};
