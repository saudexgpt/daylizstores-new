<?php

namespace Tests\Feature\Accounting;

use App\Services\Accounting\AccountingReports;

/**
 * The maths of the statements. The scenario is small enough to check by hand:
 *
 *   March:   sales (other income)   100,000        Feb:  income 40,000
 *            stock purchases (COGS)  30,000              rent   10,000
 *            rent                    12,000
 *            electricity              8,000
 *   => gross profit 70,000 (70%), net profit 50,000 (50%)
 */
class StatementsTest extends AccountingTestCase
{
    private function scenario(): void
    {
        // opening: the owner puts 500,000 into the bank
        $this->ledger()->post([
            'date' => '2026-01-02', 'type' => 'opening', 'description' => 'Owner capital',
            'lines' => [['account_code' => '1010', 'debit' => 500000], ['account_code' => '3000', 'credit' => 500000]],
        ]);
        $this->income(40000, '4900', '2026-02-10');
        $this->expense(10000, '6100', '2026-02-11');

        $this->income(100000, '4900', '2026-03-05');
        $this->expense(30000, '5000', '2026-03-06');
        $this->expense(12000, '6100', '2026-03-07');
        $this->expense(8000, '6200', '2026-03-08');
    }

    private function pl(string $from = '2026-03-01', string $to = '2026-03-31', bool $compare = true): array
    {
        return app(AccountingReports::class)->profitAndLoss($from, $to, $compare);
    }

    public function testProfitAndLossFootsAndComputesMargins()
    {
        $this->scenario();
        $pl = $this->pl();

        $this->assertEquals(100000, $pl['income']['total']);
        $this->assertEquals(30000, $pl['cost_of_sales']['total']);
        $this->assertEquals(70000, $pl['gross_profit']);
        $this->assertEquals(70.0, $pl['gross_margin']);
        $this->assertEquals(20000, $pl['operating_expenses']['total']);
        $this->assertEquals(50000, $pl['net_profit']);
        $this->assertEquals(50.0, $pl['net_margin']);
        $this->assertEquals(50000, $pl['income']['total'] - $pl['total_expenses']);
        $this->assertSame(['6100', '6200'], array_column($pl['operating_expenses']['lines'], 'code'), 'expense lines are ordered by code');
    }

    public function testThePreviousPeriodComparisonUsesTheSameLengthImmediatelyBefore()
    {
        $this->scenario();
        $pl = $this->pl('2026-03-01', '2026-03-31'); // 31 days -> compares with 2026-01-29..2026-02-28

        $this->assertSame('2026-01-29', $pl['compare']['from']);
        $this->assertSame('2026-02-28', $pl['compare']['to']);
        $this->assertEquals(40000, $pl['income']['previous_total']);
        $this->assertEquals(30000, $pl['net_profit_previous']);
        $rent = collect($pl['operating_expenses']['lines'])->firstWhere('code', '6100');
        $this->assertEquals(12000, $rent['amount']);
        $this->assertEquals(10000, $rent['previous']);
        // an account that only existed last period still appears, with 0 this period
        $this->income(10, '4910', '2026-02-20');
        $interest = collect($this->pl()['income']['lines'])->firstWhere('code', '4910');
        $this->assertEquals(0, $interest['amount']);
        $this->assertEquals(10, $interest['previous']);
    }

    public function testALossIsReportedAsANegativeProfitAndTheMarginStillWorks()
    {
        $this->income(1000, '4900', '2026-03-05');
        $this->expense(1500, '6100', '2026-03-06');

        $pl = $this->pl('2026-03-01', '2026-03-31', false);

        $this->assertEquals(-500, $pl['net_profit']);
        $this->assertEquals(-50.0, $pl['net_margin']);
        $this->assertNull($pl['compare']);
    }

    public function testMarginsAreNullNotADivisionByZeroWhenThereIsNoIncome()
    {
        $this->expense(1000, '6100', '2026-03-06');

        $pl = $this->pl('2026-03-01', '2026-03-31', false);

        $this->assertEquals(-1000, $pl['net_profit']);
        $this->assertNull($pl['net_margin']);
        $this->assertNull($pl['gross_margin']);
    }

    public function testAVoidedEntryDropsOutOfTheStatement()
    {
        $this->scenario();
        $mistake = $this->expense(5000, '6800', '2026-03-09');
        $this->assertEquals(45000, $this->pl()['net_profit']);

        $this->ledger()->void($mistake, 'duplicate');

        $this->assertEquals(50000, $this->pl()['net_profit']);
    }

    public function testSalesRefundsReduceRevenue()
    {
        $this->income(1000, '4900', '2026-03-05');
        $this->ledger()->post([
            'date' => '2026-03-06', 'type' => 'expense', 'description' => 'Refund',
            'lines' => [['account_code' => '4200', 'debit' => 250], ['account_code' => '1010', 'credit' => 250]],
        ]);

        $pl = $this->pl('2026-03-01', '2026-03-31', false);

        $this->assertEquals(750, $pl['income']['total'], 'the contra account (4200) nets against income');
    }

