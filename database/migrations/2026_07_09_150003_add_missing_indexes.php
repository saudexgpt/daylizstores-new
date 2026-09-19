<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** [table => columns that should be indexed] */
    private const INDEXES = [
        'orders' => ['order_status', 'payment_status', 'valid_till'],
        'users' => ['role'],
        'order_items' => ['total_updated'],
        'items' => ['enabled'],
    ];

    /**
     * Run the migrations.
     *
     * A column that is already the leading column of some index is skipped: the live
     * database already has indexes on orders.order_status / payment_status (created by an
     * older migration under different names), and duplicating them would only slow down
     * writes on a 150k-row table.
     *
     * @return void
     */
    public function up()
    {
        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                if ($this->isIndexed($table, $column)) {
                    continue;
                }
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->index($column);
                });
            }
        }
    }

    /**
     * Reverse the migrations. Only the indexes this migration would have created (under
     * Laravel's default names) are dropped — never the pre-existing ones it skipped.
     *
     * @return void
     */
    public function down()
    {
        foreach (self::INDEXES as $table => $columns) {
            foreach ($columns as $column) {
                $name = "{$table}_{$column}_index";
                if ($this->hasIndexNamed($table, $name)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($name) {
                        $blueprint->dropIndex($name);
                    });
                }
            }
        }
    }

    private function isIndexed(string $table, string $column): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'][0] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private function hasIndexNamed(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $name) {
                return true;
            }
        }

        return false;
    }
};
