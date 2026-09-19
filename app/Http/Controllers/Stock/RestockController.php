<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Services\Costing\CostingSettings;
use App\Services\Costing\CostLookup;
use App\Services\Reports\CsvExporter;
use App\Services\Reports\RestockList;
use Illuminate\Http\Request;

/**
 * "What do I need to restock?" — products out of stock (or running low) with their sales rate
 * and a suggested quantity. Behind `create menu`, like the rest of product management.
 */
class RestockController extends Controller
{
    public function index(Request $request, RestockList $list)
    {
        $filters = $request->validate([
            'stock' => ['nullable', 'in:out,low,both'],
            'threshold' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'level' => ['nullable', 'in:product,variant'],
            'enabled' => ['nullable', 'in:active,disabled,all'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'q' => ['nullable', 'string', 'max:100'],
            'window' => ['nullable', 'integer', 'min:7', 'max:730'],
            'cover' => ['nullable', 'integer', 'min:1', 'max:365'],
            'sort' => ['nullable', 'in:name,balance,sold,price,category'],
            'direction' => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'format' => ['nullable', 'in:csv'],
        ]);

        // what a restock would cost is shown only to people who may see costs, and only once costing is live
        $withCost = $request->user()->can('view cost') && CostingSettings::enabled();

        if (($filters['format'] ?? null) === 'csv') {
            return $withCost
                ? CsvExporter::download('restock-list', self::CSV_COLUMNS + self::CSV_COST_COLUMNS, $this->costed($list->each($filters)))
                : CsvExporter::download('restock-list', self::CSV_COLUMNS, $list->each($filters));
        }

        $perPage = (int) ($filters['per_page'] ?? 25);
        $page = (int) ($filters['page'] ?? 1);

        // the summary already counts every matching row, so it doubles as the pager's total (one less pass over the stock tables)
        $summary = $list->summary($filters);
        $rows = $list->enrich($list->query($filters)->forPage($page, $perPage)->get()->all(), $filters);
        if ($withCost) {
            $rows = iterator_to_array($this->costed($rows), false);
        }

        return response()->json([
            'rows' => $rows,
            'summary' => $summary,
            'filters' => $list->normalise($filters),
            'pagination' => ['total' => $summary['total'], 'page' => $page, 'per_page' => $perPage, 'last_page' => max((int) ceil($summary['total'] / $perPage), 1)],
        ]);
    }

    /** adds unit_cost (the last delivery's cost) and order_cost (suggested quantity × that) to rows, looked up in batches */
    private function costed(iterable $rows): \Generator
    {
        $lookup = app(CostLookup::class);
        foreach (array_chunk(is_array($rows) ? $rows : iterator_to_array($rows, false), 500) as $chunk) {
            $costs = $lookup->latest(array_values(array_unique(array_column($chunk, 'item_id'))));
            foreach ($chunk as $row) {
                $unit = $lookup->forRow($costs, $row['item_id'], null);
                $row['unit_cost'] = $unit;
                $row['order_cost'] = $unit !== null && $row['suggested_qty'] !== null ? round($unit * $row['suggested_qty'], 2) : null;
                yield $row;
            }
        }
    }

    public const CSV_COST_COLUMNS = ['unit_cost' => 'Last unit cost', 'order_cost' => 'Est. cost of suggested restock'];

    public const CSV_COLUMNS = [
        'name' => 'Product', 'variant' => 'Size / colour', 'category' => 'Category', 'status' => 'Status',
        'stocked' => 'Total stocked', 'reserved' => 'Reserved', 'sold' => 'Sold (all time)', 'balance' => 'Available',
        'sold_recent' => 'Sold (recent window)', 'suggested_qty' => 'Suggested restock', 'price' => 'Price',
        'last_sold' => 'Last sold', 'enabled' => 'Active',
    ];
}
