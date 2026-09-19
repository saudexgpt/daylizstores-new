<?php

namespace App\Services\Reports\Definitions;

use App\Models\Stock\Category;
use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What each product actually earned: sales less the FIFO cost frozen on the order line at dispatch.
 * Only lines that have been costed are in the table; the summary says how much of the period's
 * sales that covers, so nobody reads a margin built on half the sales as the whole picture.
 */
class GrossMargin extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'gross-margin';
    }

    public function title(): string
    {
        return 'Gross margin';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Sales, cost and profit per product, size or category — what each one really earned.';
    }

    public function note(): ?string
    {
        return 'Cost is the FIFO cost of the goods, frozen when the order was dispatched. Only costed sales are listed; the "not costed" figure shows sales from before costing went live or not yet dispatched.';
    }

    public function permissions(): array
    {
        return ['view reports', 'view cost'];
    }

    public function filters(): array
    {
        return array_merge($this->orderFilterSpecs(), [
            Filter::select('group_by', 'Group by', Filter::options(['product' => 'Product', 'size' => 'Product and size', 'category' => 'Category']), 'product'),
            Filter::select('category_id', 'Category', Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(), null, ['searchable' => true]),
            Filter::text('q', 'Product name', 'Search products'),
            Filter::select('sort', 'Sort by', Filter::options(['margin' => 'Profit', 'margin_pct' => 'Margin %', 'revenue' => 'Sales', 'units' => 'Units sold', 'name' => 'Name']), 'margin'),
        ]);
    }

    public function columns(array $filters = []): array
    {
        $group = $filters['group_by'] ?? 'product';

        return array_values(array_filter([
            Column::text('name', $group === 'category' ? 'Category' : 'Product'),
            $group === 'size' ? Column::text('size', 'Size') : null,
            $group === 'category' ? null : Column::text('category', 'Category'),
            Column::int('units', 'Units sold'),
            Column::money('revenue', 'Sales'),
            Column::money('cost', 'Cost'),
            Column::money('margin', 'Profit'),
            Column::percent('margin_pct', 'Margin'),
        ]));
    }

    private function base(array $f): Builder
    {
        $q = $this->applyOrderFilters(DB::table('order_items as oi')->join('orders', 'orders.id', '=', 'oi.order_id'), $f)
            ->whereNull('oi.deleted_at')
            ->leftJoin('items as i', 'i.id', '=', 'oi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_stocks as s', 's.id', '=', 'oi.stock_id');
        if (!empty($f['category_id'])) {
            $q->where('i.category_id', (int) $f['category_id']);
        }
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('oi.product_name', 'like', '%' . $f['q'] . '%')->orWhere('i.name', 'like', '%' . $f['q'] . '%'));
        }

        return $q;
    }

    protected function all(array $f): array
    {
        return $this->remember('all', $f, function () use ($f) {
            $group = $f['group_by'] ?? 'product';
            $q = $this->base($f)->whereNotNull('oi.costed_at');
            $sizeExpr = "COALESCE(TRIM(s.size), '')";

            match ($group) {
                'category' => $q->groupBy('c.id', 'c.name')->selectRaw('0 as item_id, COALESCE(c.name, \'Uncategorised\') as name, NULL as category, \'\' as size'),
                'size' => $q->groupBy('oi.item_id', 'c.name', DB::raw($sizeExpr))->selectRaw("oi.item_id, COALESCE(MAX(i.name), MAX(oi.product_name)) as name, c.name as category, $sizeExpr as size"),
                default => $q->groupBy('oi.item_id', 'c.name')->selectRaw("oi.item_id, COALESCE(MAX(i.name), MAX(oi.product_name)) as name, c.name as category, '' as size"),
            };
            $rows = $q->selectRaw('SUM(oi.quantity) as units, SUM(oi.total) as revenue, SUM(oi.cost_total) as cost')->get()->map(function ($r) use ($group) {
                $revenue = (float) $r->revenue;
                $cost = (float) $r->cost;

                return [
                    'item_id' => (int) $r->item_id, 'name' => $r->name, 'size' => $r->size !== '' ? $r->size : null,
                    'category' => $group === 'category' ? null : ($r->category ?? 'Uncategorised'),
                    'units' => (int) $r->units, 'revenue' => $this->money($revenue), 'cost' => $this->money($cost),
                    'margin' => $this->money($revenue - $cost), 'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
                ];
            })->all();

            $sort = $f['sort'] ?? 'margin';
            usort($rows, function ($a, $b) use ($sort) {
                $by = match ($sort) {
                    'margin_pct' => ($b['margin_pct'] ?? -INF) <=> ($a['margin_pct'] ?? -INF),
                    'revenue' => $b['revenue'] <=> $a['revenue'],
                    'units' => $b['units'] <=> $a['units'],
                    'name' => strcasecmp((string) $a['name'], (string) $b['name']),
                    default => $b['margin'] <=> $a['margin'],
                };

                return $by ?: ($a['item_id'] <=> $b['item_id']) ?: strcmp((string) $a['size'], (string) $b['size']);
            });

            return array_map(fn ($r) => array_diff_key($r, ['item_id' => 1]), $rows);
        });
    }

    public function footer(array $f): ?array
    {
        $rows = $this->all($f);
        if (!$rows) {
            return null;
        }
        $revenue = round(array_sum(array_column($rows, 'revenue')), 2);
        $cost = round(array_sum(array_column($rows, 'cost')), 2);

        return [
            'name' => 'Total', 'size' => null, 'category' => null, 'units' => array_sum(array_column($rows, 'units')),
            'revenue' => $revenue, 'cost' => $cost, 'margin' => round($revenue - $cost, 2), 'margin_pct' => $revenue > 0 ? round(($revenue - $cost) / $revenue * 100, 1) : null,
        ];
    }

    public function summary(array $f): array
    {
        $t = $this->footer($f) ?? ['revenue' => 0, 'cost' => 0, 'margin' => 0, 'margin_pct' => null];
        $uncosted = (float) $this->base($f)->whereNull('oi.costed_at')->sum('oi.total');

        return [
            ['label' => 'Sales (costed)', 'value' => $t['revenue'], 'type' => 'money'],
            ['label' => 'Cost of goods', 'value' => $t['cost'], 'type' => 'money'],
            ['label' => $t['margin'] >= 0 ? 'Gross profit' : 'Gross loss', 'value' => abs($t['margin']), 'type' => 'money', 'tone' => $t['margin'] >= 0 ? 'good' : 'bad'],
            ['label' => 'Margin', 'value' => $t['margin_pct'], 'type' => 'percent'],
            ['label' => 'Sales not costed yet', 'value' => round($uncosted, 2), 'type' => 'money', 'tone' => $uncosted > 0 ? 'bad' : null],
        ];
    }
}
