<?php

namespace App\Services\Reports;

/**
 * Column specs. The `type` tells the screen how to format a value (money, whole number, percent,
 * date, status badge) and the Excel export how to store it; CSV writes the raw value.
 */
final class Column
{
    public static function text(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'text', $extra);
    }

    public static function money(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'money', $extra + ['align' => 'right']);
    }

    public static function int(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'int', $extra + ['align' => 'right']);
    }

    public static function percent(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'percent', $extra + ['align' => 'right']);
    }

    public static function date(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'date', $extra);
    }

    public static function datetime(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'datetime', $extra);
    }

    public static function status(string $key, string $label, array $extra = []): array
    {
        return self::make($key, $label, 'status', $extra);
    }

    /** `optional` columns are hidden on screen when no row has a value for them */
    private static function make(string $key, string $label, string $type, array $extra): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type] + $extra;
    }
}
