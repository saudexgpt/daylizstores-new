<?php

namespace App\Services\Costing;

use App\Models\Costing\CostLayer;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\Ledger;
use App\Services\Accounting\LedgerException;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Switching costing on (the cut-over) and checking it stays true.
 *
 * At cut-over every product+size on the shelf gets an OPENING layer: the quantity the system
 * holds (stocked − sold, which is what is physically there, reserved units included) at the cost
 * you supply from your supplier invoices. The books get   Dr Inventory / Cr Opening Balance Equity
 * dated the day before, and from then on stock only moves through Receive stock, sales and
 * stock adjustments — which is what keeps Inventory in the books equal to the layers.
 */
class CostingSetup
{
    public function __construct(private Ledger $ledger)
    {
    }

    /** what the system says is on the shelf, one row per product+size */
    public function shelf(): Collection
    {
        return DB::table('item_stocks as s')
            ->join('items as i', 'i.id', '=', 's.item_id')
            ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
            ->whereNull('s.deleted_at')->whereNull('i.deleted_at')
            ->groupBy('s.item_id', 'i.name', 'c.name', DB::raw("COALESCE(TRIM(s.size), '')"))
            ->havingRaw('SUM(s.quantity_stocked - s.sold) > 0')
            ->selectRaw("s.item_id, i.name as product, c.name as category, COALESCE(TRIM(s.size), '') as size, SUM(s.quantity_stocked - s.sold) as qty")
            ->orderBy('i.name')->orderBy('size')
            ->get()
            ->map(fn ($r) => ['item_id' => (int) $r->item_id, 'product' => $r->product, 'category' => $r->category, 'size' => $r->size, 'qty' => (int) $r->qty]);
    }

    /**
     * Check a cost sheet against the shelf without changing anything.
     *
     * @param array<int,array{item_id:int,size?:string,unit_cost:mixed}> $rows
     */
    public function check(array $rows): array
    {
        $costs = [];
        $invalid = [];
        foreach ($rows as $i => $r) {
            $raw = $r['unit_cost'] ?? null;
            if ($raw === null || $raw === '') {
                continue; // an empty cost is "missing", not "invalid"
            }
            $size = Costing::sizeKey($r['size'] ?? '');
            $k = is_numeric(str_replace(',', '', (string) $raw)) ? Ledger::toKobo($raw) : 0;
            if ($k <= 0) {
                $invalid[] = ['row' => $i + 1, 'item_id' => (int) ($r['item_id'] ?? 0), 'size' => $size, 'reason' => 'The cost must be a number above zero.'];
                continue;
            }
            $costs[(int) ($r['item_id'] ?? 0) . '|' . $size] = $k;
        }

        $shelf = $this->shelf();
        $missing = [];
        $units = 0;
        $valueK = 0;
        foreach ($shelf as $row) {
            $k = $costs[$row['item_id'] . '|' . $row['size']] ?? null;
            if ($k === null) {
                $missing[] = $row;
                continue;
            }
            $units += $row['qty'];
            $valueK += $row['qty'] * $k;
        }
        $onShelf = $shelf->mapWithKeys(fn ($r) => [$r['item_id'] . '|' . $r['size'] => true]);
        $unknown = collect($costs)->keys()->reject(fn ($k) => $onShelf->has($k))->count();

        return [
            'ok' => !$missing && !$invalid && $shelf->isNotEmpty(),
            'shelf_lines' => $shelf->count(),
            'priced_lines' => $shelf->count() - count($missing),
            'units' => $units,
            'value' => Ledger::fromKobo($valueK),
            'missing_count' => count($missing),
            'missing' => array_slice($missing, 0, 100),
            'invalid' => array_slice($invalid, 0, 100),
            'invalid_count' => count($invalid),
            'not_on_shelf_count' => $unknown,
        ];
    }

