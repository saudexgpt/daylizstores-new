<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingSettings;

class TransactionsApiTest extends AccountingTestCase
{
    private function expensePayload(array $over = []): array
    {
        return $over + [
            'type' => 'expense', 'date' => '2026-03-10', 'amount' => 25000, 'description' => 'March shop rent',
            'account_id' => $this->acct('6100')->id, 'payment_account_id' => $this->acct('1010')->id,
            'party' => 'Landlord', 'payment_reference' => 'TRF-991',
        ];
    }

    // ------------------------------------------------------------------ permissions

    public function testPermissionsSeparateReadingFromRecording()
    {
        $entry = $this->expense(100);
        $viewer = $this->staffWith('view accounting');
        $noRights = $this->staffWith();
        $customer = $this->createCustomerUser();

        $this->actingAs($customer, 'api')->getJson('/api/accounting/transactions')->assertStatus(403);
        $this->actingAs($noRights, 'api')->getJson('/api/accounting/transactions')->assertStatus(403);

        // a viewer can read everything but change nothing
        $this->actingAs($viewer, 'api')->getJson('/api/accounting/transactions')->assertStatus(200);
        $this->actingAs($viewer, 'api')->getJson('/api/accounting/transactions/' . $entry->id)->assertStatus(200);
        $this->actingAs($viewer, 'api')->postJson('/api/accounting/transactions', $this->expensePayload())->assertStatus(403);
        $this->actingAs($viewer, 'api')->postJson('/api/accounting/transactions/' . $entry->id . '/void', ['reason' => 'nope'])->assertStatus(403);
        $this->actingAs($viewer, 'api')->putJson('/api/accounting/settings', ['books_closed_through' => '2026-01-31'])->assertStatus(403);
        $this->actingAs($viewer, 'api')->postJson('/api/accounting/accounts', ['code' => '6999', 'name' => 'X', 'type' => 'expense'])->assertStatus(403);

        $manager = $this->staffWith('view accounting', 'manage accounting');
        $this->actingAs($manager, 'api')->postJson('/api/accounting/transactions', $this->expensePayload())->assertStatus(201);
    }

    // ------------------------------------------------------------------ the three everyday forms

    public function testRecordingAnExpenseDebitsTheCategoryAndCreditsTheBank()
    {
        $response = $this->actingAs($this->admin(), 'api')->postJson('/api/accounting/transactions', $this->expensePayload());

        $response->assertStatus(201)
            ->assertJsonPath('transaction.type', 'expense')
            ->assertJsonPath('transaction.amount', 25000)
            ->assertJsonPath('transaction.category.code', '6100')
            ->assertJsonPath('transaction.account.code', '1010');
        $lines = collect($response->json('transaction.lines'));
        $this->assertEquals(25000, $lines->firstWhere('code', '6100')['debit']);
        $this->assertEquals(25000, $lines->firstWhere('code', '1010')['credit']);
        $this->assertMatchesRegularExpression('/^EXP-\d{6}$/', $response->json('transaction.reference'));
    }

    public function testRecordingIncomeDebitsTheBankAndCreditsTheIncomeAccount()
    {
        $response = $this->actingAs($this->admin(), 'api')->postJson('/api/accounting/transactions', [
            'type' => 'income', 'date' => '2026-03-10', 'amount' => 8000, 'description' => 'Shelf rental',
            'account_id' => $this->acct('4900')->id, 'payment_account_id' => $this->acct('1000')->id,
        ]);

        $response->assertStatus(201);
        $lines = collect($response->json('transaction.lines'));
        $this->assertEquals(8000, $lines->firstWhere('code', '1000')['debit']);
        $this->assertEquals(8000, $lines->firstWhere('code', '4900')['credit']);
    }

