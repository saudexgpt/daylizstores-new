<?php

namespace App\Services\Reports\Definitions;

use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\Ledger;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;

class CategoryBreakdown extends FinanceReport
{
    public function key(): string
    {
        return 'category-breakdown';
    }

    public function title(): string
    {
        return 'Expenses by category';
    }

    public function description(): string
    {
        return 'Where the money went (or came from), by account, with each one\'s share of the total.';
    }

    public function filters(): array
    {
        return [
            Filter::dateRange('Date'),
            Filter::select('kind', 'Show', Filter::options(['expense' => 'Expenses', 'income' => 'Income']), 'expense'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('code', 'Code'),
            Column::text('account', 'Account'),
            Column::text('group', 'Group'),
            Column::money('amount', 'Amount'),
            Column::percent('share', 'Share of total'),
        ];
    }

    protected function all(array $f): array
    {
        return $this->remember('all', $f, function () use ($f) {
            $kind = $f['kind'] ?? 'expense';
            $rows = [];
            foreach (app(AccountingReports::class)->totals($f['from'], $f['to'], [$kind]) as $r) {
                $kobo = $kind === 'income' ? $r->credit_kobo - $r->debit_kobo : $r->debit_kobo - $r->credit_kobo;
                if ($kobo === 0) {
                    continue;
                }
                $rows[] = [
                    'code' => (string) $r->code, 'account' => $r->name, 'kobo' => $kobo,
                    'group' => $kind === 'income' ? 'Income' : ($r->subtype === 'cogs' ? 'Cost of sales' : 'Operating expenses'),
                ];
            }
            usort($rows, fn ($a, $b) => $b['kobo'] <=> $a['kobo'] ?: strcmp($a['code'], $b['code']));
            $total = array_sum(array_column($rows, 'kobo'));

            return array_map(fn ($r) => [
                'code' => $r['code'], 'account' => $r['account'], 'group' => $r['group'],
                'amount' => Ledger::fromKobo($r['kobo']), 'share' => $total > 0 ? round($r['kobo'] / $total * 100, 1) : null,
            ], $rows);
        });
    }

    public function footer(array $f): ?array
    {
        $rows = $this->all($f);

        return $rows ? ['code' => null, 'account' => 'Total', 'group' => null, 'amount' => round(array_sum(array_column($rows, 'amount')), 2), 'share' => 100.0] : null;
    }

    public function summary(array $f): array
    {
        $rows = $this->all($f);
        $label = ($f['kind'] ?? 'expense') === 'income' ? 'Total income' : 'Total expenses';

        return [
            ['label' => $label, 'value' => round(array_sum(array_column($rows, 'amount')), 2), 'type' => 'money'],
            ['label' => 'Accounts', 'value' => count($rows), 'type' => 'int'],
            ['label' => 'Largest', 'value' => $rows[0]['account'] ?? '—', 'type' => 'text'],
        ];
    }
}
