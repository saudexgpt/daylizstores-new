<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SalesByCustomer extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'sales-by-customer';
    }

    public function title(): string
    {
        return 'Sales by customer';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Who buys the most — orders, total spent and last purchase per customer.';
    }

    public function filters(): array
    {
        return array_merge($this->orderFilterSpecs(), [
            Filter::text('q', 'Customer', 'Name, email or phone'),
            Filter::money('min_amount', 'Spent at least (₦)'),
            Filter::select('sort', 'Sort by', Filter::options(['spent' => 'Total spent', 'orders' => 'Number of orders', 'last_order' => 'Most recent order']), 'spent'),
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('name', 'Customer'),
            Column::text('email', 'Email'),
            Column::text('phone', 'Phone'),
            Column::int('orders', 'Orders'),
            Column::money('spent', 'Total spent'),
            Column::money('average', 'Avg. order'),
            Column::datetime('first_order', 'First order'),
            Column::datetime('last_order', 'Last order'),
        ];
    }

    private function base(array $f): Builder
    {
        $q = $this->applyOrderFilters(DB::table('orders')->join('users as u', 'u.id', '=', 'orders.user_id'), $f);
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.phone', 'like', $like));
        }

        return $q;
    }

    private function aggregate(array $f): Builder
    {
        $q = $this->base($f)
            ->groupBy('u.id', 'u.name', 'u.email', 'u.phone')
            ->selectRaw('u.id as customer_id, u.name, u.email, u.phone, COUNT(*) as orders, SUM(orders.total) as spent, MIN(orders.created_at) as first_order, MAX(orders.created_at) as last_order');
        if (isset($f['min_amount'])) {
            $q->havingRaw('SUM(orders.total) >= ?', [$f['min_amount']]);
        }

        return $q->orderByRaw(match ($f['sort'] ?? 'spent') {
            'orders' => 'orders DESC',
            'last_order' => 'last_order DESC',
            default => 'spent DESC',
        })->orderBy('u.id');
    }

    /** one row per customer who bought — aggregated once (the count, page and totals all come from it) */
    protected function all(array $f): array
    {
        return $this->remember('all', $f, fn () => $this->aggregate($f)->get()->map(fn ($r) => [
            'name' => $r->name, 'email' => $r->email, 'phone' => $r->phone,
            'orders' => (int) $r->orders, 'spent' => $this->money($r->spent),
            'average' => $r->orders ? $this->money($r->spent / $r->orders) : 0.0,
            'first_order' => $r->first_order, 'last_order' => $r->last_order,
        ])->all());
    }

    public function summary(array $f): array
    {
        $rows = $this->all($f);
        $spent = array_sum(array_column($rows, 'spent'));

        return [
            ['label' => 'Customers', 'value' => count($rows), 'type' => 'int'],
            ['label' => 'Orders', 'value' => array_sum(array_column($rows, 'orders')), 'type' => 'int'],
            ['label' => 'Total spent', 'value' => round($spent, 2), 'type' => 'money'],
            ['label' => 'Avg. per customer', 'value' => $rows ? round($spent / count($rows), 2) : 0.0, 'type' => 'money'],
        ];
    }
}
