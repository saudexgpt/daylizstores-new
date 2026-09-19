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
        Schema::create('item_reviews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('item_id')->index('item_id');
            $table->integer('user_id');
            $table->string('comment')->nullable();
            $table->integer('star')->nullable();
            $table->boolean('is_published')->default(true)->index('is_published');
            $table->timestamps();

            $table->unique(['user_id', 'item_id'], 'user_id_item_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('item_reviews');
    }
};
