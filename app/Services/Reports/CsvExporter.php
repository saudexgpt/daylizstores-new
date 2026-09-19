<?php

namespace App\Services\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download row by row (never building the whole file in memory) in a form
 * Excel opens correctly: UTF-8 with a BOM, so ₦ and accents survive.
 */
class CsvExporter
{
    /**
     * @param string                $filename base name; the date and .csv are added
     * @param array<string,string>  $columns  key => header label, in output order
     * @param iterable              $rows     arrays (or objects) keyed by column key
     */
    public static function download(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));
            foreach ($rows as $row) {
                $row = (array) $row;
                $line = [];
                foreach (array_keys($columns) as $key) {
                    $line[] = self::cell($row[$key] ?? '');
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Spreadsheet apps run cells that START with = + - @ (or a tab / carriage return) as
     * formulas. Names in these reports are typed by customers and staff, so any text like
     * that is prefixed with an apostrophe; real numbers (incl. negatives) are left alone.
     */
    public static function cell($value)
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }
        $text = (string) $value;
        if ($text !== '' && !is_numeric($text) && preg_match('/^[=+\-@\t\r]/', $text)) {
            return "'" . $text;
        }

        return $text;
    }
}
