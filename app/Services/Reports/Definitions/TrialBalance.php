<?php

namespace App\Services\Reports\Definitions;

use App\Services\Accounting\AccountingReports;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;

class TrialBalance extends FinanceReport
{
    public function key(): string
    {
        return 'trial-balance';
    }

    public function title(): string
    {
        return 'Trial balance';
    }

    public function description(): string
    {
        return 'Every account\'s balance; total debits must equal total credits.';
    }

    public function filters(): array
    {
        return [Filter::date('as_at', 'As at')];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::text('code', 'Code'), Column::text('account', 'Account'), Column::text('type', 'Type'),
            Column::money('debit', 'Debit'), Column::money('credit', 'Credit'),
        ];
    }

    private function statement(array $f): array
    {
        return $this->remember('tb', $f, fn () => app(AccountingReports::class)->trialBalance($f['as_at']));
    }

    protected function all(array $f): array
    {
        return array_map(fn ($l) => [
            'code' => (string) $l['code'], 'account' => $l['name'], 'type' => ucfirst($l['type']),
            'debit' => $l['debit'] ?: null, 'credit' => $l['credit'] ?: null,
        ], $this->statement($f)['lines']);
    }

    public function footer(array $f): ?array
    {
        $tb = $this->statement($f);

        return ['code' => null, 'account' => 'Total', 'type' => null, 'debit' => $tb['total_debit'], 'credit' => $tb['total_credit']];
    }

    public function summary(array $f): array
    {
        $tb = $this->statement($f);

        return [
            ['label' => 'Total debits', 'value' => $tb['total_debit'], 'type' => 'money'],
            ['label' => 'Total credits', 'value' => $tb['total_credit'], 'type' => 'money'],
            ['label' => 'Balances?', 'value' => $tb['balanced'] ? 'Yes' : 'No — investigate', 'type' => 'text', 'tone' => $tb['balanced'] ? 'good' : 'bad'],
        ];
    }
}
