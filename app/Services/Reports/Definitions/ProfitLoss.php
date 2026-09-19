<?php

namespace App\Services\Reports\Definitions;

use App\Services\Accounting\AccountingReports;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;

class ProfitLoss extends FinanceReport
{
    public function key(): string
    {
        return 'profit-loss';
    }

    public function title(): string
    {
        return 'Profit & loss';
    }

    public function description(): string
    {
        return 'Income less cost of sales and expenses for the period — is the business making a profit?';
    }

    public function note(): ?string
    {
        return 'Cost of sales is booked when stock is bought (cash basis), because product costs are not tracked per item.';
    }

    public function filters(): array
    {
        return [
            Filter::dateRange('Period'),
            Filter::select('compare', 'Compare with previous period', Filter::options(['yes' => 'Yes', 'no' => 'No']), 'yes'),
        ];
    }

    public function columns(array $filters = []): array
    {
        $cols = [Column::text('label', 'Account'), Column::text('code', 'Code'), Column::money('amount', 'This period')];
        if (($filters['compare'] ?? 'yes') === 'yes') {
            $cols[] = Column::money('previous', 'Previous period');
            $cols[] = Column::money('change', 'Change');
        }

        return $cols;
    }

    private function statement(array $f): array
    {
        return $this->remember('pl', $f, fn () => app(AccountingReports::class)->profitAndLoss($f['from'], $f['to'], ($f['compare'] ?? 'yes') === 'yes'));
    }

    protected function all(array $f): array
    {
        $pl = $this->statement($f);
        $rows = [];
        $row = function (string $kind, string $label, $amount, $previous, ?string $code = null) use (&$rows) {
            $rows[] = ['_kind' => $kind, 'label' => $label, 'code' => $code, 'amount' => $amount, 'previous' => $previous,
                'change' => $previous !== null ? round($amount - $previous, 2) : null];
        };
        $section = function (string $title, array $block, string $totalLabel) use ($row) {
            $row('header', $title, null, null);
            foreach ($block['lines'] as $l) {
                $row('line', $l['name'], $l['amount'], $l['previous'], $l['code']);
            }
            $row('subtotal', $totalLabel, $block['total'], $block['previous_total']);
        };

        $section('Income', $pl['income'], 'Total income');
        $section('Cost of sales', $pl['cost_of_sales'], 'Total cost of sales');
        $row('total', 'Gross profit', $pl['gross_profit'], $pl['gross_profit_previous']);
        $section('Operating expenses', $pl['operating_expenses'], 'Total operating expenses');
        $row('total', $pl['net_profit'] >= 0 ? 'Net profit' : 'Net loss', $pl['net_profit'], $pl['net_profit_previous']);

        // headers carry no numbers
        return array_map(fn ($r) => $r['_kind'] === 'header' ? ['_kind' => 'header', 'label' => $r['label'], 'code' => null, 'amount' => null, 'previous' => null, 'change' => null] : $r, $rows);
    }

    public function summary(array $f): array
    {
        $pl = $this->statement($f);

        return [
            ['label' => 'Income', 'value' => $pl['income']['total'], 'type' => 'money'],
            ['label' => 'Total expenses', 'value' => $pl['total_expenses'], 'type' => 'money'],
            ['label' => $pl['net_profit'] >= 0 ? 'Net profit' : 'Net loss', 'value' => abs($pl['net_profit']), 'type' => 'money', 'tone' => $pl['net_profit'] >= 0 ? 'good' : 'bad'],
            ['label' => 'Gross margin', 'value' => $pl['gross_margin'], 'type' => 'percent'],
            ['label' => 'Net margin', 'value' => $pl['net_margin'], 'type' => 'percent'],
        ];
    }
}
