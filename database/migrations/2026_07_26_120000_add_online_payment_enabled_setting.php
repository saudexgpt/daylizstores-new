<?php

use App\Models\Setting\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Disabled by default — online payments (Paystack) are being turned
        // off for now, leaving bank transfer + receipt upload as the only
        // active checkout path. A plain settings-table flag so it can be
        // switched back on later without a code change.
        // Setting has no $fillable (fully guarded), so it's set via direct
        // property assignment rather than mass assignment.
        if (!Setting::where('key', 'online_payment_enabled')->exists()) {
            $setting = new Setting();
            $setting->key = 'online_payment_enabled';
            $setting->value = 'false';
            $setting->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Setting::where('key', 'online_payment_enabled')->delete();
    }
};
