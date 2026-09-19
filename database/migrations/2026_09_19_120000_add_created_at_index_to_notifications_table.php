<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit trail lists notifications for a date range, newest first
 * (`WHERE created_at BETWEEN ... ORDER BY created_at DESC`). The table only
 * had indexes on the notifiable columns, so with ~350k rows every page load
 * scanned and sorted the whole table (~4 seconds).
 */
class AddCreatedAtIndexToNotificationsTable extends Migration
{
    public function up()
    {
        // (skipped if some index already leads with created_at)
        foreach (Schema::getIndexes('notifications') as $index) {
            if (($index['columns'][0] ?? null) === 'created_at') {
                return;
            }
        }
        Schema::table('notifications', function (Blueprint $table) {
            $table->index('created_at', 'notifications_created_at_index');
        });
    }

    public function down()
    {
        foreach (Schema::getIndexes('notifications') as $index) {
            if ($index['name'] === 'notifications_created_at_index') {
                Schema::table('notifications', function (Blueprint $table) {
                    $table->dropIndex('notifications_created_at_index');
                });
            }
        }
    }
}
