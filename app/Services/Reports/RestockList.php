<?php

namespace App\Services\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Products (or size/colour variants) that are out of stock or running low, with how fast they
 * sell, so restocking can be decided from one table.
 *
 * "Balance" is what can actually be sold: stocked - reserved - sold, summed over every
 * size/colour row. A product with no stock rows at all has a balance of 0. A negative balance
 * means more was sold/reserved than was ever stocked ("oversold").
 */
class RestockList
{
    public const DEFAULTS = [
        'stock' => 'out',        // out | low | both (reports also use: in | all)
        'threshold' => 10,       // "low" means a balance above 0 and up to this
        'level' => 'product',    // product | variant
        'enabled' => 'active',   // active | disabled | all
        'window' => 90,          // days of sales history used for the sales rate
        'cover' => 30,           // days of sales a restock should cover
        'sort' => 'name',
        'direction' => 'asc',
    ];

    public function normalise(array $filters): array
    {
        $f = array_merge(self::DEFAULTS, array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        $f['threshold'] = max(1, min((int) $f['threshold'], 100000));
        $f['window'] = max(7, min((int) $f['window'], 730));
        $f['cover'] = max(1, min((int) $f['cover'], 365));
        $f['stock'] = in_array($f['stock'], ['out', 'low', 'both', 'in', 'all'], true) ? $f['stock'] : 'out';
        $f['level'] = $f['level'] === 'variant' ? 'variant' : 'product';
        $f['enabled'] = in_array($f['enabled'], ['active', 'disabled', 'all'], true) ? $f['enabled'] : 'active';
        $f['direction'] = strtolower($f['direction']) === 'desc' ? 'desc' : 'asc';

        return $f;
    }

    public function query(array $f): Builder
    {
        $f = $this->normalise($f);

        return $f['level'] === 'variant' ? $this->variantQuery($f) : $this->productQuery($f);
    }

    private function productQuery(array $f): Builder
    {
        $q = DB::table('items as i')
            ->leftJoin('item_stocks as s', function ($j) {
                $j->on('s.item_id', '=', 'i.id')->whereNull('s.deleted_at');
            })
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_prices as p', 'p.item_id', '=', 'i.id')
            ->whereNull('i.deleted_at')
            ->groupBy('i.id', 'i.name', 'i.enabled', 'c.id', 'c.name')
            ->selectRaw(
                'i.id as item_id, NULL as stock_id, i.name, i.enabled, c.id as category_id, c.name as category, NULL as variant, '
                . 'MAX(p.amount) as price, COALESCE(SUM(s.quantity_stocked),0) as stocked, COALESCE(SUM(s.reserved),0) as reserved, '
                . 'COALESCE(SUM(s.sold),0) as sold, COALESCE(SUM(s.quantity_stocked - s.reserved - s.sold),0) as balance, COUNT(s.id) as variants'
            );
        $this->commonFilters($q, $f);

        $this->having($q, $f, 'balance');

        return $this->sorted($q, $f, ['name' => 'i.name', 'balance' => 'balance', 'sold' => 'sold', 'price' => 'price', 'category' => 'c.name']);
    }

    private function variantQuery(array $f): Builder
    {
        $q = DB::table('item_stocks as s')
            ->join('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('item_prices as p', 'p.item_id', '=', 'i.id')
            ->whereNull('s.deleted_at')->whereNull('i.deleted_at')
            ->selectRaw(
                'i.id as item_id, s.id as stock_id, i.name, i.enabled, c.id as category_id, c.name as category, '
                . "TRIM(CONCAT_WS(' / ', NULLIF(s.color,''), NULLIF(s.size,''))) as variant, p.amount as price, "
                . 's.quantity_stocked as stocked, s.reserved, s.sold, (s.quantity_stocked - s.reserved - s.sold) as balance, 1 as variants'
            );
        $this->commonFilters($q, $f);

        $expr = '(s.quantity_stocked - s.reserved - s.sold)';
        match ($f['stock']) {
            'low' => $q->whereRaw("$expr > 0 AND $expr <= ?", [$f['threshold']]),
            'both' => $q->whereRaw("$expr <= ?", [$f['threshold']]),
            'in' => $q->whereRaw("$expr > 0"),
            'all' => null,
            default => $q->whereRaw("$expr <= 0"),
        };

        return $this->sorted($q, $f, ['name' => 'i.name', 'balance' => 'balance', 'sold' => 's.sold', 'price' => 'p.amount', 'category' => 'c.name']);
    }

    private function commonFilters(Builder $q, array $f): void
    {
        if ($f['enabled'] === 'active') {
            $q->where('i.enabled', 1);
        } elseif ($f['enabled'] === 'disabled') {
            $q->where('i.enabled', 0);
        }
        if (!empty($f['category_id'])) {
            $q->where('i.category_id', (int) $f['category_id']);
        }
        if (!empty($f['q'])) {
            $q->where('i.name', 'like', '%' . $f['q'] . '%');
        }
    }

    private function having(Builder $q, array $f, string $alias): void
    {
        match ($f['stock']) {
            'low' => $q->havingRaw("$alias > 0 AND $alias <= ?", [$f['threshold']]),
            'both' => $q->havingRaw("$alias <= ?", [$f['threshold']]),
            'in' => $q->havingRaw("$alias > 0"),
            'all' => null,
            default => $q->havingRaw("$alias <= 0"),
        };
    }

    private function sorted(Builder $q, array $f, array $columns): Builder
    {
        $column = $columns[$f['sort']] ?? $columns['name'];

        // a stable tiebreaker, so paging and exports never repeat or skip a row
        return $q->orderBy(DB::raw($column), $f['direction'])->orderBy('i.id')->when($f['level'] === 'variant', fn ($b) => $b->orderBy('s.id'));
    }

    /**
     * How fast each row sells, and how much to reorder: adds sold_recent, last_sold and
     * suggested_qty (the sales rate over `window` days, scaled to `cover` days; null when
     * nothing sold in the window). Only the rows passed in are looked up.
     */
    public function enrich(iterable $rows, array $f): array
    {
        $f = $this->normalise($f);
        $rows = collect($rows)->map(fn ($r) => (array) $r)->values();
        if ($rows->isEmpty()) {
            return [];
        }

        $variant = $f['level'] === 'variant';
        $keyColumn = $variant ? 'oi.stock_id' : 'oi.item_id';
        $ids = $rows->pluck($variant ? 'stock_id' : 'item_id')->all();
        $since = now()->subDays($f['window'])->startOfDay();

        $sales = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn($keyColumn, $ids)
            ->where('o.payment_status', 'paid')->where('o.order_status', '!=', 'Cancelled')
            ->whereNull('o.deleted_at')->whereNull('oi.deleted_at')
            ->groupBy($keyColumn)
            ->selectRaw("$keyColumn as k, SUM(CASE WHEN o.created_at >= ? THEN oi.quantity ELSE 0 END) as recent, MAX(o.created_at) as last_sold", [$since])
            ->get()->keyBy('k');

        return $rows->map(function (array $r) use ($sales, $variant, $f) {
            $s = $sales->get($variant ? $r['stock_id'] : $r['item_id']);
            $recent = (int) ($s->recent ?? 0);
            $balance = (int) $r['balance'];

            return [
                'item_id' => (int) $r['item_id'],
                'stock_id' => $r['stock_id'] !== null ? (int) $r['stock_id'] : null,
                'name' => $r['name'],
                'variant' => $r['variant'] ?: null,
                'category' => $r['category'],
                'enabled' => (bool) $r['enabled'],
                'price' => $r['price'] !== null ? (float) $r['price'] : null,
                'variants' => (int) $r['variants'],
                'stocked' => (int) $r['stocked'],
                'reserved' => (int) $r['reserved'],
                'sold' => (int) $r['sold'],
                'balance' => $balance,
                'status' => $balance < 0 ? 'oversold' : ($balance === 0 ? 'out' : 'low'),
                'sold_recent' => $recent,
                'last_sold' => $s->last_sold ?? null,
                // enough to cover `cover` days at the recent rate, on top of clearing any oversell
                'suggested_qty' => $recent > 0 ? max((int) ceil($recent / $f['window'] * $f['cover']) - max($balance, 0), 0) : null,
            ];
        })->all();
    }

    /** counts for exactly the rows the filters match */
    public function summary(array $f): array
    {
        $row = DB::query()->fromSub($this->query($f)->reorder(), 't')
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(balance < 0),0) as oversold, COALESCE(SUM(balance = 0),0) as `out`, COALESCE(SUM(balance > 0),0) as low')
            ->first();

        return ['total' => (int) $row->total, 'out' => (int) $row->out, 'oversold' => (int) $row->oversold, 'low' => (int) $row->low];
    }

    /** every matching row, enriched, in memory-friendly chunks — for exports */
    public function each(array $f, int $chunk = 1000): \Generator
    {
        $query = $this->query($f);
        $page = 1;
        do {
            $rows = (clone $query)->forPage($page++, $chunk)->get();
            foreach ($this->enrich($rows, $f) as $row) {
                yield $row;
            }
        } while ($rows->count() === $chunk);
    }
}
