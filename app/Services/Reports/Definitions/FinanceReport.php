<?php

namespace App\Services\Reports\Definitions;

use App\Models\Accounting\Account;
use App\Services\Accounting\SalesPoster;
use App\Services\Reports\Report;

/**
 * Reports drawn from the books. They expose profit, cash and the ledger, so the reader needs
 * "view accounting" (the statements are also on the accounting menu).
 */
abstract class FinanceReport extends Report
{
    public function group(): string
    {
        return 'Finance';
    }

    public function permissions(): array
    {
        return ['view accounting'];
    }

    /** book today's sales before reading, exactly as the accounting screens do */
    public function prepare(array $f): void
    {
        app(SalesPoster::class)->syncIfStale();
    }

    /** "1010 · Bank – Sterling" style options; pass account types to narrow them */
    protected function accountOptions(?array $types = null): array
    {
        return Account::query()->when($types, fn ($q) => $q->whereIn('type', $types))->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['value' => (string) $a->id, 'label' => $a->code . ' · ' . $a->name])->all();
    }
}
