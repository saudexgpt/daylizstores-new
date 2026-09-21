<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "NO PICKUPS ON THURSDAYS" becomes "NO PICKUPS ON TUESDAYS & THURSDAYS" in the checkout note.
 *
 * The note is the `pickup_warning` row of the settings table (there is no admin screen for it), so it is
 * changed with a migration: it reaches the live database on deploy, exactly once, with no manual SQL.
 * Only that exact phrase is touched (any other wording the owner has written is left alone), and running it
 * again changes nothing, because the new wording no longer contains the old phrase.
 */
return new class extends Migration
{
    private const OLD = '/NO PICKUPS ON THURSDAYS/i';
    private const NEW = 'NO PICKUPS ON TUESDAYS & THURSDAYS';

    public function up()
    {
        $row = DB::table('settings')->where('key', 'pickup_warning')->first();
        if (!$row || !preg_match(self::OLD, (string) $row->value)) {
            return;
        }

        DB::table('settings')->where('id', $row->id)->update(['value' => preg_replace(self::OLD, self::NEW, $row->value)]);
    }

    public function down()
    {
        $row = DB::table('settings')->where('key', 'pickup_warning')->first();
        if ($row && str_contains((string) $row->value, self::NEW)) {
            DB::table('settings')->where('id', $row->id)->update(['value' => str_replace(self::NEW, 'NO PICKUPS ON THURSDAYS', $row->value)]);
        }
    }
};
