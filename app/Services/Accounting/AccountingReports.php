<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Financial statements, computed from the ledger lines. All arithmetic is done in whole kobo
 * and only converted to naira for display, so totals always foot exactly.
 *
 * Void entries and their reversals are BOTH included: they cancel to zero in whichever
 * period each one is dated, which is what makes a correction in a later period correct.
 */
class AccountingReports
{
    private const CASH_SUBTYPES = ['cash', 'bank', 'clearing'];
    private const CURRENT_ASSET_SUBTYPES = ['cash', 'bank', 'clearing', 'inventory', 'receivable', 'prepayment'];

    /** raw debit/credit totals per account, in kobo, for entries dated within [from, to] */
    public function totals(?string $from, ?string $to, ?array $types = null): Collection
    {
        return DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->when($from, fn ($q) => $q->where('e.entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('e.entry_date', '<=', $to))
            ->when($types, fn ($q) => $q->whereIn('a.type', $types))
            ->groupBy('a.id', 'a.code', 'a.name', 'a.type', 'a.subtype')
            ->orderBy('a.code')
            ->get(['a.id', 'a.code', 'a.name', 'a.type', 'a.subtype', DB::raw('SUM(l.debit) as debit'), DB::raw('SUM(l.credit) as credit')])
            ->map(function ($row) {
                $row->debit_kobo = Ledger::toKobo($row->debit);
                $row->credit_kobo = Ledger::toKobo($row->credit);

                return $row;
            });
    }

    // ------------------------------------------------------------------ profit & loss

    public function profitAndLoss(string $from, string $to, bool $compare = true): array
    {
        $current = $this->plBlock($from, $to);
        $result = ['period' => ['from' => $from, 'to' => $to], 'compare' => null] + $this->format($current, null);

        if ($compare) {
            $days = Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
            $prevTo = Carbon::parse($from)->subDay();
            $prevFrom = $prevTo->copy()->subDays($days - 1);
            $previous = $this->plBlock($prevFrom->toDateString(), $prevTo->toDateString());
            $result = ['period' => $result['period'], 'compare' => ['from' => $prevFrom->toDateString(), 'to' => $prevTo->toDateString()]] + $this->format($current, $previous);
        }

        return $result;
    }

    /** sections in kobo: income / cogs / operating, each [code => [name, kobo]] */
    private function plBlock(string $from, string $to): array
    {
        $block = ['income' => [], 'cogs' => [], 'operating' => []];
        foreach ($this->totals($from, $to, ['income', 'expense']) as $row) {
            if ($row->type === 'income') {
                $block['income'][$row->code] = [$row->name, $row->credit_kobo - $row->debit_kobo, $row->id];
            } elseif ($row->subtype === 'cogs') {
                $block['cogs'][$row->code] = [$row->name, $row->debit_kobo - $row->credit_kobo, $row->id];
            } else {
                $block['operating'][$row->code] = [$row->name, $row->debit_kobo - $row->credit_kobo, $row->id];
            }
        }

        return $block;
    }

    private function format(array $current, ?array $previous): array
    {
        $sections = [];
        $totals = [];
        foreach (['income', 'cogs', 'operating'] as $key) {
            $codes = array_unique(array_merge(array_keys($current[$key]), $previous ? array_keys($previous[$key]) : []));
            sort($codes);
            $lines = [];
            $sumCur = 0;
            $sumPrev = 0;
            foreach ($codes as $code) {
                $cur = $current[$key][$code][1] ?? 0;
                $prev = $previous[$key][$code][1] ?? 0;
                $lines[] = [
                    'account_id' => $current[$key][$code][2] ?? ($previous[$key][$code][2] ?? null),
                    'code' => (string) $code,
                    'name' => $current[$key][$code][0] ?? $previous[$key][$code][0],
                    'amount' => Ledger::fromKobo($cur),
                    'previous' => $previous ? Ledger::fromKobo($prev) : null,
                ];
                $sumCur += $cur;
                $sumPrev += $prev;
            }
            $sections[$key] = ['lines' => $lines, 'total' => Ledger::fromKobo($sumCur), 'previous_total' => $previous ? Ledger::fromKobo($sumPrev) : null];
            $totals[$key] = [$sumCur, $sumPrev];
        }

        $gross = [$totals['income'][0] - $totals['cogs'][0], $totals['income'][1] - $totals['cogs'][1]];
        $net = [$gross[0] - $totals['operating'][0], $gross[1] - $totals['operating'][1]];
        $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        return [
            'income' => $sections['income'],
            'cost_of_sales' => $sections['cogs'],
            'gross_profit' => Ledger::fromKobo($gross[0]),
            'gross_profit_previous' => $previous ? Ledger::fromKobo($gross[1]) : null,
            'gross_margin' => $pct($gross[0], $totals['income'][0]),
            'operating_expenses' => $sections['operating'],
            'net_profit' => Ledger::fromKobo($net[0]),
            'net_profit_previous' => $previous ? Ledger::fromKobo($net[1]) : null,
            'net_margin' => $pct($net[0], $totals['income'][0]),
            'total_expenses' => Ledger::fromKobo($totals['cogs'][0] + $totals['operating'][0]),
            'total_expenses_previous' => $previous ? Ledger::fromKobo($totals['cogs'][1] + $totals['operating'][1]) : null,
        ];
    }

    // ------------------------------------------------------------------ balance sheet

    public function balanceSheet(string $asAt): array
    {
        $rows = $this->totals(null, $asAt);
        $sections = ['current_assets' => [], 'fixed_assets' => [], 'liabilities' => [], 'equity' => []];
        $sum = ['current_assets' => 0, 'fixed_assets' => 0, 'liabilities' => 0, 'equity' => 0];
        $income = 0;
        $expense = 0;

        foreach ($rows as $row) {
            $debitNormal = in_array($row->type, ['asset', 'expense'], true);
            $balance = $debitNormal ? $row->debit_kobo - $row->credit_kobo : $row->credit_kobo - $row->debit_kobo;

            if ($row->type === 'income') {
                $income += $balance;
                continue;
            }
            if ($row->type === 'expense') {
                $expense += $balance;
                continue;
            }
            if ($balance === 0) {
                continue;
            }
            $section = match (true) {
                $row->type === 'asset' && in_array($row->subtype, self::CURRENT_ASSET_SUBTYPES, true) => 'current_assets',
                $row->type === 'asset' => 'fixed_assets',
                $row->type === 'liability' => 'liabilities',
                default => 'equity',
            };
            $sections[$section][] = ['account_id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'amount' => Ledger::fromKobo($balance)];
            $sum[$section] += $balance;
        }

        // profit made so far that has not been rolled into Retained Earnings
        $profitToDate = $income - $expense;
        $equityTotal = $sum['equity'] + $profitToDate;
        $assetsTotal = $sum['current_assets'] + $sum['fixed_assets'];
        $liabEquity = $sum['liabilities'] + $equityTotal;

        return [
            'as_at' => $asAt,
            'current_assets' => ['lines' => $sections['current_assets'], 'total' => Ledger::fromKobo($sum['current_assets'])],
            'fixed_assets' => ['lines' => $sections['fixed_assets'], 'total' => Ledger::fromKobo($sum['fixed_assets'])],
            'total_assets' => Ledger::fromKobo($assetsTotal),
            'liabilities' => ['lines' => $sections['liabilities'], 'total' => Ledger::fromKobo($sum['liabilities'])],
            'equity' => ['lines' => $sections['equity'], 'profit_to_date' => Ledger::fromKobo($profitToDate), 'total' => Ledger::fromKobo($equityTotal)],
            'total_liabilities_and_equity' => Ledger::fromKobo($liabEquity),
            'balanced' => $assetsTotal === $liabEquity,
        ];
    }

    // ------------------------------------------------------------------ trial balance

    public function trialBalance(string $asAt): array
    {
        $lines = [];
        $debits = 0;
        $credits = 0;
        foreach ($this->totals(null, $asAt) as $row) {
            $net = $row->debit_kobo - $row->credit_kobo;
            if ($net === 0) {
                continue;
            }
            $lines[] = [
                'account_id' => $row->id, 'code' => $row->code, 'name' => $row->name, 'type' => $row->type,
                'debit' => $net > 0 ? Ledger::fromKobo($net) : 0,
                'credit' => $net < 0 ? Ledger::fromKobo(-$net) : 0,
            ];
            $debits += max($net, 0);
            $credits += max(-$net, 0);
        }

        return [
            'as_at' => $asAt,
            'lines' => $lines,
            'total_debit' => Ledger::fromKobo($debits),
            'total_credit' => Ledger::fromKobo($credits),
            'balanced' => $debits === $credits,
        ];
    }

    // ------------------------------------------------------------------ general ledger

    public function generalLedger(int $accountId, string $from, string $to): array
    {
        $account = Account::findOrFail($accountId);
        $debitNormal = $account->isDebitNormal();

        $opening = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)->where('e.entry_date', '<', $from)
            ->selectRaw('SUM(l.debit) as debit, SUM(l.credit) as credit')->first();
        $openingKobo = $this->signed(Ledger::toKobo($opening->debit ?? 0), Ledger::toKobo($opening->credit ?? 0), $debitNormal);

        $running = $openingKobo;
        $lines = [];
        $debits = 0;
        $credits = 0;
        $rows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->where('l.account_id', $accountId)->whereBetween('e.entry_date', [$from, $to])
            ->orderBy('e.entry_date')->orderBy('e.id')->orderBy('l.id')
            ->get(['e.id as entry_id', 'e.entry_date', 'e.reference', 'e.description', 'e.party', 'e.status', 'l.debit', 'l.credit', 'l.memo']);
        foreach ($rows as $row) {
            $d = Ledger::toKobo($row->debit);
            $c = Ledger::toKobo($row->credit);
            $running += $this->signed($d, $c, $debitNormal);
            $debits += $d;
            $credits += $c;
            $lines[] = [
                'entry_id' => $row->entry_id, 'date' => $row->entry_date, 'reference' => $row->reference,
                'description' => $row->description, 'party' => $row->party, 'status' => $row->status,
                'debit' => Ledger::fromKobo($d), 'credit' => Ledger::fromKobo($c), 'balance' => Ledger::fromKobo($running),
            ];
        }

        return [
            'account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'type' => $account->type],
            'period' => ['from' => $from, 'to' => $to],
            'opening_balance' => Ledger::fromKobo($openingKobo),
            'lines' => $lines,
            'total_debit' => Ledger::fromKobo($debits),
            'total_credit' => Ledger::fromKobo($credits),
            'closing_balance' => Ledger::fromKobo($running),
        ];
    }

    private function signed(int $debit, int $credit, bool $debitNormal): int
    {
        return $debitNormal ? $debit - $credit : $credit - $debit;
    }

    // ------------------------------------------------------------------ overview

    public function overview(string $from, string $to): array
    {
        $pl = $this->profitAndLoss($from, $to, true);

        // twelve calendar months ending with the month of $to (zero-filled)
        // (from the START of the month: subtracting months from a 31st overflows — 31 March
        // minus 11 months is "31 April", which Carbon rolls into May)
        $monthStart = Carbon::parse($to)->startOfMonth()->subMonths(11);
        $monthEnd = Carbon::parse($to)->endOfMonth();
        $series = [];
        for ($m = $monthStart->copy(); $m->lte($monthEnd); $m->addMonth()) {
            $series[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'income' => 0, 'cost_of_sales' => 0, 'expenses' => 0, 'net' => 0];
        }
        $rows = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereBetween('e.entry_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->whereIn('a.type', ['income', 'expense'])
            ->groupBy(DB::raw("DATE_FORMAT(e.entry_date, '%Y-%m')"), 'a.type', 'a.subtype')
            ->get([DB::raw("DATE_FORMAT(e.entry_date, '%Y-%m') as month"), 'a.type', 'a.subtype', DB::raw('SUM(l.debit) as debit'), DB::raw('SUM(l.credit) as credit')]);
        foreach ($rows as $row) {
            $d = Ledger::toKobo($row->debit);
            $c = Ledger::toKobo($row->credit);
            $key = $row->type === 'income' ? 'income' : ($row->subtype === 'cogs' ? 'cost_of_sales' : 'expenses');
            $series[$row->month][$key] += $row->type === 'income' ? $c - $d : $d - $c;
        }
        $trend = array_map(function ($m) {
            $m['net'] = $m['income'] - $m['cost_of_sales'] - $m['expenses'];

            return array_map(fn ($v) => is_int($v) ? Ledger::fromKobo($v) : $v, $m);
        }, array_values($series));

        // where the money went this period, biggest first
        $breakdown = collect($pl['cost_of_sales']['lines'])->merge($pl['operating_expenses']['lines'])
            ->filter(fn ($l) => $l['amount'] > 0)->sortByDesc('amount')->values()->all();

        // position today (not period-bound)
        $balances = $this->totals(null, now()->toDateString(), ['asset', 'liability']);
        $cash = 0;
        $receivable = 0;
        $payable = 0;
        $cashAccounts = [];
        foreach ($balances as $row) {
            $net = $row->debit_kobo - $row->credit_kobo;
            if ($row->type === 'asset' && in_array($row->subtype, self::CASH_SUBTYPES, true)) {
                $cash += $net;
                $cashAccounts[] = ['code' => $row->code, 'name' => $row->name, 'balance' => Ledger::fromKobo($net)];
            } elseif ($row->type === 'asset' && $row->subtype === 'receivable') {
                $receivable += $net;
            } elseif ($row->type === 'liability' && $row->subtype === 'payable') {
                $payable += -$net;
            }
        }

        return [
            'period' => $pl['period'],
            'compare' => $pl['compare'],
            'kpis' => [
                'income' => $pl['income']['total'], 'income_previous' => $pl['income']['previous_total'],
                'cost_of_sales' => $pl['cost_of_sales']['total'],
                'gross_profit' => $pl['gross_profit'], 'gross_margin' => $pl['gross_margin'],
                'expenses' => $pl['operating_expenses']['total'], 'expenses_previous' => $pl['operating_expenses']['previous_total'],
                'total_expenses' => $pl['total_expenses'], 'total_expenses_previous' => $pl['total_expenses_previous'],
                'net_profit' => $pl['net_profit'], 'net_profit_previous' => $pl['net_profit_previous'], 'net_margin' => $pl['net_margin'],
            ],
            'trend' => $trend,
            'expense_breakdown' => $breakdown,
            'position' => [
                'cash_and_bank' => Ledger::fromKobo($cash),
                'accounts' => $cashAccounts,
                'receivable' => Ledger::fromKobo($receivable),
                'payable' => Ledger::fromKobo($payable),
            ],
        ];
    }
}
