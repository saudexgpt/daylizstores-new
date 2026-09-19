<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingReports;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\SalesPoster;
use Illuminate\Support\Facades\Artisan;

/**
 * Sales reach the books as a daily summary of PAID, NOT-CANCELLED orders, and re-syncing must
 * never double count.
 */
class SalesPosterTest extends AccountingTestCase
{
    private function poster(): SalesPoster
    {
        return app(SalesPoster::class);
    }

    private function salesOn(string $from, string $to): float
    {
        return app(AccountingReports::class)->profitAndLoss($from, $to, false)['income']['total'];
    }

    private function bankBalance(): float
    {
        $t = app(AccountingReports::class)->totals(null, '2099-12-31', ['asset'])->firstWhere('code', '1010');

        return $t ? ($t->debit_kobo - $t->credit_kobo) / 100 : 0;
    }

    public function testOnlyPaidNonCancelledOrdersFromTheStartDateAreCountedAndBankMatches()
    {
        $this->order(10000, '2026-03-10');                       // counts
        $this->order(2500.50, '2026-03-10');                     // counts (same day → one summary)
        $this->order(4000, '2026-03-11', 'pending', 'Pending');  // unpaid
        $this->order(6000, '2026-03-11', 'paid', 'Cancelled');   // paid but cancelled
        $this->order(7000, '2025-12-31');                        // before the books start

        $result = $this->poster()->sync();

        $this->assertSame(1, $result['entries'], 'one summary, for the one day that has sales');
        $entry = JournalEntry::where('source', 'sales')->firstOrFail();
        $this->assertSame('2026-03-10', $entry->entry_date->toDateString());
        $this->assertSame('SAL', substr($entry->reference, 0, 3));
        $this->assertStringContainsString('2 orders', $entry->description);
        $this->assertEquals(12500.50, $this->salesOn('2026-01-01', '2026-12-31'));
        $this->assertEquals(12500.50, $this->bankBalance(), 'the bank is debited by exactly what revenue is credited');
    }

    public function testDeliveryIsSeparatedFromProductSales()
    {
        $this->order(11000, '2026-03-10', 'paid', 'Delivered', 1000);

        $this->poster()->sync();

        $pl = app(AccountingReports::class)->profitAndLoss('2026-03-01', '2026-03-31', false);
        $lines = collect($pl['income']['lines'])->keyBy('code');
        $this->assertEquals(10000, $lines['4000']['amount']);
        $this->assertEquals(1000, $lines['4100']['amount']);
        $this->assertEquals(11000, $pl['income']['total']);
    }

    public function testSyncingTwiceDoesNotDoubleCount()
    {
        $this->order(5000, '2026-03-10');

        $first = $this->poster()->sync();
        $second = $this->poster()->sync();
        Artisan::call('accounting:post-sales');

        $this->assertSame(1, $first['entries']);
        $this->assertSame(0, $second['entries']);
        $this->assertSame(1, JournalEntry::where('source', 'sales')->count());
        $this->assertEquals(5000, $this->salesOn('2026-03-01', '2026-03-31'));
    }

    public function testACancelledOrderIsCorrectedByAnAdjustingEntryNotByRewritingHistory()
    {
        $order = $this->order(5000, '2026-03-10');
        $this->order(1000, '2026-03-10');
        $this->poster()->sync();
        $original = JournalEntry::where('source', 'sales')->first();

        $order->order_status = 'Cancelled';
        $order->save();
        $result = $this->poster()->sync();

        $this->assertSame(1, $result['entries']);
        $this->assertEquals(-5000, $result['net_adjustment']);
        $this->assertSame(2, JournalEntry::where('source', 'sales')->count());
        $this->assertSame('posted', $original->fresh()->status, 'the original summary is untouched');
        $adjustment = JournalEntry::where('source', 'sales')->where('id', '!=', $original->id)->first();
        $this->assertStringContainsString('Adjustment', $adjustment->description);
        $this->assertEquals(1000, $this->salesOn('2026-03-01', '2026-03-31'));
        $this->assertEquals(1000, $this->bankBalance());

        // and a third sync has nothing left to do
        $this->assertSame(0, $this->poster()->sync()['entries']);
    }

