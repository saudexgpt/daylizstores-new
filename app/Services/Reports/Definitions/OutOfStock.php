<?php

namespace App\Services\Reports\Definitions;

use App\Models\Stock\Category;
use App\Services\Costing\CostingSettings;
use App\Services\Costing\CostLookup;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use App\Services\Reports\RestockList;

/** The restock list as a report, so it can be filtered, exported and printed like the others. */
class OutOfStock extends Report
{
    public function __construct(private RestockList $list)
    {
    }

    public function key(): string
    {
        return 'out-of-stock';
    }

    public function title(): string
    {
        return 'Out of stock / restock list';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function description(): string
    {
        return 'Products that have run out (or are running low), how fast they sell, and how many to reorder.';
    }

    public function note(): ?string
    {
        return 'Suggested restock = the units sold recently, scaled to the days of cover you choose, less what is still on hand.';
    }

    public function filters(): array
    {
        return [
            Filter::select('stock', 'Show', Filter::options(['out' => 'Out of stock', 'low' => 'Low stock', 'both' => 'Out of stock and low']), 'out'),
            Filter::number('threshold', 'Low-stock threshold', 10),
            Filter::select('level', 'Detail', Filter::options(['product' => 'One line per product', 'variant' => 'One line per size / colour']), 'product'),
            Filter::select('enabled', 'Visibility', Filter::options(['active' => 'Active products', 'disabled' => 'Disabled products', 'all' => 'All']), 'active'),
            Filter::select('category_id', 'Category', Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(), null, ['searchable' => true]),
            Filter::text('q', 'Product name', 'Search products'),
            Filter::number('window', 'Sales history (days)', 90, 7, 730),
            Filter::number('cover', 'Days of stock to order', 30, 1, 365),
            Filter::select('sort', 'Sort by', Filter::options(['name' => 'Product name', 'balance' => 'Available', 'sold' => 'Most sold', 'price' => 'Price', 'category' => 'Category']), 'name'),
        ];
    }

    public function columns(array $filters = []): array
    {
        $columns = [
            Column::text('name', 'Product'),
            Column::text('variant', 'Size / colour', ['optional' => true]),
            Column::text('category', 'Category'),
            Column::status('status', 'Status'),
            Column::int('balance', 'Available'),
            Column::int('sold_recent', 'Sold recently'),
            Column::int('suggested_qty', 'Suggested restock'),
            Column::datetime('last_sold', 'Last sold'),
            Column::money('price', 'Price'),
        ];

        // what a restock costs is only for people who may see costs
        if ($this->canSeeCosts()) {
            $columns[] = Column::money('unit_cost', 'Last unit cost');
            $columns[] = Column::money('order_cost', 'Est. cost of suggested order');
        }

        return $columns;
    }

    private function counts(array $f): array
    {
        return $this->remember('counts', $f, fn () => $this->list->summary($f));
    }

    public function page(array $f, int $page, int $perPage): array
    {
        // the summary counts every matching row, so it doubles as the total (one less pass over the stock tables)
        $total = $this->counts($f)['total'];
        $rows = $this->list->query($f)->forPage($page, $perPage)->get()->all();

        return ['rows' => $this->costs($this->present($this->list->enrich($rows, $f))), 'total' => $total];
    }

    public function each(array $f, int $chunk = 1000): \Generator
    {
        $buffer = [];
        foreach ($this->list->each($f, $chunk) as $row) {
            $buffer[] = $this->present([$row])[0];
            if (count($buffer) >= 500) {
                yield from $this->costs($buffer);
                $buffer = [];
            }
        }
        yield from $this->costs($buffer);
    }

    private function present(array $rows): array
    {
        return array_map(fn ($r) => [
            'name' => $r['name'], 'variant' => $r['variant'], 'category' => $r['category'] ?? 'Uncategorised',
            'status' => ['out' => 'Out of stock', 'oversold' => 'Oversold', 'low' => 'Low'][$r['status']],
            'balance' => $r['balance'], 'sold_recent' => $r['sold_recent'], 'suggested_qty' => $r['suggested_qty'],
            'last_sold' => $r['last_sold'], 'price' => $r['price'], 'item_id' => $r['item_id'], 'unit_cost' => null, 'order_cost' => null,
        ], $rows);
    }

    private function canSeeCosts(): bool
    {
        $user = request()->user();

        return CostingSettings::enabled() && $user && $user->can('view cost');
    }

    /** adds what the last delivery cost and what the suggested order would cost — for those who may see costs */
    private function costs(array $rows): array
    {
        if ($rows && $this->canSeeCosts()) {
            $lookup = app(CostLookup::class);
            $costs = $lookup->latest(array_values(array_unique(array_column($rows, 'item_id'))));
            foreach ($rows as &$row) {
                $unit = $lookup->forRow($costs, $row['item_id'], null);
                $row['unit_cost'] = $unit;
                $row['order_cost'] = $unit !== null && $row['suggested_qty'] !== null ? round($unit * $row['suggested_qty'], 2) : null;
            }
            unset($row);
        }

        // everyone loses the helper key; people who may not see costs lose the (empty) cost keys too
        $drop = ['item_id' => 1] + ($this->canSeeCosts() ? [] : ['unit_cost' => 1, 'order_cost' => 1]);

        return array_map(fn ($r) => array_diff_key($r, $drop), $rows);
    }

    public function summary(array $f): array
    {
        $s = $this->counts($f);
        $tiles = [
            ['label' => 'Products listed', 'value' => $s['total'], 'type' => 'int'],
            ['label' => 'Out of stock', 'value' => $s['out'], 'type' => 'int'],
            ['label' => 'Oversold', 'value' => $s['oversold'], 'type' => 'int'],
            ['label' => 'Low stock', 'value' => $s['low'], 'type' => 'int'],
        ];
        if ($this->canSeeCosts()) {
            $cash = 0.0;
            foreach ($this->each($f) as $row) {
                $cash += (float) ($row['order_cost'] ?? 0);
            }
            $tiles[] = ['label' => 'Est. cost of suggested order', 'value' => round($cash, 2), 'type' => 'money'];
        }

        return $tiles;
    }
}
