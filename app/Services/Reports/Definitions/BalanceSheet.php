<?php

namespace App\Services\Reports\Definitions;

use App\Services\Accounting\AccountingReports;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;

class BalanceSheet extends FinanceReport
{
    public function key(): string
    {
        return 'balance-sheet';
    }

    public function title(): string
    {
        return 'Balance sheet';
    }

    public function description(): string
    {
        return 'What the business owns, owes and is worth on a given day.';
    }

    public function note(): ?string
    {
        return 'Assets = liabilities + equity. Equity includes the profit made so far that has not yet been rolled into retained earnings.';
    }

    public function filters(): array
    {
        return [Filter::date('as_at', 'As at')];
    }

    public function columns(array $filters = []): array
    {
        return [Column::text('label', 'Account'), Column::text('code', 'Code'), Column::money('amount', 'Amount')];
    }

    private function statement(array $f): array
    {
        return $this->remember('bs', $f, fn () => app(AccountingReports::class)->balanceSheet($f['as_at']));
    }

    protected function all(array $f): array
    {
        $bs = $this->statement($f);
        $rows = [];
        $add = function (string $kind, string $label, $amount = null, ?string $code = null) use (&$rows) {
            $rows[] = ['_kind' => $kind, 'label' => $label, 'code' => $code, 'amount' => $amount];
        };
        $lines = function (array $block) use ($add) {
            foreach ($block['lines'] as $l) {
                $add('line', $l['name'], $l['amount'], $l['code']);
            }
        };

        $add('header', 'Assets');
        $add('header', 'Current assets');
        $lines($bs['current_assets']);
        $add('subtotal', 'Total current assets', $bs['current_assets']['total']);
        if ($bs['fixed_assets']['lines']) {
            $add('header', 'Fixed assets');
            $lines($bs['fixed_assets']);
            $add('subtotal', 'Total fixed assets', $bs['fixed_assets']['total']);
        }
        $add('total', 'Total assets', $bs['total_assets']);

        $add('header', 'Liabilities');
        $lines($bs['liabilities']);
        $add('subtotal', 'Total liabilities', $bs['liabilities']['total']);

        $add('header', 'Equity');
        $lines($bs['equity']);
        $add('line', $bs['equity']['profit_to_date'] >= 0 ? 'Profit to date' : 'Loss to date', $bs['equity']['profit_to_date']);
        $add('subtotal', 'Total equity', $bs['equity']['total']);
        $add('total', 'Total liabilities and equity', $bs['total_liabilities_and_equity']);

        return $rows;
    }

    public function summary(array $f): array
    {
        $bs = $this->statement($f);

        return [
            ['label' => 'Total assets', 'value' => $bs['total_assets'], 'type' => 'money'],
            ['label' => 'Liabilities', 'value' => $bs['liabilities']['total'], 'type' => 'money'],
            ['label' => 'Equity', 'value' => $bs['equity']['total'], 'type' => 'money'],
            ['label' => 'Balances?', 'value' => $bs['balanced'] ? 'Yes' : 'No — investigate', 'type' => 'text', 'tone' => $bs['balanced'] ? 'good' : 'bad'],
        ];
    }
}
