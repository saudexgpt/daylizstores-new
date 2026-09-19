<?php

namespace App\Services\Costing;

use App\Models\Accounting\Account;
use App\Models\Costing\CostConsumption;
use App\Models\Costing\CostLayer;
use App\Models\Costing\StockAdjustment;
use App\Models\Costing\StockReceipt;
use App\Models\Order\OrderItem;
use App\Models\Stock\ItemStock;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\Ledger;
use App\Services\Accounting\LedgerException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Product costing: what stock cost, and what each sale therefore earned.
 *
 *  - FIFO, per product + size: a delivery creates a LAYER; a sale (or a stock loss) takes from the
 *    oldest layer first and remembers which layers it used, so the cost of a sale is frozen and
 *    auditable. Colours of a size share a cost.
 *  - Landed cost: freight / customs on a delivery are spread across its lines in proportion to
 *    value, in whole kobo, the remainder landing on the last line so the total is exact.
 *  - Money is handled in whole kobo; a layer stores the TOTAL it cost and the total still
 *    remaining, so units taken cost a proportional share of what is left and the last unit takes
 *    the remainder — no kobo is ever created or lost.
 *  - Selling more than the layers hold never blocks a sale: the shortfall is costed at the latest
 *    known cost, marked PROVISIONAL, and trued up when the missing delivery is recorded.
 *
 * Every posting to the books goes through Ledger, in the same transaction as the stock movement.
 */
class Costing
{
    public function __construct(private Ledger $ledger)
    {
    }

    public static function enabled(): bool
    {
        return CostingSettings::enabled();
    }

    /** the size part of a stock key: '' when the product has no sizes */
    public static function sizeKey($size): string
    {
        return trim((string) $size);
    }

    // ------------------------------------------------------------------ receiving stock

