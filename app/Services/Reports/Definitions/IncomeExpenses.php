<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Filter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every income and expense line in the books — the transactions behind the profit figure.
 * It reads income/expense ACCOUNT lines (not entry types), so a journal that touches an
 * expense account shows up here and the totals tie to the profit & loss statement.
 */
class IncomeExpenses extends FinanceReport
{
    /** signed amount of a line on an income (credit-normal) or expense (debit-normal) account */
    private const AMOUNT = "CASE WHEN a.type = 'income' THEN l.credit - l.debit ELSE l.debit - l.credit END";

    public function key(): string
    {
        return 'income-expenses';
    }

    public function title(): string
    {
        return 'Income & expenses';
    }

    public function description(): string
    {
        return 'Every income and expense in the books, line by line, with the profit they add up to.';
    }

    public function note(): ?string
    {
        return 'Daily sales appear as one line per day. Voided entries and their reversals are hidden by default; totals equal the profit & loss statement unless an entry was corrected in a later period.';
    }

    public function filters(): array
    {
        return [
            Filter::dateRange('Date'),
            Filter::select('kind', 'Type', Filter::options(['income' => 'Income', 'expense' => 'Expenses']), null),
            Filter::select('account_id', 'Category', $this->accountOptions(['income', 'expense']), null, ['searchable' => true]),
            Filter::select('source', 'Source', Filter::options(['all' => 'Everything', 'manual' => 'Recorded by hand', 'sales' => 'Daily sales and cost-of-sales postings', 'stock' => 'Stock deliveries and adjustments']), 'all'),
            Filter::text('q', 'Search', 'Description, payee, reference'),
            Filter::money('min_amount', 'Amount at least (₦)'),
            Filter::money('max_amount', 'Amount at most (₦)'),
            Filter::select('voided', 'Voided entries', Filter::options(['hide' => 'Hide', 'show' => 'Show']), 'hide'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::date('date', 'Date'),
            Column::text('reference', 'Reference'),
            Column::text('account', 'Category'),
            Column::text('description', 'Description'),
            Column::text('party', 'Paid to / received from'),
            Column::money('income', 'Income'),
            Column::money('expense', 'Expense'),
            Column::status('status', 'Status'),
        ];
    }

    private function base(array $f): Builder
    {
        $q = DB::table('journal_lines as l')
            ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'l.account_id')
            ->whereIn('a.type', ['income', 'expense'])
            ->whereBetween('e.entry_date', [$f['from'], $f['to']]);

        if (($f['voided'] ?? 'hide') === 'hide') {
            $q->where('e.status', 'posted')->where(fn ($w) => $w->whereNull('e.source')->orWhere('e.source', '!=', 'reversal'));
        }
        if (!empty($f['kind'])) {
            $q->where('a.type', $f['kind']);
        }
        if (!empty($f['account_id'])) {
            $q->where('l.account_id', (int) $f['account_id']);
        }
        match ($f['source'] ?? 'all') {
            'manual' => $q->whereNull('e.source'),
            'sales' => $q->whereIn('e.source', ['sales', 'cogs']),
            'stock' => $q->whereIn('e.source', ['receipt', 'adjustment', 'opening_stock']),
            default => null,
        };
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('e.description', 'like', $like)->orWhere('e.party', 'like', $like)
                ->orWhere('e.reference', 'like', $like)->orWhere('l.memo', 'like', $like));
        }
        if (isset($f['min_amount'])) {
            $q->whereRaw('(' . self::AMOUNT . ') >= ?', [$f['min_amount']]);
        }
        if (isset($f['max_amount'])) {
            $q->whereRaw('(' . self::AMOUNT . ') <= ?', [$f['max_amount']]);
        }

        return $q;
    }

    public function query(array $f): ?Builder
    {
        return $this->base($f)
            ->selectRaw('e.entry_date as date, e.reference, a.type, a.code, a.name as account, e.description, e.party, e.status, ' . self::AMOUNT . ' as amount')
            ->orderByDesc('e.entry_date')->orderByDesc('e.id')->orderByDesc('l.id');
    }

    protected function shape($r): array
    {
        $amount = round((float) $r->amount, 2);

        return [
            'date' => $r->date, 'reference' => $r->reference, 'account' => $r->code . ' · ' . $r->account,
            'description' => $r->description, 'party' => $r->party,
            'income' => $r->type === 'income' ? $amount : null,
            'expense' => $r->type === 'expense' ? $amount : null,
            'status' => $r->status === 'void' ? 'Void' : 'Posted',
        ];
    }

    public function summary(array $f): array
    {
        $t = $this->base($f)->selectRaw("COALESCE(SUM(CASE WHEN a.type = 'income' THEN l.credit - l.debit ELSE 0 END),0) as income, COALESCE(SUM(CASE WHEN a.type = 'expense' THEN l.debit - l.credit ELSE 0 END),0) as expense, COUNT(*) as n")->first();
        $net = round((float) $t->income - (float) $t->expense, 2);

        return [
            ['label' => 'Income', 'value' => round((float) $t->income, 2), 'type' => 'money'],
            ['label' => 'Expenses', 'value' => round((float) $t->expense, 2), 'type' => 'money'],
            ['label' => $net >= 0 ? 'Profit' : 'Loss', 'value' => abs($net), 'type' => 'money', 'tone' => $net >= 0 ? 'good' : 'bad'],
            ['label' => 'Lines', 'value' => (int) $t->n, 'type' => 'int'],
        ];
    }
}
