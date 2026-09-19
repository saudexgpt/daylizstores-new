<?php

namespace App\Services\Costing;

use App\Services\Accounting\AccountingSettings;

/** Whether product costing is live, and the day it went live. */
class CostingSettings
{
    public const ENABLED = 'costing_enabled';
    public const START = 'costing_start_date';

    public static function enabled(): bool
    {
        return AccountingSettings::get(self::ENABLED, '0') === '1';
    }

    /** first day cost of sales is booked (Y-m-d), or null before go-live */
    public static function startDate(): ?string
    {
        return AccountingSettings::get(self::START);
    }
}