    /**
     * Record a delivery: stock goes up, a cost layer is created per product+size, and the books
     * get   Dr Inventory / Cr Bank (or Accounts Payable when bought on credit).
     *
     * @param array $data {
     *   received_on: Y-m-d, supplier: string, invoice_number?: string,
     *   extra_costs?: number, extra_costs_note?: string, payment_account_id?: int|null,
     *   lines: [ { item_id, size?, color?, quantity, unit_cost } ]
     * }
     */
    public function receive(array $data, ?int $userId = null): StockReceipt
    {
        $this->requireEnabled();
        $lines = $this->normaliseReceiptLines($data['lines'] ?? []);

        $valueK = [];
        foreach ($lines as $i => $l) {
            $valueK[$i] = $l['quantity'] * $l['unit_cost_k'];
        }
        $itemsK = array_sum($valueK);
        $extraK = Ledger::toKobo($data['extra_costs'] ?? 0);
        if ($extraK < 0) {
            throw new LedgerException('Extra costs cannot be negative.');
        }
        $shares = $this->allocate($extraK, $valueK);

        $payment = null;
        if (!empty($data['payment_account_id'])) {
            $payment = Account::find($data['payment_account_id']);
            if (!$payment || $payment->type !== 'asset' || !in_array($payment->subtype, ['cash', 'bank', 'clearing'], true) || !$payment->is_active) {
                throw new LedgerException('Choose a bank or cash account to pay from, or leave it empty if the goods were bought on credit.');
            }
        }

        return DB::transaction(function () use ($data, $lines, $valueK, $shares, $itemsK, $extraK, $payment, $userId) {
            $receipt = StockReceipt::create([
                'received_on' => Carbon::parse($data['received_on'])->toDateString(),
                'supplier' => mb_substr(trim($data['supplier']), 0, 150),
                'invoice_number' => isset($data['invoice_number']) ? mb_substr(trim($data['invoice_number']), 0, 100) : null,
                'items_total' => Ledger::fromKobo($itemsK),
                'extra_costs' => Ledger::fromKobo($extraK),
                'extra_costs_note' => isset($data['extra_costs_note']) ? mb_substr(trim($data['extra_costs_note']), 0, 255) : null,
                'landed_total' => Ledger::fromKobo($itemsK + $extraK),
                'payment_account_id' => $payment?->id,
                'status' => 'posted',
                'created_by' => $userId,
            ]);
            $receipt->reference = 'RCV-' . str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT);

            // stock rows, and the cost of each product+size (its lines plus their share of the extras)
            $layerK = [];
            $layerQty = [];
            foreach ($lines as $i => $l) {
                $receipt->lines()->create([
                    'item_id' => $l['item_id'], 'size' => $l['size'], 'color' => $l['color'], 'quantity' => $l['quantity'],
                    'unit_cost' => Ledger::fromKobo($l['unit_cost_k']), 'extra_cost' => Ledger::fromKobo($shares[$i]),
                ]);
                $this->addStock($l['item_id'], $l['color'], $l['size'], $l['quantity']);

                $key = $l['item_id'] . '|' . $l['size'];
                $layerK[$key] = ($layerK[$key] ?? 0) + $valueK[$i] + $shares[$i];
                $layerQty[$key] = ($layerQty[$key] ?? 0) + $l['quantity'];
            }

            foreach ($layerK as $key => $costK) {
                [$itemId, $size] = explode('|', $key, 2);
                $layer = CostLayer::create([
                    'item_id' => (int) $itemId, 'size' => $size, 'source' => 'receipt', 'stock_receipt_id' => $receipt->id,
                    'received_on' => $receipt->received_on, 'qty_received' => $layerQty[$key], 'qty_remaining' => $layerQty[$key],
                    'cost_total' => Ledger::fromKobo($costK), 'cost_remaining' => Ledger::fromKobo($costK),
                ]);
                $this->settleProvisional((int) $itemId, $size, $layer);
            }

            $entry = $this->ledger->post([
                'date' => $receipt->received_on->toDateString(),
                'type' => 'journal',
                'description' => 'Stock received from ' . $receipt->supplier . ($receipt->invoice_number ? ' (invoice ' . $receipt->invoice_number . ')' : ''),
                'party' => $receipt->supplier,
                'payment_reference' => $receipt->invoice_number,
                'source' => 'receipt',
                'source_key' => (string) $receipt->id,
                'created_by' => $userId,
                'lines' => [
                    ['account_code' => '1100', 'debit' => Ledger::fromKobo($itemsK + $extraK)],
                    $payment ? ['account_id' => $payment->id, 'credit' => Ledger::fromKobo($itemsK + $extraK)] : ['account_code' => '2000', 'credit' => Ledger::fromKobo($itemsK + $extraK)],
                ],
            ]);
            $receipt->journal_entry_id = $entry->id;
            $receipt->save();

            return $receipt->load('lines');
        });
    }

    /**
     * Cancel a delivery entered by mistake. Only possible while none of it has been sold or written
     * off (every layer is untouched) — otherwise the cost is already in sales, and the fix is a stock
     * adjustment or a new receipt.
     */
    public function voidReceipt(StockReceipt $receipt, string $reason, ?int $userId = null): void
    {
        DB::transaction(function () use ($receipt, $reason, $userId) {
            $receipt = StockReceipt::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            if ($receipt->status === 'void') {
                throw new LedgerException('This delivery has already been voided.');
            }
            $layers = CostLayer::where('stock_receipt_id', $receipt->id)->lockForUpdate()->get();
            foreach ($layers as $layer) {
                if ($layer->qty_remaining !== $layer->qty_received) {
                    throw new LedgerException('Some of this delivery has already been sold, so it cannot be voided. Record a stock adjustment for anything that is wrong instead.');
                }
            }

            foreach ($receipt->lines as $line) {
                $stock = $this->stockRow($line->item_id, $line->color, $line->size);
                if (!$stock || $stock->quantity_stocked - $stock->reserved - $stock->sold < $line->quantity) {
                    throw new LedgerException('The stock from this delivery is no longer all free (some is reserved for open orders), so it cannot be voided.');
                }
                $stock->quantity_stocked -= $line->quantity;
                $stock->save();
            }
            foreach ($layers as $layer) {
                $layer->qty_remaining = 0;
                $layer->cost_remaining = 0;
                $layer->save();
            }

            if ($receipt->journal_entry_id) {
                $this->ledger->void($receipt->journalEntry, $reason, $userId, true);
            }
            $receipt->status = 'void';
            $receipt->void_reason = mb_substr($reason, 0, 255);
            $receipt->save();
        });
    }

    // ------------------------------------------------------------------ selling stock

    /** cost every line of an order that has just left the shelf (called inside the sell-out transaction) */
    public function costOrder(int $orderId): void
    {
        if (!self::enabled()) {
            return;
        }
        $lines = OrderItem::with('stock')->where('order_id', $orderId)->whereNull('costed_at')->lockForUpdate()->get();
        foreach ($lines as $line) {
            $this->costLine($line);
        }
    }

    /** FIFO cost for one sold order line, frozen on the line. Idempotent. */
    public function costLine(OrderItem $line): void
    {
        if ($line->costed_at !== null || !self::enabled()) {
            return;
        }
        $size = self::sizeKey(optional($line->stock)->size);
        $taken = $this->takeFifo($line->item_id, $size, (int) $line->quantity);

        $totalK = 0;
        foreach ($taken['parts'] as [$layer, $qty, $costK]) {
            CostConsumption::create(['order_item_id' => $line->id, 'cost_layer_id' => $layer->id, 'item_id' => $line->item_id, 'size' => $size, 'quantity' => $qty, 'cost' => Ledger::fromKobo($costK)]);
            $totalK += $costK;
        }
        if ($taken['short'] > 0) {
            $shortK = $taken['short'] * $this->latestUnitCostK($line->item_id, $size);
            CostConsumption::create(['order_item_id' => $line->id, 'cost_layer_id' => null, 'item_id' => $line->item_id, 'size' => $size, 'quantity' => $taken['short'], 'cost' => Ledger::fromKobo($shortK), 'provisional' => true]);
            $totalK += $shortK;
        }

        $line->cost_total = Ledger::fromKobo($totalK);
        $line->costed_at = now();
        $line->save();
    }

    // ------------------------------------------------------------------ adjustments

    /**
     * Damage, loss or a count difference. A loss takes stock off at its FIFO cost (Dr Stock Loss /
     * Cr Inventory); stock found is added at the unit cost you give (Dr Inventory / Cr Stock Loss).
     * Before costing is live it only moves the quantity.
     *
     * @param array $data { item_stock_id, quantity (±), reason, note?, adjusted_on, unit_cost? (gains) }
     */
    public function adjust(array $data, ?int $userId = null): StockAdjustment
    {
        $qty = (int) $data['quantity'];
        if ($qty === 0) {
            throw new LedgerException('The quantity cannot be zero. Use a negative number for stock lost, a positive one for stock found.');
        }
        $date = Carbon::parse($data['adjusted_on'])->toDateString();

        return DB::transaction(function () use ($data, $qty, $date, $userId) {
            $stock = ItemStock::whereKey($data['item_stock_id'])->lockForUpdate()->firstOrFail();
            $size = self::sizeKey($stock->size);
            if ($qty < 0 && $stock->quantity_stocked - $stock->reserved - $stock->sold < -$qty) {
                throw new LedgerException('Only ' . max(0, $stock->quantity_stocked - $stock->reserved - $stock->sold) . ' unit(s) are free — the rest are reserved for open orders.');
            }

            $adjustment = StockAdjustment::create([
                'adjusted_on' => $date, 'item_id' => $stock->item_id, 'item_stock_id' => $stock->id, 'size' => $size,
                'quantity' => $qty, 'reason' => $data['reason'], 'note' => isset($data['note']) ? mb_substr((string) $data['note'], 0, 255) : null,
                'cost' => 0, 'created_by' => $userId,
            ]);
            $adjustment->reference = 'ADJ-' . str_pad((string) $adjustment->id, 6, '0', STR_PAD_LEFT);
            $stock->quantity_stocked += $qty;
            $stock->save();

            if (self::enabled()) {
                $costK = $qty < 0 ? $this->writeOffLayers($adjustment, $stock->item_id, $size, -$qty) : $this->addFoundLayer($adjustment, $stock->item_id, $size, $qty, $data['unit_cost'] ?? null, $date);
                $adjustment->cost = Ledger::fromKobo($costK);

                if ($costK > 0) {
                    $label = ($qty < 0 ? 'Stock written off' : 'Stock found') . ' (' . str_replace('_', ' ', $data['reason']) . ')';
                    $entry = $this->ledger->post([
                        'date' => $date, 'type' => 'journal', 'description' => $label . ' — ' . $adjustment->reference,
                        'source' => 'adjustment', 'source_key' => (string) $adjustment->id, 'created_by' => $userId,
                        'lines' => $qty < 0
                            ? [['account_code' => '6960', 'debit' => Ledger::fromKobo($costK)], ['account_code' => '1100', 'credit' => Ledger::fromKobo($costK)]]
                            : [['account_code' => '1100', 'debit' => Ledger::fromKobo($costK)], ['account_code' => '6960', 'credit' => Ledger::fromKobo($costK)]],
                    ]);
                    $adjustment->journal_entry_id = $entry->id;
                }
            }
            $adjustment->save();

            return $adjustment;
        });
    }

    private function writeOffLayers(StockAdjustment $adjustment, int $itemId, string $size, int $qty): int
    {
        $taken = $this->takeFifo($itemId, $size, $qty);
        $totalK = 0;
        foreach ($taken['parts'] as [$layer, $units, $costK]) {
            CostConsumption::create(['stock_adjustment_id' => $adjustment->id, 'cost_layer_id' => $layer->id, 'item_id' => $itemId, 'size' => $size, 'quantity' => $units, 'cost' => Ledger::fromKobo($costK)]);
            $totalK += $costK;
        }
        if ($taken['short'] > 0) {
            // no layer left to take from: written off at the latest known cost (final — nothing will true it up)
            $shortK = $taken['short'] * $this->latestUnitCostK($itemId, $size);
            CostConsumption::create(['stock_adjustment_id' => $adjustment->id, 'cost_layer_id' => null, 'item_id' => $itemId, 'size' => $size, 'quantity' => $taken['short'], 'cost' => Ledger::fromKobo($shortK)]);
            $totalK += $shortK;
        }

        return $totalK;
    }

    private function addFoundLayer(StockAdjustment $adjustment, int $itemId, string $size, int $qty, $unitCost, string $date): int
    {
        $unitK = Ledger::toKobo($unitCost ?? 0);
        if ($unitK <= 0) {
            throw new LedgerException('Enter what each unit found is worth (its unit cost).');
        }
        $costK = $unitK * $qty;
        $layer = CostLayer::create([
            'item_id' => $itemId, 'size' => $size, 'source' => 'adjustment', 'received_on' => $date,
            'qty_received' => $qty, 'qty_remaining' => $qty, 'cost_total' => Ledger::fromKobo($costK), 'cost_remaining' => Ledger::fromKobo($costK),
        ]);
        $this->settleProvisional($itemId, $size, $layer);

        return $costK;
    }

    // ------------------------------------------------------------------ FIFO mechanics

    /**
     * Take $qty units from the oldest layers of a product+size.
     *
     * @return array{parts: array<int,array{0:CostLayer,1:int,2:int}>, short: int} layer, units, kobo — and how many units had no layer
     */
    private function takeFifo(int $itemId, string $size, int $qty): array
    {
        $parts = [];
        $need = $qty;
        $layers = CostLayer::where('item_id', $itemId)->where('size', $size)->where('qty_remaining', '>', 0)
            ->orderBy('received_on')->orderBy('id')->lockForUpdate()->get();
        foreach ($layers as $layer) {
            if ($need <= 0) {
                break;
            }
            $units = min($need, $layer->qty_remaining);
            $parts[] = [$layer, $units, $this->takeFromLayer($layer, $units)];
            $need -= $units;
        }

        return ['parts' => $parts, 'short' => max($need, 0)];
    }

    /** the cost (kobo) of $units from a layer: a proportional share of what is left, the last unit takes the remainder */
    private function takeFromLayer(CostLayer $layer, int $units): int
    {
        $remainingK = Ledger::toKobo($layer->cost_remaining);
        $costK = $units >= $layer->qty_remaining ? $remainingK : (int) round($remainingK * $units / $layer->qty_remaining);
        $layer->qty_remaining -= $units;
        $layer->cost_remaining = Ledger::fromKobo($remainingK - $costK);
        $layer->save();

        return $costK;
    }

    /** kobo per unit of the most recent delivery of this size (else of any size of the product); 0 if never bought */
    private function latestUnitCostK(int $itemId, string $size): int
    {
        $layer = CostLayer::where('item_id', $itemId)->where('size', $size)->orderByDesc('id')->first()
            ?: CostLayer::where('item_id', $itemId)->orderByDesc('id')->first();

        return $layer && $layer->qty_received > 0 ? (int) round(Ledger::toKobo($layer->cost_total) / $layer->qty_received) : 0;
    }

    /**
     * A delivery has arrived for goods that were already sold without a layer: cost those units
     * from the new layer and correct the frozen cost on their order lines.
     */
    private function settleProvisional(int $itemId, string $size, CostLayer $layer): void
    {
        $rows = CostConsumption::where('item_id', $itemId)->where('size', $size)->where('provisional', true)->orderBy('id')->lockForUpdate()->get();
        foreach ($rows as $row) {
            if ($layer->qty_remaining <= 0) {
                break;
            }
            $units = min($row->quantity, $layer->qty_remaining);
            $realK = $this->takeFromLayer($layer, $units);
            $provisionalK = Ledger::toKobo($row->cost);
            $provisionalPart = $units === $row->quantity ? $provisionalK : (int) round($provisionalK * $units / $row->quantity);

            if ($units === $row->quantity) {
                $row->update(['cost_layer_id' => $layer->id, 'cost' => Ledger::fromKobo($realK), 'provisional' => false]);
            } else {
                $row->update(['quantity' => $row->quantity - $units, 'cost' => Ledger::fromKobo($provisionalK - $provisionalPart)]);
                CostConsumption::create([
                    'order_item_id' => $row->order_item_id, 'cost_layer_id' => $layer->id, 'item_id' => $itemId, 'size' => $size,
                    'quantity' => $units, 'cost' => Ledger::fromKobo($realK), 'provisional' => false,
                ]);
            }

            if ($row->order_item_id && ($line = OrderItem::find($row->order_item_id))) {
                $line->cost_total = Ledger::fromKobo(Ledger::toKobo($line->cost_total) + $realK - $provisionalPart);
                $line->save();
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    /** split $totalK across $weights in proportion; the last takes the remainder so the parts always add up */
    private function allocate(int $totalK, array $weights): array
    {
        $sum = array_sum($weights);
        $out = [];
        $given = 0;
        $count = count($weights);
        $i = 0;
        foreach ($weights as $key => $w) {
            $i++;
            $share = $i === $count ? $totalK - $given : ($sum > 0 ? (int) floor($totalK * ($w / $sum)) : 0);
            $out[$key] = $share;
            $given += $share;
        }

        return $out;
    }

    private function normaliseReceiptLines(array $lines): array
    {
        if (!$lines) {
            throw new LedgerException('Add at least one product to the delivery.');
        }
        $out = [];
        foreach ($lines as $i => $l) {
            $qty = (int) ($l['quantity'] ?? 0);
            $costK = Ledger::toKobo($l['unit_cost'] ?? 0);
            if ($qty <= 0) {
                throw new LedgerException('Line ' . ($i + 1) . ': the quantity must be at least 1.');
            }
            if ($costK <= 0) {
                throw new LedgerException('Line ' . ($i + 1) . ': enter what each unit cost — it cannot be zero.');
            }
            $color = isset($l['color']) && trim((string) $l['color']) !== '' ? trim((string) $l['color']) : null;
            $out[] = ['item_id' => (int) $l['item_id'], 'size' => self::sizeKey($l['size'] ?? ''), 'color' => $color, 'quantity' => $qty, 'unit_cost_k' => $costK];
        }

        // one product+size must carry ONE cost within a delivery (colours share it)
        $seen = [];
        foreach ($out as $l) {
            $key = $l['item_id'] . '|' . $l['size'];
            if (isset($seen[$key]) && $seen[$key] !== $l['unit_cost_k']) {
                throw new LedgerException('The same product and size has two different costs in this delivery. Colours of a size share one cost.');
            }
            $seen[$key] = $l['unit_cost_k'];
        }

        return $out;
    }

    private function stockRow(int $itemId, ?string $color, string $size): ?ItemStock
    {
        return ItemStock::where('item_id', $itemId)->where('color', $color)->where('size', $size === '' ? null : $size)->lockForUpdate()->first();
    }

    private function addStock(int $itemId, ?string $color, string $size, int $qty): void
    {
        $row = $this->stockRow($itemId, $color, $size);
        if (!$row) {
            $row = new ItemStock();
            $row->item_id = $itemId;
            $row->color = $color;
            $row->size = $size === '' ? null : $size;
            $row->quantity_stocked = 0;
        }
        $row->quantity_stocked += $qty;
        $row->save();
    }

    private function requireEnabled(): void
    {
        if (!self::enabled()) {
            throw new LedgerException('Product costing has not been switched on yet. Finish the cost set-up first.');
        }
    }

    /** the closing day before costing starts (opening stock is dated then) */
    public static function openingDate(string $startDate): string
    {
        return Carbon::parse($startDate)->subDay()->toDateString();
    }
}
