<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Report;
use Illuminate\Support\Facades\DB;

class SalesByCategory extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'sales-by-category';
    }

    public function title(): string
    {
        return 'Sales by category';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Which product categories bring in the most.';
    }

    public function filters(): array
    {
        return $this->orderFilterSpecs();
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('category', 'Category'),
            Column::int('products', 'Products sold'),
            Column::int('units', 'Units sold'),
            Column::int('orders', 'Orders'),
            Column::money('revenue', 'Revenue'),
            Column::percent('share', 'Share of revenue'),
        ];
    }

    protected function all(array $f): array
    {
        return $this->remember('all', $f, fn () => $this->compute($f));
    }

    private function compute(array $f): array
    {
        $rows = $this->applyOrderFilters(DB::table('order_items as oi')->join('orders', 'orders.id', '=', 'oi.order_id'), $f)
            ->whereNull('oi.deleted_at')
            ->leftJoin('items as i', 'i.id', '=', 'oi.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->groupBy('c.id', 'c.name')
            ->selectRaw('c.name as category, COUNT(DISTINCT oi.item_id) as products, SUM(oi.quantity) as units, COUNT(DISTINCT oi.order_id) as orders, SUM(oi.total) as revenue')
            ->orderByDesc('revenue')->orderBy('c.id')
            ->get();

        $total = (float) $rows->sum('revenue');

        return $rows->map(fn ($r) => [
            'category' => $r->category ?? 'Uncategorised',
            'products' => (int) $r->products, 'units' => (int) $r->units, 'orders' => (int) $r->orders,
            'revenue' => $this->money($r->revenue), 'share' => $this->share($r->revenue, $total),
        ])->all();
    }

    public function footer(array $f): ?array
    {
        $rows = $this->all($f);

        return $rows ? [
            'category' => 'Total', 'products' => array_sum(array_column($rows, 'products')),
            'units' => array_sum(array_column($rows, 'units')), 'orders' => null,
            'revenue' => round(array_sum(array_column($rows, 'revenue')), 2), 'share' => 100.0,
        ] : null;
    }

    public function summary(array $f): array
    {
        $rows = $this->all($f);
        $top = $rows[0] ?? null;

        return [
            ['label' => 'Revenue', 'value' => round(array_sum(array_column($rows, 'revenue')), 2), 'type' => 'money'],
            ['label' => 'Categories', 'value' => count($rows), 'type' => 'int'],
            ['label' => 'Top category', 'value' => $top['category'] ?? '—', 'type' => 'text'],
        ];
    }
}
