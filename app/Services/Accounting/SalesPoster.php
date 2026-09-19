<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Order\Order;
use Carbon\Carbon;
use App\Services\Costing\CogsPoster;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Posts product sales to the ledger as a DAILY SALES SUMMARY — the way a shop posts its till
 * report each evening — instead of one journal entry per order (150k+ rows for no benefit):
 *
 *     Dr  Bank (where customer transfers land)      the day's takings
 *         Cr  Sales Revenue                          products sold
 *         Cr  Delivery Income                        delivery charged
 *
 * A day's figure is the sum of orders that are PAID and not CANCELLED (Order::revenue()),
 * dated by the order date, from the books start date onward.
 *
 * It is idempotent: the summary for a day is compared with what is already posted for that
 * day and only the DIFFERENCE is posted (an "adjustment"). Running it twice posts nothing the
 * second time; if an order was cancelled or paid late, the next run posts the correction.
 * If the day is in a closed period the adjustment is dated today, so a closed period never
 * changes.
 */
class SalesPoster
{
    private const SOURCE = 'sales';

    public function __construct(private Ledger $ledger)
    {
    }

    /**
     * @return array{days:int, entries:int, net_adjustment:float, skipped:bool}
     */
    public function sync(?string $from = null, ?string $to = null): array
    {
        // two syncs at once could each post the same difference
        $lock = Cache::lock('accounting-sales-sync', 300);
        if (!$lock->get()) {
            return ['days' => 0, 'entries' => 0, 'net_adjustment' => 0.0, 'skipped' => true];
        }

        try {
            $result = $this->run($from, $to);
            // the cost of what was sold goes in with the sales (nothing happens until costing is live)
            app(CogsPoster::class)->sync($from, $to);

            return $result;
        } finally {
            $lock->release();
        }
    }

    /** run a sync only if the last one is older than $minutes (cheap to call on every page load) */
    public function syncIfStale(int $minutes = 10): void
    {
        $last = AccountingSettings::get(AccountingSettings::LAST_SYNC);
        if (!$last || Carbon::parse($last)->lt(now()->subMinutes($minutes))) {
            $this->sync();
        }
    }

    private function run(?string $from, ?string $to): array
    {
        $start = Carbon::parse(max($from ?: '0000-01-01', AccountingSettings::booksStart()))->startOfDay();
        $end = Carbon::parse($to ?: now()->toDateString())->endOfDay();
        if ($start->gt($end)) {
            return ['days' => 0, 'entries' => 0, 'net_adjustment' => 0.0, 'skipped' => false];
        }

        $deposit = Account::where('code', AccountingSettings::depositAccountCode())->first();
        $revenue = Account::where('code', '4000')->firstOrFail();
        $delivery = Account::where('code', '4100')->firstOrFail();
        if (!$deposit || !$deposit->is_active) {
            throw new LedgerException('The account sales are deposited to is missing or inactive. Choose one in Accounting settings.');
        }

        // what the orders say each day's sales are (in kobo)
        $current = Order::query()->revenue()
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('DATE(orders.created_at) as day, COUNT(*) as orders, SUM(orders.total - orders.delivery_cost) as revenue, SUM(orders.delivery_cost) as delivery')
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->get()
            ->keyBy('day');

        // what is already in the books for those days (credits minus debits, in kobo)
        $posted = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('e.source', self::SOURCE)
            ->whereBetween('e.source_key', [$start->toDateString(), $end->toDateString()])
            ->whereIn('l.account_id', [$revenue->id, $delivery->id])
            ->selectRaw('e.source_key as day, l.account_id, SUM(l.credit - l.debit) as net')
            ->groupBy('e.source_key', 'l.account_id')
            ->get()
            ->groupBy('day');

        $days = $current->keys()->merge($posted->keys())->unique()->sort()->values();
        $entries = 0;
        $netKobo = 0;

        foreach ($days as $day) {
            // (->get(), not [$day]: indexing a collection with a missing key throws)
            $orders = $current->get($day);
            $postedDay = $posted->get($day);
            $wantRevenue = Ledger::toKobo($orders->revenue ?? 0);
            $wantDelivery = Ledger::toKobo($orders->delivery ?? 0);
            $haveRevenue = Ledger::toKobo(optional($postedDay?->firstWhere('account_id', $revenue->id))->net ?? 0);
            $haveDelivery = Ledger::toKobo(optional($postedDay?->firstWhere('account_id', $delivery->id))->net ?? 0);

            $diffRevenue = $wantRevenue - $haveRevenue;
            $diffDelivery = $wantDelivery - $haveDelivery;
            if ($diffRevenue === 0 && $diffDelivery === 0) {
                continue;
            }

            $isFirst = $postedDay === null;
            $lines = [];
            $this->addSigned($lines, $revenue->id, $diffRevenue);
            $this->addSigned($lines, $delivery->id, $diffDelivery);
            // the bank moves by the same amount, the other way round
            $this->addSigned($lines, $deposit->id, -($diffRevenue + $diffDelivery));

            $locked = AccountingSettings::closedThrough() && Carbon::parse($day)->lte(Carbon::parse(AccountingSettings::closedThrough()));
            $this->ledger->post([
                'date' => $locked ? now()->toDateString() : $day,
                'type' => 'sales',
                'description' => $isFirst
                    ? 'Sales for ' . Carbon::parse($day)->format('j M Y') . ' (' . (int) ($orders->orders ?? 0) . ' orders)'
                    : 'Adjustment to sales of ' . Carbon::parse($day)->format('j M Y'),
                'source' => self::SOURCE,
                'source_key' => $day,
                'lines' => $lines,
                'allow_locked' => $locked, // dated today, which is open
            ]);
            $entries++;
            $netKobo += $diffRevenue + $diffDelivery;
        }

        AccountingSettings::set(AccountingSettings::LAST_SYNC, now()->toDateTimeString());

        return ['days' => $days->count(), 'entries' => $entries, 'net_adjustment' => Ledger::fromKobo($netKobo), 'skipped' => false];
    }

    /**
     * $kobo is "credit-positive" for the income accounts and is passed negated for the bank,
     * so a positive value is a credit and a negative value is a debit.
     */
    private function addSigned(array &$lines, int $accountId, int $kobo): void
    {
        if ($kobo === 0) {
            return;
        }
        $lines[] = $kobo > 0
            ? ['account_id' => $accountId, 'credit' => Ledger::fromKobo($kobo)]
            : ['account_id' => $accountId, 'debit' => Ledger::fromKobo(-$kobo)];
    }
}
