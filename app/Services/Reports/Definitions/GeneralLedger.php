<?php

namespace App\Services\Reports\Definitions;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\AccountingSettings;
use App\Services\Reports\Column;
use App\Services\Reports\Filter;

class GeneralLedger extends FinanceReport
{
    public function key(): string
    {
        return 'general-ledger';
    }

    public function title(): string
    {
        return 'General ledger';
    }

    public function description(): string
    {
        return 'Every movement on one account, with an opening balance, a running balance and a closing balance.';
    }

    public function note(): ?string
    {
        return 'Voided entries and their reversals both appear, so you can see exactly what was corrected.';
    }

    public function filters(): array
    {
        $default = Account::where('code', AccountingSettings::depositAccountCode())->value('id');

        return [
            Filter::select('account_id', 'Account', $this->accountOptions(), $default ? (string) $default : null, ['required' => true, 'searchable' => true, 'clearable' => false]),
            Filter::dateRange('Period'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::date('date', 'Date'), Column::text('reference', 'Reference'), Column::text('description', 'Description'),
            Column::text('party', 'Payee / payer'), Column::status('status', 'Status'),
            Column::money('debit', 'Debit'), Column::money('credit', 'Credit'), Column::money('balance', 'Balance'),
        ];
    }

    private function statement(array $f): array
    {
        return $this->remember('gl', $f, fn () => app(AccountingReports::class)->generalLedger((int) $f['account_id'], $f['from'], $f['to']));
    }

    protected function all(array $f): array
    {
        $gl = $this->statement($f);
        $blank = ['reference' => null, 'party' => null, 'status' => null, 'debit' => null, 'credit' => null];

        $rows = [['_kind' => 'opening', 'date' => $f['from'], 'description' => 'Opening balance', 'balance' => $gl['opening_balance']] + $blank];
        foreach ($gl['lines'] as $l) {
            $rows[] = [
                'date' => $l['date'], 'reference' => $l['reference'], 'description' => $l['description'], 'party' => $l['party'],
                'status' => $l['status'] === 'void' ? 'Void' : 'Posted',
                'debit' => $l['debit'] ?: null, 'credit' => $l['credit'] ?: null, 'balance' => $l['balance'],
            ];
        }
        $rows[] = ['_kind' => 'closing', 'date' => $f['to'], 'description' => 'Closing balance', 'balance' => $gl['closing_balance'],
            'debit' => $gl['total_debit'], 'credit' => $gl['total_credit']] + $blank;

        return $rows;
    }

    public function summary(array $f): array
    {
        $gl = $this->statement($f);

        return [
            ['label' => 'Account', 'value' => $gl['account']['code'] . ' · ' . $gl['account']['name'], 'type' => 'text'],
            ['label' => 'Opening balance', 'value' => $gl['opening_balance'], 'type' => 'money'],
            ['label' => 'Total debits', 'value' => $gl['total_debit'], 'type' => 'money'],
            ['label' => 'Total credits', 'value' => $gl['total_credit'], 'type' => 'money'],
            ['label' => 'Closing balance', 'value' => $gl['closing_balance'], 'type' => 'money'],
        ];
    }
}
