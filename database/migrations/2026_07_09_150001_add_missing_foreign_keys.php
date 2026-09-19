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
        // item_media has 43 rows pointing at item_ids that no longer exist
        // (verified against production data) — clean those up first, since
        // MySQL will refuse to add a FK over data that already violates it.
        DB::statement(
            'DELETE item_media FROM item_media LEFT JOIN items ON item_media.item_id = items.id
             WHERE item_media.item_id IS NOT NULL AND items.id IS NULL'
        );

        // Every referencing column below is a plain signed `int`, while the
        // primary keys they point at are unsigned (int or bigint). MySQL/InnoDB
        // requires an exact type match to form a foreign key, so widen/unsign
        // these columns first (all existing values are positive IDs, verified
        // against production data, so this is a lossless type change).
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            // orders.id is BIGINT UNSIGNED (live and baseline), so the column must be too —
            // an INT here makes MySQL refuse the foreign key
            $table->unsignedBigInteger('order_id')->change();
            $table->unsignedBigInteger('stock_id')->change();
            $table->unsignedInteger('item_id')->nullable()->change();
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->change();
        });

        Schema::table('item_reviews', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->change();
            $table->unsignedBigInteger('user_id')->change();
        });

        Schema::table('item_media', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->nullable()->change();
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->unsignedInteger('item_id')->change();
        });

        Schema::table('location_user', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->change();
            $table->unsignedBigInteger('user_id')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('RESTRICT');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('stock_id')->references('id')->on('item_stocks')->onUpdate('CASCADE')->onDelete('RESTRICT');
            $table->foreign('item_id')->references('id')->on('items')->onUpdate('CASCADE')->onDelete('SET NULL');
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('item_reviews', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('item_media', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->onUpdate('CASCADE')->onDelete('CASCADE');
        });

        Schema::table('location_user', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('locations')->onUpdate('CASCADE')->onDelete('CASCADE');
            $table->foreign('user_id')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('CASCADE');
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
            $table->dropForeign(['user_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['stock_id']);
            $table->dropForeign(['item_id']);
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('item_reviews', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('item_media', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::table('location_user', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->integer('user_id')->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('order_id')->change();
            $table->integer('stock_id')->change();
            $table->integer('item_id')->nullable()->change();
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->integer('item_id')->change();
        });

        Schema::table('item_reviews', function (Blueprint $table) {
            $table->integer('item_id')->change();
            $table->integer('user_id')->change();
        });

        Schema::table('item_media', function (Blueprint $table) {
            $table->integer('item_id')->nullable()->change();
        });

        Schema::table('item_discounts', function (Blueprint $table) {
            $table->integer('item_id')->change();
        });

        Schema::table('location_user', function (Blueprint $table) {
            $table->integer('location_id')->change();
            $table->integer('user_id')->change();
        });
    }
};
