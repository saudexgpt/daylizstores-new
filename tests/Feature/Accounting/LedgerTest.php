<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\Ledger;
use App\Services\Accounting\LedgerException;

/**
 * The invariants of double-entry bookkeeping. If any of these break, the books are wrong.
 */
class LedgerTest extends AccountingTestCase
{
    public function testAPostedEntryBalancesAndGetsASequentialReference()
    {
        $entry = $this->expense(1500.50, '6100');

        $this->assertMatchesRegularExpression('/^EXP-\d{6}$/', $entry->reference);
        $this->assertSame('1500.50', (string) $entry->total);
        $this->assertCount(2, $entry->lines);
        $this->assertSame(
            round($entry->lines->sum('debit'), 2),
            round($entry->lines->sum('credit'), 2),
            'debits must equal credits'
        );
        $this->assertSame('posted', $entry->status);
    }

    public function testAnUnbalancedEntryIsRefusedAndNothingIsSaved()
    {
        try {
            $this->ledger()->post([
                'date' => '2026-03-10', 'type' => 'journal', 'description' => 'oops',
                'lines' => [['account_code' => '6100', 'debit' => 100], ['account_code' => '1010', 'credit' => 99.99]],
            ]);
            $this->fail('an unbalanced entry was accepted');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('does not balance', $e->getMessage());
        }
        $this->assertSame(0, JournalEntry::count());
    }

    public function testKoboArithmeticHasNoFloatingPointDrift()
    {
        // 0.1 + 0.2 != 0.3 in floats. In kobo it is exact.
        $entry = $this->ledger()->post([
            'date' => '2026-03-10', 'type' => 'journal', 'description' => 'float trap',
            'lines' => [
                ['account_code' => '6100', 'debit' => 0.1], ['account_code' => '6200', 'debit' => 0.2],
                ['account_code' => '1010', 'credit' => 0.3],
            ],
        ]);

        $this->assertSame('0.30', (string) $entry->total);
        $this->assertSame(30, Ledger::toKobo('0.30'));
        $this->assertSame(123450, Ledger::toKobo('1,234.50'));
    }

    public function testBadLinesAreRefusedWithAReadableReason()
    {
        $cases = [
            'one line only' => [[['account_code' => '6100', 'debit' => 10]], 'at least two lines'],
            'debit and credit on one line' => [[['account_code' => '6100', 'debit' => 10, 'credit' => 10], ['account_code' => '1010', 'credit' => 10]], 'either a debit or a credit'],
            'a zero line' => [[['account_code' => '6100', 'debit' => 0], ['account_code' => '1010', 'credit' => 0]], 'either a debit or a credit'],
            'negative amount' => [[['account_code' => '6100', 'debit' => -5], ['account_code' => '1010', 'credit' => -5]], 'cannot be negative'],
            'unknown account' => [[['account_id' => 999999, 'debit' => 10], ['account_code' => '1010', 'credit' => 10]], 'does not exist'],
        ];
        foreach ($cases as $label => [$lines, $expected]) {
            try {
                $this->ledger()->post(['date' => '2026-03-10', 'type' => 'journal', 'description' => $label, 'lines' => $lines]);
                $this->fail("$label was accepted");
            } catch (LedgerException $e) {
                $this->assertStringContainsString($expected, $e->getMessage(), $label);
            }
        }
        $this->assertSame(0, JournalEntry::count());
    }

    public function testAnInactiveAccountCannotBePostedTo()
    {
        $this->acct('6100')->update(['is_active' => false]);

        $this->expectException(LedgerException::class);
        $this->expectExceptionMessage('inactive');
        $this->expense(50, '6100');
    }

    public function testVoidingPostsAMirrorEntryAndNetsToZero()
    {
        $entry = $this->expense(1000, '6100');

        $reversal = $this->ledger()->void($entry, 'entered twice');

        $entry->refresh();
        $this->assertSame('void', $entry->status);
        $this->assertSame($reversal->id, (int) $entry->reversed_by_entry_id);
        $this->assertSame('entered twice', $entry->void_reason);
        $this->assertSame($entry->id, (int) $reversal->reverses_entry_id);
        // the mirror image: every line's debit/credit is swapped
        $this->assertEquals(1000, $reversal->lines->firstWhere('account_id', $this->acct('6100')->id)->credit);
        // nothing is deleted: the original and the reversal are both in the books, netting to zero on rent
        $rent = $this->acct('6100')->lines()->selectRaw('SUM(debit) d, SUM(credit) c')->first();
        $this->assertEquals(0, round($rent->d - $rent->c, 2));
        $this->assertSame(2, JournalEntry::count());
    }

    public function testVoidingTwiceOrVoidingAReversalOrASalesSummaryIsRefused()
    {
        $entry = $this->expense(10);
        $reversal = $this->ledger()->void($entry, 'mistake');

        foreach ([$entry->fresh(), $reversal] as $target) {
            try {
                $this->ledger()->void($target, 'again');
                $this->fail('a second void / a reversal void was accepted');
            } catch (LedgerException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $sales = $this->ledger()->post([
            'date' => '2026-03-11', 'type' => 'sales', 'description' => 'Sales', 'source' => 'sales', 'source_key' => '2026-03-11',
            'lines' => [['account_code' => '1010', 'debit' => 5], ['account_code' => '4000', 'credit' => 5]],
        ]);
        $this->expectExceptionMessage('generated from orders');
        $this->ledger()->void($sales, 'no');
    }

    public function testAClosedPeriodCannotBePostedToOrVoided()
    {
        $old = $this->expense(100, '6100', '2026-02-10');
        AccountingSettings::set(AccountingSettings::CLOSED, '2026-02-28');

        try {
            $this->expense(1, '6100', '2026-02-28');
            $this->fail('posting on the closing day was accepted');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('closed through 28 Feb 2026', $e->getMessage());
        }
        try {
            $this->ledger()->void($old, 'too late');
            $this->fail('voiding in a closed period was accepted');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('closed', $e->getMessage());
        }

        // the first open day is fine
        $this->assertSame('posted', $this->expense(1, '6100', '2026-03-01')->status);
    }

    public function testACorrectionVoidsTheOldEntryAndLinksBothWays()
    {
        $old = $this->expense(500, '6100');

        $new = $this->ledger()->replace($old, [
            'date' => '2026-03-10', 'type' => 'expense', 'description' => 'Rent (corrected)',
            'lines' => [['account_code' => '6100', 'debit' => 550], ['account_code' => '1010', 'credit' => 550]],
        ]);

        $old->refresh();
        $this->assertSame('void', $old->status);
        $this->assertSame($new->id, (int) $old->replaced_by_entry_id);
        $this->assertSame($old->id, (int) $new->replaces_entry_id);
        $this->assertSame('posted', $new->status);
        $rent = $this->acct('6100')->lines()->selectRaw('SUM(debit - credit) as net')->value('net');
        $this->assertEquals(550, $rent, 'only the corrected amount remains');
    }

    public function testTheSystemAccountsTheAutomaticPostingsNeedExist()
    {
        foreach (['1010', '4000', '4100'] as $code) {
            $this->assertTrue(Account::where('code', $code)->where('is_system', true)->exists(), "system account $code");
        }
    }
}
