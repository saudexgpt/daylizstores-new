<?php

namespace App\Services\Costing;

use App\Services\Accounting\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * "What did this cost last time?" — the unit cost of the most recent delivery, per product and size,
 * used to price a restock. One query for any number of products.
 */
class CostLookup
{
    /**
     * @param  int[]  $itemIds
     * @return array<int, array{any: float, sizes: array<string,float>}>  unit cost by item: the latest delivery overall, and per size
     */
    public function latest(array $itemIds): array
    {
        if (!$itemIds || !CostingSettings::enabled()) {
            return [];
        }
        $latestPerSize = DB::table('cost_layers')->whereIn('item_id', $itemIds)->groupBy('item_id', 'size')->selectRaw('MAX(id) as id');
        $rows = DB::table('cost_layers')->whereIn('id', $latestPerSize)->where('qty_received', '>', 0)
            ->orderBy('id')->get(['item_id', 'size', 'qty_received', 'cost_total']);

        $out = [];
        foreach ($rows as $r) {
            $unit = Ledger::fromKobo((int) round(Ledger::toKobo($r->cost_total) / $r->qty_received));
            $out[$r->item_id]['sizes'][$r->size] = $unit;
            $out[$r->item_id]['any'] = $unit;   // ordered by id, so the last one written is the newest delivery
        }

        return $out;
    }

    /** the unit cost for a row that may or may not be one size: that size if known, else the product's latest */
    public function forRow(array $costs, int $itemId, ?string $size): ?float
    {
        $c = $costs[$itemId] ?? null;
        if (!$c) {
            return null;
        }

        return $size !== null && $size !== '' && isset($c['sizes'][Costing::sizeKey($size)]) ? $c['sizes'][Costing::sizeKey($size)] : $c['any'];
    }
}