    public function testTheBalanceSheetBalancesAndIncludesProfitToDate()
    {
        $this->scenario();
        // buy a generator with cash from the bank, borrow to pay part of a bill, draw some money
        $this->ledger()->post(['date' => '2026-03-15', 'type' => 'transfer', 'description' => 'Generator', 'lines' => [['account_code' => '1500', 'debit' => 80000], ['account_code' => '1010', 'credit' => 80000]]]);
        $this->ledger()->post(['date' => '2026-03-16', 'type' => 'transfer', 'description' => 'Bank loan', 'lines' => [['account_code' => '1010', 'debit' => 200000], ['account_code' => '2100', 'credit' => 200000]]]);
        $this->ledger()->post(['date' => '2026-03-20', 'type' => 'transfer', 'description' => 'Owner drawings', 'lines' => [['account_code' => '3100', 'debit' => 15000], ['account_code' => '1010', 'credit' => 15000]]]);
        $this->expense(3000, '6800', '2026-03-21', '2000'); // bought on credit → accounts payable

        $bs = app(AccountingReports::class)->balanceSheet('2026-03-31');

        $this->assertTrue($bs['balanced'], 'assets must equal liabilities + equity');
        // bank: 500,000 + 40,000 - 10,000 + 100,000 - 30,000 - 12,000 - 8,000 - 80,000 + 200,000 - 15,000 = 685,000
        $bank = collect($bs['current_assets']['lines'])->firstWhere('code', '1010');
        $this->assertEquals(685000, $bank['amount']);
        $this->assertEquals(80000, $bs['fixed_assets']['total']);
        $this->assertEquals(765000, $bs['total_assets']);
        $this->assertEquals(203000, $bs['liabilities']['total']); // loan 200,000 + payable 3,000
        // profit to date: (40,000-10,000) + (100,000-30,000-12,000-8,000) - 3,000 = 77,000
        $this->assertEquals(77000, $bs['equity']['profit_to_date']);
        $this->assertEquals(500000 - 15000 + 77000, $bs['equity']['total']);
        $this->assertEquals($bs['total_assets'], $bs['total_liabilities_and_equity']);
    }

    public function testTheBalanceSheetIsAsAtTheChosenDate()
    {
        $this->scenario();

        $feb = app(AccountingReports::class)->balanceSheet('2026-02-28');

        $this->assertTrue($feb['balanced']);
        $this->assertEquals(500000 + 40000 - 10000, $feb['total_assets']);
    }

    public function testTheTrialBalanceHasEqualDebitAndCreditTotals()
    {
        $this->scenario();

        $tb = app(AccountingReports::class)->trialBalance('2026-03-31');

        $this->assertTrue($tb['balanced']);
        $this->assertEquals($tb['total_debit'], $tb['total_credit']);
        $this->assertGreaterThan(0, $tb['total_debit']);
        $bank = collect($tb['lines'])->firstWhere('code', '1010');
        $this->assertEquals(0, $bank['credit']);
        $capital = collect($tb['lines'])->firstWhere('code', '3000');
        $this->assertEquals(500000, $capital['credit'], 'a credit-normal account shows its balance in the credit column');
    }

    public function testTheGeneralLedgerShowsOpeningBalanceRunningBalanceAndClosing()
    {
        $this->scenario();

        $gl = app(AccountingReports::class)->generalLedger($this->acct('1010')->id, '2026-03-01', '2026-03-31');

        // bank before March: 500,000 + 40,000 - 10,000
        $this->assertEquals(530000, $gl['opening_balance']);
        $this->assertCount(4, $gl['lines']);
        $this->assertEquals(630000, $gl['lines'][0]['balance']); // +100,000
        $this->assertEquals(600000, $gl['lines'][1]['balance']); // -30,000
        $this->assertEquals(580000, $gl['closing_balance']);
        $this->assertEquals(100000, $gl['total_debit']);
        $this->assertEquals(50000, $gl['total_credit']);
        $this->assertEquals($gl['opening_balance'] + $gl['total_debit'] - $gl['total_credit'], $gl['closing_balance']);
    }

    public function testTheOverviewGivesKpisAMonthlyTrendAndTheCashPosition()
    {
        $this->scenario();

        $o = $this->actingAs($this->admin(), 'api')->getJson('/api/accounting/overview?from=2026-03-01&to=2026-03-31');

        $o->assertStatus(200);
        $this->assertEquals(50000, $o->json('kpis.net_profit'));
        $this->assertEquals(100000, $o->json('kpis.income'));
        $this->assertEquals(30000, $o->json('kpis.net_profit_previous'));
        $this->assertCount(12, $o->json('trend'));
        $this->assertSame('2026-03', $o->json('trend.11.month'));
        $this->assertEquals(50000, $o->json('trend.11.net'));
        $this->assertEquals(30000, $o->json('trend.10.net'));
        $this->assertSame('5000', $o->json('expense_breakdown.0.code'), 'the biggest cost comes first');
        // bank: 500,000 + 40,000 - 10,000 + 100,000 - 30,000 - 12,000 - 8,000
        $this->assertEquals(580000, $o->json('position.cash_and_bank'));
    }

    public function testStatementEndpointsValidateTheirDatesAndPermissions()
    {
        $admin = $this->admin();
        $this->actingAs($admin, 'api')->getJson('/api/accounting/statements/profit-loss?from=2026-03-31&to=2026-03-01')->assertStatus(422);
        $this->actingAs($admin, 'api')->getJson('/api/accounting/statements/profit-loss?from=yesterday')->assertStatus(422);
        $this->actingAs($admin, 'api')->getJson('/api/accounting/statements/ledger')->assertStatus(422)->assertJsonValidationErrors('account_id');
        $this->actingAs($admin, 'api')->getJson('/api/accounting/statements/balance-sheet?as_at=nonsense')->assertStatus(422);

        $this->actingAs($this->staffWith(), 'api')->getJson('/api/accounting/statements/profit-loss')->assertStatus(403);
        $this->actingAs($this->staffWith('view accounting'), 'api')->getJson('/api/accounting/statements/profit-loss')->assertStatus(200);
    }
}
