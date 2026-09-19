<?php

namespace App\Services\Accounting;

use App\Models\Setting\Setting;
use Carbon\Carbon;

/**
 * The few accounting settings, stored (as text) in the existing `settings` table.
 */
class AccountingSettings
{
    public const START = 'books_start_date';
    public const CLOSED = 'books_closed_through';
    public const DEPOSIT = 'sales_deposit_account';
    public const LAST_SYNC = 'last_sales_sync';

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Setting::where('key', $key)->value('value');

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        // Setting has no $fillable (fully guarded) — assign properties directly
        $setting = Setting::where('key', $key)->first() ?: new Setting();
        $setting->key = $key;
        $setting->value = $value ?? '';
        $setting->save();
    }

    /** first day included in the books (Y-m-d) */
    public static function booksStart(): string
    {
        return self::get(self::START, Carbon::now()->startOfYear()->toDateString());
    }

    /** entries dated on or before this day are locked (Y-m-d), or null when nothing is closed */
    public static function closedThrough(): ?string
    {
        return self::get(self::CLOSED);
    }

    /** chart code of the account customer transfers are received into */
    public static function depositAccountCode(): string
    {
        return self::get(self::DEPOSIT, '1010');
    }
}
