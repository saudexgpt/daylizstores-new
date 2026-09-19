<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

/**
 * A ledger account. `type` decides its normal balance side:
 *   asset, expense            -> debit-normal  (balance = debits - credits)
 *   liability, equity, income -> credit-normal (balance = credits - debits)
 */
class Account extends Model
{
    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    /** the signed balance for this account's own normal side, given raw debit/credit totals */
    public static function normalBalance(string $type, float $debit, float $credit): float
    {
        return in_array($type, ['asset', 'expense'], true) ? $debit - $credit : $credit - $debit;
    }
}
