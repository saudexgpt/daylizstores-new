<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\CsvExporter;
use App\Services\Reports\Report;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;

/**
 * One generic endpoint set for every report in the registry:
 *   GET reports/catalog            what this user may run, with each report's filters and columns
 *   GET reports/run/{key}          a page of rows + headline figures, for the screen
 *   GET reports/export/{key}       ?format=csv (streamed, any size) or ?format=json (for the Excel export)
 * The route guard admits either "view reports" or "view accounting"; each report then decides for itself
 * (sales, stock and customer reports need "view reports"; the financial statements need "view accounting").
 */
class ReportCenterController extends Controller
{
    public function catalog(Request $request, ReportRegistry $registry)
    {
        $reports = $registry->visibleTo($request->user())->map(fn (Report $r) => $r->describe())->values();

        return response()->json(['reports' => $reports, 'groups' => $reports->pluck('group')->unique()->values()]);
    }

    public function run(Request $request, string $key, ReportRegistry $registry)
    {
        $report = $this->locate($request, $key, $registry);
        $f = $report->resolve($request->query());
        $paging = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:200']]);
        $page = (int) ($paging['page'] ?? 1);
        $perPage = (int) ($paging['per_page'] ?? 50);

        $report->prepare($f);
        $data = $report->page($f, $page, $perPage);

        return response()->json([
            'key' => $report->key(), 'title' => $report->title(),
            'filters' => $f, 'columns' => $report->columns($f),
            'rows' => $data['rows'], 'summary' => $report->summary($f), 'footer' => $report->footer($f),
            'pagination' => ['total' => $data['total'], 'page' => $page, 'per_page' => $perPage, 'last_page' => max((int) ceil($data['total'] / $perPage), 1)],
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request, string $key, ReportRegistry $registry)
    {
        $report = $this->locate($request, $key, $registry);
        $f = $report->resolve($request->query());
        $format = $request->validate(['format' => ['required', 'in:csv,json']])['format'];

        $report->prepare($f);
        $columns = $report->columns($f);

        if ($format === 'csv') {
            @set_time_limit(0);
            $rows = (function () use ($report, $f) {
                yield from $report->each($f);
                if ($footer = $report->footer($f)) {
                    yield $footer;
                }
            })();

            return CsvExporter::download($this->filename($report, $f), array_column($columns, 'label', 'key'), $rows);
        }

        // Excel is assembled in the browser, so it has a ceiling; bigger exports should use CSV
        $total = $report->page($f, 1, 1)['total'];
        $limit = (int) config('reports.excel_limit', Report::EXCEL_ROW_LIMIT);
        if ($total > $limit) {
            return response()->json(['message' => 'This report has ' . number_format($total) . ' rows, which is too many for Excel. Download the CSV instead, or narrow the filters.'], 422);
        }

        return response()->json([
            'key' => $report->key(), 'title' => $report->title(), 'filters' => $f, 'columns' => $columns,
            'rows' => iterator_to_array($report->each($f), false), 'summary' => $report->summary($f), 'footer' => $report->footer($f),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    private function locate(Request $request, string $key, ReportRegistry $registry): Report
    {
        $report = $registry->find($key);
        abort_if(!$report, 404, 'That report does not exist.');
        abort_unless($report->allowedFor($request->user()), 403, 'You do not have access to this report.');

        return $report;
    }

    private function filename(Report $report, array $f): string
    {
        $range = isset($f['from']) ? "_{$f['from']}_to_{$f['to']}" : (isset($f['as_at']) ? "_as-at-{$f['as_at']}" : '');

        return $report->key() . $range;
    }
}
