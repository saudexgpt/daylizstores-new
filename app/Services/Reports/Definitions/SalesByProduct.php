<?php

namespace App\Services\Reports\Definitions;

use App\Models\Stock\Category;
use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesByProduct extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'sales-by-product';
    }

    public function title(): string
    {
        return 'Sales by product';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'What sold, how many, and how much it earned — best sellers first.';
    }

    public function note(): ?string
    {
        return 'Revenue is the product line total (price × quantity), so delivery charges are not included.';
    }

    public function filters(): array
    {
        return array_merge($this->orderFilterSpecs(), [
            Filter::select('category_id', 'Category', Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(), null, ['searchable' => true]),
            Filter::text('q', 'Product name', 'Search products'),
            Filter::select('sort', 'Sort by', Filter::options(['revenue' => 'Revenue', 'units' => 'Units sold', 'name' => 'Product name']), 'revenue'),
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('product', 'Product'),
            Column::text('category', 'Category'),
            Column::int('units', 'Units sold'),
            Column::int('orders', 'Orders'),
            Column::money('revenue', 'Revenue'),
            Column::money('average_price', 'Avg. price'),
            Column::percent('share', 'Share of revenue'),
        ];
    }

    private function base(array $f): Builder
    {
        $q = $this->applyOrderFilters(DB::table('order_items as oi')->join('orders', 'orders.id', '=', 'oi.order_id'), $f)
            ->whereNull('oi.deleted_at')
            ->leftJoin('items as i', 'i.id', '=', 'oi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id');
        if (!empty($f['category_id'])) {
            $q->where('i.category_id', (int) $f['category_id']);
        }
        if (!empty($f['q'])) {
            $q->where(fn ($w) => $w->where('oi.product_name', 'like', '%' . $f['q'] . '%')->orWhere('i.name', 'like', '%' . $f['q'] . '%'));
        }

        return $q;
    }

    private function aggregate(array $f): Builder
    {
        return $this->base($f)
            ->groupBy('oi.item_id', 'c.id', 'c.name')
            ->selectRaw('oi.item_id, COALESCE(MAX(i.name), MAX(oi.product_name)) as product, c.name as category, SUM(oi.quantity) as units, COUNT(DISTINCT oi.order_id) as orders, SUM(oi.total) as revenue');
    }

    /**
     * One row per product sold — bounded by the size of the catalogue, so it is aggregated once
     * and sorted here rather than re-aggregated for the count, the page and the totals.
     */
    protected function all(array $f): array
    {
        return $this->remember('all', $f, function () use ($f) {
            $rows = $this->aggregate($f)->get()->all();
            $total = array_sum(array_map(fn ($r) => (float) $r->revenue, $rows));

            $sort = $f['sort'] ?? 'revenue';
            usort($rows, function ($a, $b) use ($sort) {
                $by = match ($sort) {
                    'units' => (int) $b->units <=> (int) $a->units,
                    'name' => strcasecmp($a->product, $b->product),
                    default => (float) $b->revenue <=> (float) $a->revenue,
                };

                return $by ?: $a->item_id <=> $b->item_id;
            });

            return array_map(fn ($r) => [
                'product' => $r->product, 'category' => $r->category ?? 'Uncategorised',
                'units' => (int) $r->units, 'orders' => (int) $r->orders,
                'revenue' => $this->money($r->revenue),
                'average_price' => $r->units ? $this->money($r->revenue / $r->units) : 0.0,
                'share' => $this->share($r->revenue, $total),
            ], $rows);
        });
    }

    public function summary(array $f): array
    {
        $rows = $this->all($f);

        return [
            ['label' => 'Revenue', 'value' => round(array_sum(array_column($rows, 'revenue')), 2), 'type' => 'money'],
            ['label' => 'Units sold', 'value' => array_sum(array_column($rows, 'units')), 'type' => 'int'],
            ['label' => 'Products sold', 'value' => count($rows), 'type' => 'int'],
        ];
    }
}