    public function testATransferMovesMoneyBetweenBalanceSheetAccountsOnly()
    {
        $admin = $this->admin();
        // bank -> cash
        $ok = $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', [
            'type' => 'transfer', 'date' => '2026-03-10', 'amount' => 5000, 'description' => 'Cash for the till',
            'from_account_id' => $this->acct('1010')->id, 'to_account_id' => $this->acct('1000')->id,
        ]);
        $ok->assertStatus(201)->assertJsonPath('transaction.from.code', '1010')->assertJsonPath('transaction.to.code', '1000');

        // owner puts money in: bank <- capital (equity is allowed)
        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', [
            'type' => 'transfer', 'date' => '2026-03-10', 'amount' => 100000, 'description' => 'Owner top-up',
            'from_account_id' => $this->acct('3000')->id, 'to_account_id' => $this->acct('1010')->id,
        ])->assertStatus(201);

        // an expense account is not a transfer account
        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', [
            'type' => 'transfer', 'date' => '2026-03-10', 'amount' => 10, 'description' => 'bad',
            'from_account_id' => $this->acct('1010')->id, 'to_account_id' => $this->acct('6100')->id,
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'balance-sheet accounts'));

        // same account both sides
        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', [
            'type' => 'transfer', 'date' => '2026-03-10', 'amount' => 10, 'description' => 'same',
            'from_account_id' => $this->acct('1010')->id, 'to_account_id' => $this->acct('1010')->id,
        ])->assertStatus(422)->assertJsonValidationErrors('to_account_id');
    }

    // ------------------------------------------------------------------ validation

    public function testAccountKindsAndAmountsAreValidated()
    {
        $admin = $this->admin();
        $post = fn (array $over) => $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $this->expensePayload($over));

        $post(['account_id' => $this->acct('4900')->id])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'expense category'));
        $post(['payment_account_id' => $this->acct('6200')->id])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'where the money'));
        $post(['amount' => 0])->assertStatus(422)->assertJsonValidationErrors('amount');
        $post(['amount' => -5])->assertStatus(422)->assertJsonValidationErrors('amount');
        $post(['amount' => 'abc'])->assertStatus(422)->assertJsonValidationErrors('amount');
        $post(['date' => now()->addDays(3)->toDateString()])->assertStatus(422)->assertJsonValidationErrors('date');
        $post(['date' => '10/03/2026'])->assertStatus(422)->assertJsonValidationErrors('date');
        $post(['description' => ''])->assertStatus(422)->assertJsonValidationErrors('description');
        $post(['account_id' => 99999])->assertStatus(422)->assertJsonValidationErrors('account_id');
        $this->assertSame(0, JournalEntry::count(), 'nothing invalid may have been saved');
    }

    public function testAnExpenseCanBeBoughtOnCreditToAccountsPayable()
    {
        $this->actingAs($this->admin(), 'api')->postJson('/api/accounting/transactions', $this->expensePayload([
            'payment_account_id' => $this->acct('2000')->id, 'account_id' => $this->acct('5000')->id, 'description' => 'Stock on credit',
        ]))->assertStatus(201)->assertJsonPath('transaction.account.code', '2000');
    }

    public function testAJournalEntryMustBalance()
    {
        $admin = $this->admin();
        $lines = fn ($debit, $credit) => [
            'type' => 'journal', 'date' => '2026-03-10', 'description' => 'Year-end adjustment',
            'lines' => [['account_id' => $this->acct('6950')->id, 'debit' => $debit], ['account_id' => $this->acct('1590')->id, 'credit' => $credit]],
        ];

        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $lines(300, 299))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'does not balance'));
        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $lines(300, 300))->assertStatus(201);
    }

    // ------------------------------------------------------------------ listing

    public function testTheListFiltersAndSummarisesAndHidesReversals()
    {
        $admin = $this->admin();
        $this->expense(1000, '6100', '2026-03-05');
        $this->expense(400, '6200', '2026-03-20');
        $this->income(2500, '4900', '2026-03-12');
        $oldExpense = $this->expense(9999, '6100', '2026-01-05');

        $all = $this->actingAs($admin, 'api')->getJson('/api/accounting/transactions?from=2026-03-01&to=2026-03-31')->assertStatus(200);
        $this->assertCount(3, $all->json('transactions'));
        $this->assertEquals(2500, $all->json('summary.income'));
        $this->assertEquals(1400, $all->json('summary.expense'));
        $this->assertEquals(1100, $all->json('summary.net'));

        // filter by type and by category account
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson('/api/accounting/transactions?from=2026-03-01&type=income')->json('transactions'));
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson('/api/accounting/transactions?from=2026-03-01&to=2026-03-31&account_id=' . $this->acct('6200')->id)->json('transactions'));
        // free-text search
        $this->assertCount(1, $this->actingAs($admin, 'api')->getJson('/api/accounting/transactions?q=Expense%206200')->json('transactions'));

        // a voided entry shows as VOID, its reversal is hidden, and it drops out of the totals
        $this->ledger()->void($oldExpense, 'duplicate');
        $jan = $this->actingAs($admin, 'api')->getJson('/api/accounting/transactions?from=2026-01-01&to=2026-01-31');
        $this->assertCount(1, $jan->json('transactions'));
        $this->assertSame('void', $jan->json('transactions.0.status'));
        $this->assertEquals(0, $jan->json('summary.expense'));
    }

    // ------------------------------------------------------------------ void & correct

    public function testVoidNeedsAReasonAndCannotRepeat()
    {
        $admin = $this->admin();
        $entry = $this->expense(200);

        $this->actingAs($admin, 'api')->postJson("/api/accounting/transactions/{$entry->id}/void", [])->assertStatus(422)->assertJsonValidationErrors('reason');
        $ok = $this->actingAs($admin, 'api')->postJson("/api/accounting/transactions/{$entry->id}/void", ['reason' => 'paid twice']);
        $ok->assertStatus(200)->assertJsonPath('transaction.status', 'void');
        $this->assertNotEmpty($ok->json('reversal'));
        $this->actingAs($admin, 'api')->postJson("/api/accounting/transactions/{$entry->id}/void", ['reason' => 'again'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already been voided'));
    }

    public function testEditingReplacesTheTransactionAndKeepsTheTrail()
    {
        $admin = $this->admin();
        $created = $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $this->expensePayload())->json('transaction');

        $edited = $this->actingAs($admin, 'api')->putJson("/api/accounting/transactions/{$created['id']}", $this->expensePayload(['amount' => 27500, 'description' => 'Rent incl. service charge']));

        $edited->assertStatus(200)->assertJsonPath('transaction.amount', 27500)->assertJsonPath('transaction.replaces_entry_id', $created['id']);
        $old = JournalEntry::find($created['id']);
        $this->assertSame('void', $old->status);
        $this->assertSame($edited->json('transaction.id'), (int) $old->replaced_by_entry_id);

        // voided entries can't be edited again
        $this->actingAs($admin, 'api')->putJson("/api/accounting/transactions/{$created['id']}", $this->expensePayload())->assertStatus(422);
    }

    public function testNothingCanBeRecordedOrChangedInAClosedPeriod()
    {
        $admin = $this->admin();
        $entry = $this->expense(100, '6100', '2026-02-10');
        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['books_closed_through' => '2026-02-28'])->assertStatus(200);

        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $this->expensePayload(['date' => '2026-02-20']))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'closed through'));
        $this->actingAs($admin, 'api')->postJson("/api/accounting/transactions/{$entry->id}/void", ['reason' => 'x'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson("/api/accounting/transactions/{$entry->id}", $this->expensePayload(['date' => '2026-02-10']))->assertStatus(422);
        $this->actingAs($admin, 'api')->postJson('/api/accounting/transactions', $this->expensePayload(['date' => '2026-03-01']))->assertStatus(201);
    }

    // ------------------------------------------------------------------ chart of accounts

    public function testTheChartOfAccountsCanBeExtendedButSystemAccountsAreProtected()
    {
        $admin = $this->admin();

        $created = $this->actingAs($admin, 'api')->postJson('/api/accounting/accounts', ['code' => '6970', 'name' => 'Generator Fuel', 'type' => 'expense', 'subtype' => 'operating']);
        $created->assertStatus(201);
        $this->actingAs($admin, 'api')->postJson('/api/accounting/accounts', ['code' => '6970', 'name' => 'Dup', 'type' => 'expense'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($admin, 'api')->postJson('/api/accounting/accounts', ['code' => 'bad code!', 'name' => 'X', 'type' => 'expense'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->actingAs($admin, 'api')->postJson('/api/accounting/accounts', ['code' => '6971', 'name' => 'X', 'type' => 'wizardry'])->assertStatus(422)->assertJsonValidationErrors('type');

        $sales = $this->acct('4000');
        $this->actingAs($admin, 'api')->putJson("/api/accounting/accounts/{$sales->id}", ['name' => 'Sales Revenue', 'is_active' => false])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson("/api/accounting/accounts/{$sales->id}", ['name' => 'Product Sales'])->assertStatus(200);
        $this->assertSame('Product Sales', $sales->fresh()->name);
        $this->assertSame('4000', $sales->fresh()->code);
        $this->actingAs($admin, 'api')->deleteJson("/api/accounting/accounts/{$sales->id}")->assertStatus(422);

        // an account with history can't be retyped or deleted, only deactivated
        $this->expense(10, '6100');
        $rent = $this->acct('6100');
        $this->actingAs($admin, 'api')->putJson("/api/accounting/accounts/{$rent->id}", ['name' => 'Rent', 'type' => 'income'])->assertStatus(422);
        $this->actingAs($admin, 'api')->deleteJson("/api/accounting/accounts/{$rent->id}")->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson("/api/accounting/accounts/{$rent->id}", ['name' => 'Rent', 'is_active' => false])->assertStatus(200);

        // a brand-new, unused account can be deleted
        $this->actingAs($admin, 'api')->deleteJson('/api/accounting/accounts/' . $created->json('account.id'))->assertStatus(204);
    }

    public function testAccountsCanBeListedWithBalances()
    {
        $this->income(1000, '4900');
        $rows = collect($this->actingAs($this->admin(), 'api')->getJson('/api/accounting/accounts?with_balance=1')->json('accounts'));

        $this->assertEquals(1000, $rows->firstWhere('code', '1010')['balance']); // asset: debit-normal
        $this->assertEquals(1000, $rows->firstWhere('code', '4900')['balance']); // income: credit-normal
        $this->assertTrue($rows->firstWhere('code', '4900')['in_use']);
        $this->assertFalse($rows->firstWhere('code', '6100')['in_use']);
    }

    public function testTheSettingsCanBeReadAndValidated()
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->getJson('/api/accounting/settings')->assertStatus(200)
            ->assertJsonStructure(['books_start_date', 'books_closed_through', 'sales_deposit_account', 'deposit_accounts']);

        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['books_closed_through' => now()->addDay()->toDateString()])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['sales_deposit_account' => '6100'])->assertStatus(422);
        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['sales_deposit_account' => '1020'])->assertStatus(200)->assertJsonPath('sales_deposit_account', '1020');
        // clearing the lock
        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['books_closed_through' => '2026-02-01'])->assertStatus(200);
        $this->actingAs($admin, 'api')->putJson('/api/accounting/settings', ['books_closed_through' => ''])->assertStatus(200)->assertJsonPath('books_closed_through', null);
        $this->assertNull(AccountingSettings::closedThrough());
    }
}
