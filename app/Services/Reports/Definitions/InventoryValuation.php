<?php

namespace App\Services\Reports\Definitions;

use App\Models\Stock\Category;
use App\Services\Accounting\Ledger;
use App\Services\Costing\CostingSetup;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Illuminate\Support\Facades\DB;

/**
 * What the stock on the shelf is worth at cost, product by size — and whether that agrees with the
 * Inventory account in the books. "Dead stock" is the same list filtered to what has not sold for a while.
 */
class InventoryValuation extends Report
{
    public function key(): string
    {
        return 'inventory-valuation';
    }

    public function title(): string
    {
        return 'Inventory valuation';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function description(): string
    {
        return 'Stock on hand at cost, by product and size — the oldest first, and what has stopped selling.';
    }

    public function note(): ?string
    {
        return 'Value is what is left of each delivery at its landed FIFO cost, as of now. Use "Not sold for" to find slow and dead stock and how much money is tied up in it.';
    }

    public function permissions(): array
    {
        return ['view reports', 'view cost'];
    }

    public function filters(): array
    {
        return [
            Filter::select('category_id', 'Category', Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(), null, ['searchable' => true]),
            Filter::text('q', 'Product name', 'Search products'),
            Filter::number('idle_days', 'Not sold for at least (days)', 0, 0, 3650),
            Filter::select('sort', 'Sort by', Filter::options(['value' => 'Value (highest first)', 'oldest' => 'Oldest stock', 'idle' => 'Longest unsold', 'name' => 'Product name']), 'value'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('name', 'Product'),
            Column::text('size', 'Size', ['optional' => true]),
            Column::text('category', 'Category'),
            Column::int('units', 'Units'),
            Column::money('unit_cost', 'Avg. cost'),
            Column::money('value', 'Value at cost'),
            Column::date('oldest', 'Oldest delivery'),
            Column::date('last_sold', 'Last sold'),
            Column::int('idle_days', 'Days unsold', ['optional' => true]),
        ];
    }

    protected function all(array $f): array
    {
        return $this->remember('all', $f, function () use ($f) {
            $lastSold = DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereNull('oi.deleted_at')->whereNull('o.deleted_at')->where('o.payment_status', 'paid')->where('o.order_status', '!=', 'Cancelled')
                ->groupBy('oi.item_id')->selectRaw('oi.item_id, MAX(o.created_at) as last_sold');

            $q = DB::table('cost_layers as l')
                ->join('items as i', 'i.id', '=', 'l.item_id')
                ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
                ->leftJoinSub($lastSold, 's', 's.item_id', '=', 'l.item_id')
                ->where('l.qty_remaining', '>', 0)
                ->groupBy('l.item_id', 'l.size', 'i.name', 'c.name', 's.last_sold')
                ->selectRaw('l.item_id, i.name, l.size, c.name as category, SUM(l.qty_remaining) as units, SUM(l.cost_remaining) as value, MIN(l.received_on) as oldest, s.last_sold');
            if (!empty($f['category_id'])) {
                $q->where('i.category_id', (int) $f['category_id']);
            }
            if (!empty($f['q'])) {
                $q->where('i.name', 'like', '%' . $f['q'] . '%');
            }

            $today = now()->startOfDay();
            $rows = $q->get()->map(function ($r) use ($today) {
                $sold = $r->last_sold ? \Carbon\Carbon::parse($r->last_sold) : null;
                $units = (int) $r->units;
                $value = (float) $r->value;

                return [
                    'item_id' => (int) $r->item_id, 'name' => $r->name, 'size' => $r->size !== '' ? $r->size : null, 'category' => $r->category ?? 'Uncategorised',
                    'units' => $units, 'unit_cost' => $units ? round($value / $units, 2) : 0.0, 'value' => round($value, 2),
                    'oldest' => $r->oldest, 'last_sold' => $sold ? $sold->toDateString() : null,
                    // never sold since costing began: counted from the oldest delivery instead
                    'idle_days' => (int) abs($today->diffInDays($sold ?: \Carbon\Carbon::parse($r->oldest)->startOfDay())),
                ];
            });

            if (($f['idle_days'] ?? 0) > 0) {
                $rows = $rows->filter(fn ($r) => $r['idle_days'] >= $f['idle_days']);
            }
            $sort = $f['sort'] ?? 'value';
            $rows = $rows->sort(function ($a, $b) use ($sort) {
                $by = match ($sort) {
                    'oldest' => strcmp((string) $a['oldest'], (string) $b['oldest']),
                    'idle' => $b['idle_days'] <=> $a['idle_days'],
                    'name' => strcasecmp($a['name'], $b['name']),
                    default => $b['value'] <=> $a['value'],
                };

                return $by ?: ($a['item_id'] <=> $b['item_id']) ?: strcmp((string) $a['size'], (string) $b['size']);
            });

            return $rows->map(fn ($r) => array_diff_key($r, ['item_id' => 1]))->values()->all();
        });
    }

    public function footer(array $f): ?array
    {
        $rows = $this->all($f);

        return $rows ? [
            'name' => 'Total', 'size' => null, 'category' => null, 'units' => array_sum(array_column($rows, 'units')), 'unit_cost' => null,
            'value' => round(array_sum(array_column($rows, 'value')), 2), 'oldest' => null, 'last_sold' => null, 'idle_days' => null,
        ] : null;
    }

    public function summary(array $f): array
    {
        $rows = $this->all($f);
        $check = app(CostingSetup::class)->reconcile();

        return [
            ['label' => 'Stock at cost', 'value' => round(array_sum(array_column($rows, 'value')), 2), 'type' => 'money'],
            ['label' => 'Units', 'value' => array_sum(array_column($rows, 'units')), 'type' => 'int'],
            ['label' => 'Product sizes', 'value' => count($rows), 'type' => 'int'],
            ['label' => 'Inventory in the books', 'value' => $check['books_value'], 'type' => 'money'],
            ['label' => 'Books and shelf agree?', 'value' => $check['in_step'] ? 'Yes' : 'No — see Accounting overview', 'type' => 'text', 'tone' => $check['in_step'] ? 'good' : 'bad'],
        ];
    }
}
