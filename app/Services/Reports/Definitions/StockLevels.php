<?php

namespace App\Services\Reports\Definitions;

use App\Models\Stock\Category;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use App\Services\Reports\RestockList;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class StockLevels extends Report
{
    public function __construct(private RestockList $list)
    {
    }

    public function key(): string
    {
        return 'stock-levels';
    }

    public function title(): string
    {
        return 'Stock levels';
    }

    public function group(): string
    {
        return 'Inventory';
    }

    public function description(): string
    {
        return 'Every product with what was stocked, reserved, sold and what is still available.';
    }

    public function note(): ?string
    {
        return 'Available = stocked − reserved − sold. Retail value is available units at the current selling price (not cost — product costs are not recorded).';
    }

    public function filters(): array
    {
        return [
            Filter::select('stock', 'Stock', Filter::options(['all' => 'All products', 'in' => 'In stock', 'low' => 'Low stock', 'out' => 'Out of stock']), 'all'),
            Filter::number('threshold', 'Low-stock threshold', 10),
            Filter::select('level', 'Detail', Filter::options(['product' => 'One line per product', 'variant' => 'One line per size / colour']), 'product'),
            Filter::select('enabled', 'Visibility', Filter::options(['active' => 'Active products', 'disabled' => 'Disabled products', 'all' => 'All']), 'active'),
            Filter::select('category_id', 'Category', Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => (string) $c->id, 'label' => $c->name])->all(), null, ['searchable' => true]),
            Filter::text('q', 'Product name', 'Search products'),
            Filter::select('sort', 'Sort by', Filter::options(['name' => 'Product name', 'balance' => 'Available (lowest first)', 'sold' => 'Most sold', 'price' => 'Price', 'category' => 'Category']), 'name'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('name', 'Product'),
            Column::text('variant', 'Size / colour', ['optional' => true]),
            Column::text('category', 'Category'),
            Column::status('status', 'Status'),
            Column::int('stocked', 'Stocked'),
            Column::int('reserved', 'Reserved'),
            Column::int('sold', 'Sold'),
            Column::int('balance', 'Available'),
            Column::money('price', 'Price'),
            Column::money('value', 'Retail value'),
        ];
    }

    public function query(array $f): ?Builder
    {
        return $this->list->query($f);
    }

    protected function shape($r): array
    {
        $balance = (int) $r->balance;
        $price = $r->price !== null ? (float) $r->price : null;

        return [
            'name' => $r->name, 'variant' => $r->variant ?: null, 'category' => $r->category ?? 'Uncategorised',
            'status' => !$r->enabled ? 'Disabled' : ($balance < 0 ? 'Oversold' : ($balance === 0 ? 'Out of stock' : ($balance <= ($this->threshold ?? 10) ? 'Low' : 'In stock'))),
            'stocked' => (int) $r->stocked, 'reserved' => (int) $r->reserved, 'sold' => (int) $r->sold, 'balance' => $balance,
            'price' => $price, 'value' => $price !== null ? round(max($balance, 0) * $price, 2) : null,
        ];
    }

    private ?int $threshold = null;

    protected function shapeRows(array $rows, array $f): array
    {
        $this->threshold = (int) $this->list->normalise($f)['threshold'];

        return parent::shapeRows($rows, $f);
    }

    public function summary(array $f): array
    {
        $t = DB::query()->fromSub($this->query($f)->reorder(), 't')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(balance <= 0),0) as out_n, COALESCE(SUM(GREATEST(balance,0)),0) as units, COALESCE(SUM(GREATEST(balance,0) * COALESCE(price,0)),0) as value')->first();

        return [
            ['label' => 'Products listed', 'value' => (int) $t->n, 'type' => 'int'],
            ['label' => 'Out of stock', 'value' => (int) $t->out_n, 'type' => 'int'],
            ['label' => 'Units available', 'value' => (int) $t->units, 'type' => 'int'],
            ['label' => 'Retail value of stock', 'value' => round((float) $t->value, 2), 'type' => 'money'],
        ];
    }
}