    public function testAnOrderPaidLaterAddsAnAdjustmentToItsOrderDate()
    {
        $late = $this->order(3000, '2026-03-10', 'pending', 'Pending');
        $this->order(2000, '2026-03-10');
        $this->poster()->sync();
        $this->assertEquals(2000, $this->salesOn('2026-03-01', '2026-03-31'));

        $late->payment_status = 'paid';
        $late->save();
        $this->assertSame(1, $this->poster()->sync()['entries']);

        $this->assertEquals(5000, $this->salesOn('2026-03-01', '2026-03-31'));
    }

    public function testAdjustingAClosedDayIsPostedTodayAndTheClosedPeriodDoesNotChange()
    {
        $order = $this->order(4000, '2026-02-10');
        $this->poster()->sync();
        AccountingSettings::set(AccountingSettings::CLOSED, '2026-02-28');
        $februaryBefore = $this->salesOn('2026-02-01', '2026-02-28');

        $order->order_status = 'Cancelled';
        $order->save();
        $this->poster()->sync();

        $this->assertEquals($februaryBefore, $this->salesOn('2026-02-01', '2026-02-28'), 'February is closed and must not move');
        $adjustment = JournalEntry::where('source', 'sales')->orderByDesc('id')->first();
        $this->assertSame(now()->toDateString(), $adjustment->entry_date->toDateString());
        $this->assertEquals(-4000, $this->salesOn(now()->startOfMonth()->toDateString(), now()->toDateString()));
    }

    public function testMovingTheStartDateEarlierBringsOlderSalesIntoTheBooks()
    {
        AccountingSettings::set(AccountingSettings::START, '2026-03-01');
        $this->order(1000, '2026-02-15');
        $this->order(2000, '2026-03-15');
        $this->poster()->sync();
        $this->assertEquals(2000, $this->salesOn('2025-01-01', '2026-12-31'));

        $this->actingAs($this->admin(), 'api')->putJson('/api/accounting/settings', ['books_start_date' => '2026-01-01'])->assertStatus(200);

        $this->assertEquals(3000, $this->salesOn('2025-01-01', '2026-12-31'));
    }

    public function testRevenueMatchesTheDashboardsDefinitionOfRevenue()
    {
        $this->order(1234.56, '2026-03-10');
        $this->order(99, '2026-03-10', 'paid', 'Cancelled');
        $this->order(50, '2026-03-10', 'pending', 'Pending');
        $this->poster()->sync();

        $fromOrders = \App\Models\Order\Order::query()->revenue()->sum('total');
        $this->assertEquals((float) $fromOrders, $this->salesOn('2026-01-01', '2026-12-31'));
    }

    public function testTheSyncEndpointIsPermissionedAndReportsWhatItDid()
    {
        $this->order(700, '2026-03-10');

        $this->actingAs($this->staffWith('view accounting'), 'api')->postJson('/api/accounting/sync-sales')->assertStatus(403);
        $this->actingAs($this->admin(), 'api')->postJson('/api/accounting/sync-sales')
            ->assertStatus(200)->assertJsonPath('sync.entries', 1);
    }

    public function testStatementsBringSalesUpToDateAutomatically()
    {
        $this->order(900, now()->subDays(2)->toDateString());

        $pl = $this->actingAs($this->admin(), 'api')->getJson('/api/accounting/statements/profit-loss?from=' . now()->startOfYear()->toDateString() . '&to=' . now()->toDateString());

        $pl->assertStatus(200);
        $this->assertEquals(900, $pl->json('income.total'), 'no manual sync was needed');
    }
}
