<?php

namespace App\Services\Reports;

use Illuminate\Validation\Rule;

/**
 * Builders for a report's filter spec. Each spec is sent to the browser (which draws the
 * matching control) and its `rules` are used server-side to validate what comes back, so a
 * filter is declared once and can never drift between the two.
 */
final class Filter
{
    /** two date inputs, sent as `from` and `to` (inclusive) */
    public static function dateRange(string $label = 'Date range', ?string $from = null, ?string $to = null): array
    {
        return [
            'type' => 'date_range', 'key' => 'period', 'label' => $label, 'keys' => ['from', 'to'],
            'default' => ['from' => $from ?? now()->startOfMonth()->toDateString(), 'to' => $to ?? now()->toDateString()],
            'rules' => ['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d']],
        ];
    }

    /** a single date, e.g. "as at" for a balance sheet */
    public static function date(string $key, string $label, ?string $default = null): array
    {
        return [
            'type' => 'date', 'key' => $key, 'label' => $label, 'default' => $default ?? now()->toDateString(),
            'rules' => [$key => ['nullable', 'date_format:Y-m-d']],
        ];
    }

    /**
     * @param array<int,array{value:string|int,label:string}> $options
     * @param array{clearable?:bool,searchable?:bool,required?:bool} $flags
     *   clearable — an empty choice means "no filter" (use for optional filters with no default)
     */
    public static function select(string $key, string $label, array $options, $default = null, array $flags = []): array
    {
        $rules = [($flags['required'] ?? false) ? 'required' : 'nullable', Rule::in(array_column($options, 'value'))];

        return [
            'type' => 'select', 'key' => $key, 'label' => $label, 'options' => $options, 'default' => $default,
            'clearable' => $flags['clearable'] ?? ($default === null), 'searchable' => $flags['searchable'] ?? false,
            'rules' => [$key => $rules],
        ];
    }

    public static function text(string $key, string $label, string $placeholder = ''): array
    {
        return ['type' => 'text', 'key' => $key, 'label' => $label, 'placeholder' => $placeholder, 'default' => null, 'rules' => [$key => ['nullable', 'string', 'max:100']]];
    }

    /** an amount of money (naira, two decimals) */
    public static function money(string $key, string $label): array
    {
        return ['type' => 'money', 'key' => $key, 'label' => $label, 'default' => null, 'rules' => [$key => ['nullable', 'numeric', 'min:0', 'max:99999999999.99']]];
    }

    public static function number(string $key, string $label, int $default, int $min = 1, int $max = 100000): array
    {
        return ['type' => 'number', 'key' => $key, 'label' => $label, 'default' => $default, 'min' => $min, 'max' => $max, 'rules' => [$key => ['nullable', 'integer', "min:$min", "max:$max"]]];
    }

    /** helper: [['value' => 'a', 'label' => 'A'], …] from a value => label map */
    public static function options(array $map): array
    {
        $out = [];
        foreach ($map as $value => $label) {
            $out[] = ['value' => (string) $value, 'label' => $label];
        }

        return $out;
    }
}
