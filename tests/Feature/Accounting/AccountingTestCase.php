<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Order\Order;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Shared helpers. The chart of accounts and the accounting settings are created by the
 * migrations, so every test starts with the real seeded chart.
 */
abstract class AccountingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // no lock, books open since the start of 2026, sales go to the main bank account
        AccountingSettings::set(AccountingSettings::START, '2026-01-01');
        AccountingSettings::set(AccountingSettings::CLOSED, '');
        AccountingSettings::set(AccountingSettings::DEPOSIT, '1010');
        AccountingSettings::set(AccountingSettings::LAST_SYNC, '');
    }

    protected function acct(string $code): Account
    {
        return Account::where('code', $code)->firstOrFail();
    }

    protected function ledger(): Ledger
    {
        return app(Ledger::class);
    }

    /** a posted expense: Dr <expense account>, Cr <paid from> */
    protected function expense(float $amount, string $code = '6100', string $date = '2026-03-10', string $from = '1010'): JournalEntry
    {
        return $this->ledger()->post([
            'date' => $date, 'type' => 'expense', 'description' => "Expense $code",
            'lines' => [['account_code' => $code, 'debit' => $amount], ['account_code' => $from, 'credit' => $amount]],
        ]);
    }

    /** a posted income: Dr <deposited to>, Cr <income account> */
    protected function income(float $amount, string $code = '4900', string $date = '2026-03-10', string $to = '1010'): JournalEntry
    {
        return $this->ledger()->post([
            'date' => $date, 'type' => 'income', 'description' => "Income $code",
            'lines' => [['account_code' => $to, 'debit' => $amount], ['account_code' => $code, 'credit' => $amount]],
        ]);
    }

    /** an order counted (or not) as revenue by Order::revenue() */
    protected function order(float $total, string $date, string $payment = 'paid', string $status = 'Delivered', float $delivery = 0): Order
    {
        $order = Order::factory()->create();
        $order->amount = $total - $delivery;
        $order->delivery_cost = $delivery;
        $order->total = $total;
        $order->payment_status = $payment;
        $order->order_status = $status;
        $order->created_at = $date . ' 12:00:00';
        $order->save();

        return $order;
    }

    /** an admin (passes every permission check) */
    protected function admin()
    {
        return $this->createAdminUser();
    }

    /** a staff member holding only the named permissions */
    protected function staffWith(string ...$permissions)
    {
        $staff = $this->createStaffUser();
        foreach ($permissions as $name) {
            $staff->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']));
        }

        return $staff;
    }
}