    /** switch costing on: opening layers + opening journal + settings, in one transaction */
    public function goLive(array $rows, string $startDate, ?int $userId = null): array
    {
        if (CostingSettings::enabled()) {
            throw new LedgerException('Product costing is already switched on.');
        }
        $start = Carbon::parse($startDate)->startOfDay();
        if ($start->gt(now()->endOfDay())) {
            throw new LedgerException('The start date cannot be in the future. Go live on the day itself, after the stock count.');
        }
        if ($start->lte(Carbon::parse(AccountingSettings::booksStart()))) {
            throw new LedgerException('Costing must start after the books start date (' . Carbon::parse(AccountingSettings::booksStart())->format('j M Y') . ').');
        }
        $check = $this->check($rows);
        if (!$check['ok']) {
            throw new LedgerException($check['shelf_lines'] === 0
                ? 'There is no stock on the shelf to value. Record your stock first.'
                : ($check['missing_count'] + $check['invalid_count']) . ' product size(s) have no valid cost yet. Every size on the shelf needs one before costing can start.');
        }

        $costs = [];
        foreach ($rows as $r) {
            if (($r['unit_cost'] ?? '') !== '' && ($r['unit_cost'] ?? null) !== null) {
                $costs[(int) $r['item_id'] . '|' . Costing::sizeKey($r['size'] ?? '')] = Ledger::toKobo($r['unit_cost']);
            }
        }
        $opening = Costing::openingDate($start->toDateString());

        return DB::transaction(function () use ($costs, $opening, $start, $userId) {
            $now = now();
            $totalK = 0;
            $batch = [];
            foreach ($this->shelf() as $row) {
                $costK = $row['qty'] * $costs[$row['item_id'] . '|' . $row['size']];
                $totalK += $costK;
                $batch[] = [
                    'item_id' => $row['item_id'], 'size' => $row['size'], 'source' => 'opening', 'stock_receipt_id' => null, 'received_on' => $opening,
                    'qty_received' => $row['qty'], 'qty_remaining' => $row['qty'],
                    'cost_total' => Ledger::fromKobo($costK), 'cost_remaining' => Ledger::fromKobo($costK), 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($batch, 500) as $chunk) {
                CostLayer::insert($chunk);
            }

            $entry = $this->ledger->post([
                'date' => $opening, 'type' => 'opening', 'description' => 'Opening stock at cost (' . count($batch) . ' product sizes)',
                'source' => 'opening_stock', 'source_key' => $start->toDateString(), 'created_by' => $userId,
                'lines' => [['account_code' => '1100', 'debit' => Ledger::fromKobo($totalK)], ['account_code' => '3900', 'credit' => Ledger::fromKobo($totalK)]],
            ]);

            AccountingSettings::set(CostingSettings::ENABLED, '1');
            AccountingSettings::set(CostingSettings::START, $start->toDateString());

            return ['layers' => count($batch), 'value' => Ledger::fromKobo($totalK), 'journal' => $entry->reference, 'start_date' => $start->toDateString()];
        });
    }

    /**
     * Do the books and the shelf still agree?
     *  - per product+size, units in the layers must equal stocked − sold;
     *  - Inventory in the ledger must equal the cost still in the layers;
     *  - units sold beyond the layers are "provisional" until the missing delivery is recorded.
     */
    public function reconcile(): array
    {
        $layers = CostLayer::query()->groupBy('item_id', 'size')
            ->selectRaw('item_id, size, SUM(qty_remaining) as qty, SUM(cost_remaining) as value')->get()
            ->keyBy(fn ($r) => $r->item_id . '|' . $r->size);

        $stock = DB::table('item_stocks as s')->join('items as i', 'i.id', '=', 's.item_id')
            ->whereNull('s.deleted_at')->whereNull('i.deleted_at')
            ->groupBy('s.item_id', 'i.name', DB::raw("COALESCE(TRIM(s.size), '')"))
            ->selectRaw("s.item_id, i.name as product, COALESCE(TRIM(s.size), '') as size, SUM(s.quantity_stocked - s.sold) as qty")->get();

        $differences = [];
        $seen = [];
        foreach ($stock as $row) {
            $key = $row->item_id . '|' . $row->size;
            $seen[$key] = true;
            $shelf = max((int) $row->qty, 0); // more sold than stocked is "oversold", not negative stock
            $inLayers = (int) optional($layers->get($key))->qty;
            if ($shelf !== $inLayers) {
                $differences[] = ['item_id' => (int) $row->item_id, 'product' => $row->product, 'size' => $row->size, 'shelf' => $shelf, 'layers' => $inLayers];
            }
        }
        foreach ($layers as $key => $layer) {
            if (!isset($seen[$key]) && (int) $layer->qty !== 0) {
                $differences[] = ['item_id' => (int) $layer->item_id, 'product' => null, 'size' => $layer->size, 'shelf' => 0, 'layers' => (int) $layer->qty];
            }
        }

        $layerValueK = 0;
        foreach ($layers as $layer) {
            $layerValueK += Ledger::toKobo($layer->value);
        }
        $glK = Ledger::toKobo(DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('a.code', '1100')
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as v')->value('v'));

        $provisional = DB::table('cost_consumptions')->where('provisional', true)->selectRaw('COALESCE(SUM(quantity), 0) as units, COALESCE(SUM(cost), 0) as value')->first();

        return [
            'enabled' => CostingSettings::enabled(),
            'stock_value' => Ledger::fromKobo($layerValueK),
            'books_value' => Ledger::fromKobo($glK),
            'value_difference' => Ledger::fromKobo($glK - $layerValueK),
            'in_step' => $glK === $layerValueK && !$differences,
            'unit_differences' => count($differences),
            'differences' => array_slice($differences, 0, 50),
            'provisional_units' => (int) $provisional->units,
            'provisional_value' => round((float) $provisional->value, 2),
        ];
    }
}
