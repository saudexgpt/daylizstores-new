<?php

namespace App\Services\Costing;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Books the COST of what was sold, the way SalesPoster books the sales — one summary per day,
 * idempotent, posting only the difference from what is already there:
 *
 *     Dr  Cost of Goods Sold        the FIFO cost frozen on the day's sold order lines
 *         Cr  Inventory
 *
 * A line belongs to the day of its ORDER (like its revenue), but never before costing went live.
 * A day in a closed period gets its correction dated today, so a closed period never changes.
 * Costs are booked as goods leave the shelf (dispatch) whether or not the order is paid yet,
 * which is what keeps Inventory in the books equal to the stock layers.
 */
class CogsPoster
{
    private const SOURCE = 'cogs';

    public function __construct(private Ledger $ledger)
    {
    }

    /** @return array{days:int, entries:int, net_adjustment:float} */
    public function sync(?string $from = null, ?string $to = null): array
    {
        $none = ['days' => 0, 'entries' => 0, 'net_adjustment' => 0.0];
        if (!CostingSettings::enabled()) {
            return $none;
        }
        $live = CostingSettings::startDate();
        $start = Carbon::parse(max($from ?: '0000-01-01', AccountingSettings::booksStart(), $live))->startOfDay();
        $end = Carbon::parse($to ?: now()->toDateString())->endOfDay();
        if ($start->gt($end)) {
            return $none;
        }

        $cogs = Account::where('code', '5000')->firstOrFail();
        $inventory = Account::where('code', '1100')->firstOrFail();

        // the day a line is booked on: its order's day, but never before go-live
        // (the date is written into the SQL, quoted: the same expression appears in SELECT, WHERE and
        // GROUP BY, and strict MySQL only accepts that when the three are textually identical)
        $day = 'GREATEST(DATE(orders.created_at), ' . DB::getPdo()->quote(Carbon::parse($live)->toDateString()) . ')';
        $current = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')->whereNull('order_items.deleted_at')->whereNotNull('order_items.costed_at')
            ->whereRaw("$day BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->groupBy(DB::raw($day))
            ->selectRaw("$day as day, SUM(order_items.cost_total) as cost")
            ->pluck('cost', 'day');

        $posted = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.source', self::SOURCE)->whereBetween('e.source_key', [$start->toDateString(), $end->toDateString()])
            ->where('l.account_id', $cogs->id)
            ->selectRaw('e.source_key as day, SUM(l.debit - l.credit) as net')->groupBy('e.source_key')->pluck('net', 'day');

        $days = $current->keys()->merge($posted->keys())->unique()->sort()->values();
        $entries = 0;
        $netK = 0;
        foreach ($days as $d) {
            $diff = Ledger::toKobo($current->get($d, 0)) - Ledger::toKobo($posted->get($d, 0));
            if ($diff === 0) {
                continue;
            }
            $locked = AccountingSettings::closedThrough() && Carbon::parse($d)->lte(Carbon::parse(AccountingSettings::closedThrough()));
            $amount = Ledger::fromKobo(abs($diff));
            $this->ledger->post([
                'date' => $locked ? now()->toDateString() : $d,
                'type' => 'sales',
                'description' => ($posted->has($d) ? 'Adjustment to cost of sales of ' : 'Cost of sales for ') . Carbon::parse($d)->format('j M Y'),
                'source' => self::SOURCE, 'source_key' => $d, 'allow_locked' => $locked,
                'lines' => $diff > 0
                    ? [['account_id' => $cogs->id, 'debit' => $amount], ['account_id' => $inventory->id, 'credit' => $amount]]
                    : [['account_id' => $inventory->id, 'debit' => $amount], ['account_id' => $cogs->id, 'credit' => $amount]],
            ]);
            $entries++;
            $netK += $diff;
        }

        return ['days' => $days->count(), 'entries' => $entries, 'net_adjustment' => Ledger::fromKobo($netK)];
    }
}
