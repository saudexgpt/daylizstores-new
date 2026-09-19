<?php

namespace Tests\Feature\Costing;

use App\Models\Accounting\JournalEntry;
use App\Models\Costing\CostLayer;
use App\Models\Stock\ItemStock;
use App\Services\Accounting\AccountingSettings;
use App\Services\Accounting\SalesPoster;
use App\Services\Costing\CostingSetup;
use App\Services\Costing\CostingSettings;

/**
 * Cost of sales in the books, the receive / adjust / go-live API, permissions, and privacy of costs.
 */
class CostingApiTest extends CostingTestCase
{
    private function sync(): void
    {
        app(SalesPoster::class)->sync();
    }

    private function cogsEntries()
    {
        return JournalEntry::where('source', 'cogs')->orderBy('entry_date')->orderBy('id')->get();
    }

    private function api($user = null)
    {
        return $this->actingAs($user ?? $this->admin(), 'api');
    }

    private function receiptPayload($item, array $override = []): array
    {
        return array_replace_recursive([
            'received_on' => '2026-03-02', 'supplier' => 'Acme Supplies', 'invoice_number' => 'INV-77',
            'extra_costs' => 100, 'extra_costs_note' => 'Freight', 'payment_account_id' => $this->acct('1010')->id,
            'lines' => [['item_id' => $item->id, 'size' => '42', 'color' => 'Black', 'quantity' => 10, 'unit_cost' => 500]],
        ], $override);
    }

    // ------------------------------------------------------------------ cost of sales in the books

    public function testCostOfSalesIsBookedPerDayAndOnlyTheDifferenceIsPostedAgain()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 4, '2026-03-10');

        $this->sync();
        $entries = $this->cogsEntries();
        $this->assertCount(1, $entries);
        $this->assertSame('2026-03-10', $entries[0]->entry_date->toDateString());
        $this->assertEquals(400, $entries[0]->total);
        $this->assertEquals(400, $this->balance('5000'), 'Dr Cost of Goods Sold');
        $this->assertEquals(600, $this->balance('1100'), 'Cr Inventory: 1,000 received less 400 sold');

        $this->sync();
        $this->assertCount(1, $this->cogsEntries(), 'running it again posts nothing');

        $this->sell($item, null, 2, '2026-03-10');   // another order the same day
        $this->sync();
        $this->assertCount(2, $this->cogsEntries());
        $this->assertEquals(600, $this->balance('5000'));
        $this->assertStringContainsString('Adjustment', $this->cogsEntries()[1]->description);
    }

    public function testAnOrderFromBeforeGoLiveIsBookedOnTheStartDate()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 3, '2026-02-20');   // placed before costing started, dispatched after

        $this->sync();
        $this->assertSame('2026-03-01', $this->cogsEntries()[0]->entry_date->toDateString());
        $this->assertEquals(300, $this->balance('5000'));
    }

    public function testACorrectionInAClosedPeriodIsDatedTodayNotBackdated()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 2, '2026-03-10');
        $this->sync();

        AccountingSettings::set(AccountingSettings::CLOSED, '2026-03-31');
        $this->sell($item, null, 1, '2026-03-10');   // a late dispatch for a closed day
        $this->sync();

        $entries = $this->cogsEntries();
        $this->assertCount(2, $entries);
        $this->assertSame('2026-03-10', $entries[0]->entry_date->toDateString(), 'the closed month did not change');
        $this->assertSame(now()->toDateString(), $entries[1]->entry_date->toDateString());
        $this->assertEquals(300, $this->balance('5000'));
    }

    public function testATruedUpShortfallFlowsThroughToCostOfSales()
    {
        $item = $this->product();
        $this->receive([[$item, '', 5, 100]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 5, '2026-03-10');
        $this->sell($item, null, 3, '2026-03-11');        // oversold: provisional 3 × 100
        $this->sync();
        $this->assertEquals(800, $this->balance('5000'));

        $this->receive([[$item, '', 10, 150]], 0, '1010', '2026-03-12');   // the real cost turns out to be 150
        $this->sync();

        $this->assertEquals(950, $this->balance('5000'), '500 + 3 × 150');
        $check = app(CostingSetup::class)->reconcile();
        $this->assertTrue($check['in_step']);
        $this->assertEquals($check['stock_value'], $check['books_value']);
    }

    public function testStockPurchasesCannotAlsoBeTypedInAsAnExpenseOnceCostingIsLive()
    {
        $payload = ['type' => 'expense', 'date' => '2026-03-10', 'amount' => 5000, 'description' => 'Bought stock',
            'account_id' => $this->acct('5000')->id, 'payment_account_id' => $this->acct('1010')->id];

        $this->api()->postJson('/api/accounting/transactions', $payload)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Receive stock'));
        $this->assertSame(0, JournalEntry::where('type', 'expense')->count(), 'nothing was booked');

        // an ordinary expense is untouched, and before go-live the old way still works
        $this->api()->postJson('/api/accounting/transactions', array_merge($payload, ['account_id' => $this->acct('6100')->id]))->assertStatus(201);
        $this->costingOff();
        $this->api()->postJson('/api/accounting/transactions', $payload)->assertStatus(201);
    }

    public function testTheBooksListDoesNotLetSystemEntriesBeEditedOrVoided()
    {
        $item = $this->product();
        $receipt = $this->receive([[$item, '', 10, 100]], 0, '1010');
        $this->sell($item, null, 2, '2026-03-10');
        $this->sync();
        $cogs = $this->cogsEntries()[0];

        // the delivery's journal: void it from Receive stock, never from the transactions list
        $this->api()->postJson('/api/accounting/transactions/' . $receipt->journal_entry_id . '/void', ['reason' => 'trying it'])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Receive stock'));
        $this->api()->postJson('/api/accounting/transactions/' . $cogs->id . '/void', ['reason' => 'trying it'])->assertStatus(422);
        $this->api()->putJson('/api/accounting/transactions/' . $receipt->journal_entry_id, ['type' => 'journal', 'date' => '2026-03-02', 'description' => 'x', 'lines' => [
            ['account_id' => $this->acct('1100')->id, 'debit' => 5], ['account_id' => $this->acct('1010')->id, 'credit' => 5],
        ]])->assertStatus(422);

        $list = $this->api()->getJson('/api/accounting/transactions?source=stock&from=2026-03-01&to=2026-03-31')->assertStatus(200);
        $this->assertCount(1, $list->json('transactions'));
        $this->assertFalse($list->json('transactions.0.editable'));
        $sales = $this->api()->getJson('/api/accounting/transactions?source=sales&from=2026-03-01&to=2026-03-31');
        $this->assertContains('SAL', array_map(fn ($t) => substr($t['reference'], 0, 3), $sales->json('transactions')));
        $this->assertFalse($sales->json('transactions.0.editable'));
        $this->assertNotNull(JournalEntry::find($receipt->journal_entry_id)->where('status', 'posted')->first());
    }

    // ------------------------------------------------------------------ receive stock API

    public function testReceivingStockThroughTheApi()
    {
        $item = $this->product();
        $manager = $this->staffWith('create menu');

        $r = $this->api($manager)->postJson('/api/costing/receipts', $this->receiptPayload($item))->assertStatus(201);
        $this->assertSame('Acme Supplies', $this->api()->getJson('/api/costing/receipts/' . $r->json('receipt.id'))->json('receipt.supplier'));
        $this->assertSame(10, ItemStock::where(['item_id' => $item->id, 'size' => '42'])->value('quantity_stocked'));
        $this->assertEquals(5100, $r->json('receipt.landed_total'), '10 × 500 + 100 freight');
        $this->assertEquals(5100, $this->layers($item, '42')[0]->cost_total);
    }

    public function testReceivingIsRefusedForBadInput()
    {
        $item = $this->product();
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item, ['lines' => [['unit_cost' => 0]]]))->assertStatus(422);
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item, ['received_on' => now()->addDays(2)->toDateString()]))->assertStatus(422);
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item, ['lines' => [['item_id' => 99999]]]))->assertStatus(422);
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item, ['supplier' => '']))->assertStatus(422);
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item, ['payment_account_id' => $this->acct('6100')->id]))->assertStatus(422);
        $this->assertSame(0, ItemStock::count());
        $this->assertSame(0, CostLayer::count());

        $this->costingOff();
        $this->api()->postJson('/api/costing/receipts', $this->receiptPayload($item))->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'not been switched on'));
    }

    public function testWhoMayReceiveSeeCostsAndVoid()
    {
        $item = $this->product();
        $this->getJson('/api/costing/receipts')->assertStatus(401);
        $this->api($this->createCustomerUser())->postJson('/api/costing/receipts', $this->receiptPayload($item))->assertStatus(403);
        $this->api($this->createStaffUser())->postJson('/api/costing/receipts', $this->receiptPayload($item))->assertStatus(403);

        $manager = $this->staffWith('create menu');
        $id = $this->api($manager)->postJson('/api/costing/receipts', $this->receiptPayload($item))->assertStatus(201)->json('receipt.id');

        // recording a delivery does not let you read what things cost, or undo one
        $this->api($manager)->getJson('/api/costing/receipts')->assertStatus(403);
        $this->api($manager)->getJson('/api/costing/receipts/' . $id)->assertStatus(403);
        $this->api($manager)->postJson('/api/costing/receipts/' . $id . '/void', ['reason' => 'mistake'])->assertStatus(403);

        $viewer = $this->staffWith('view cost');
        $list = $this->api($viewer)->getJson('/api/costing/receipts')->assertStatus(200);
        $this->assertEquals(5100, $list->json('receipts.0.landed_total'));
        $show = $this->api($viewer)->getJson('/api/costing/receipts/' . $id)->assertStatus(200);
        $this->assertEquals(510, $show->json('receipt.lines.0.landed_unit_cost'), '(10 × 500 + 100) / 10');

        $accountant = $this->staffWith('manage accounting');
        $this->api($accountant)->postJson('/api/costing/receipts/' . $id . '/void', ['reason' => 'entered twice'])->assertStatus(200);
        $this->api($accountant)->postJson('/api/costing/receipts/' . $id . '/void', ['reason' => 'again'])->assertStatus(422);
    }

    public function testProductLookupShowsSizesAndOnlyShowsCostsToThoseWhoMaySeeThem()
    {
        $item = $this->product('Runner Pro');
        $this->receive([[$item, '42', 5, 700, 'Black']], 0, '1010');
        $this->stockRow($item, '43', 'Red');

        $plain = $this->api($this->staffWith('create menu'))->getJson('/api/costing/receipts/products?q=Runner')->assertStatus(200);
        $this->assertSame(['42', '43'], $plain->json('products.0.sizes'));
        $this->assertSame(['Black', 'Red'], $plain->json('products.0.colors'));
        $this->assertNull($plain->json('products.0.last_cost'));

        $both = $this->api($this->staffWith('create menu', 'view cost'))->getJson('/api/costing/receipts/products?q=Runner');
        $this->assertEquals(700, $both->json('products.0.last_cost.42'));
    }

    public function testPeopleWhoReceiveStockCanSeeWhichAccountsToPayFrom()
    {
        $r = $this->api($this->staffWith('create menu'))->getJson('/api/costing/payment-accounts')->assertStatus(200);
        $codes = collect($r->json('accounts'))->pluck('code')->all();
        $this->assertContains('1010', $codes);
        $this->assertContains('1000', $codes);
        $this->assertNotContains('6100', $codes, 'only bank / cash accounts');
        $this->assertNotContains('2000', $codes);
        $this->api($this->createStaffUser())->getJson('/api/costing/payment-accounts')->assertStatus(403);
    }

    public function testProductLookupListsShelfRowsWithWhatIsFreeToAdjust()
    {
        $item = $this->product('Lookup Bag');
        $this->receive([[$item, '42', 10, 100, 'Black']], 0, '1010');
        ItemStock::where('item_id', $item->id)->update(['reserved' => 3]);

        $r = $this->api($this->staffWith('create menu'))->getJson('/api/costing/receipts/products?q=Lookup')->assertStatus(200);
        $this->assertSame(7, $r->json('products.0.stocks.0.available'), '10 stocked, 3 reserved');
        $this->assertSame('Black', $r->json('products.0.stocks.0.color'));
    }

    public function testTheOldStockUpIsClosedOnceCostingIsLive()
    {
        $item = $this->product();
        $payload = ['sub_batches' => [['quantity' => 5, 'size' => '42']]];

        $this->api()->putJson('/api/stock/general-items/stockup/' . $item->id, $payload)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Receive stock'));
        $this->assertSame(0, ItemStock::count());

        $this->costingOff();
        $this->api()->putJson('/api/stock/general-items/stockup/' . $item->id, $payload)->assertStatus(200);
        $this->assertSame(5, ItemStock::where('item_id', $item->id)->value('quantity_stocked'));
    }

    // ------------------------------------------------------------------ adjustments API

    public function testAdjustmentsThroughTheApiHideCostsFromThoseWhoMayNotSeeThem()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        $stock = ItemStock::where('item_id', $item->id)->first();
        $manager = $this->staffWith('create menu');

        $this->api($manager)->postJson('/api/costing/adjustments', ['item_stock_id' => $stock->id, 'quantity' => -2, 'reason' => 'damage', 'adjusted_on' => '2026-03-09', 'note' => 'water damage'])->assertStatus(201);
        $this->api($manager)->postJson('/api/costing/adjustments', ['item_stock_id' => $stock->id, 'quantity' => 0, 'reason' => 'damage', 'adjusted_on' => '2026-03-09'])->assertStatus(422);
        $this->api($manager)->postJson('/api/costing/adjustments', ['item_stock_id' => $stock->id, 'quantity' => -1, 'reason' => 'because', 'adjusted_on' => '2026-03-09'])->assertStatus(422);
        $this->api($this->createStaffUser())->postJson('/api/costing/adjustments', ['item_stock_id' => $stock->id, 'quantity' => -1, 'reason' => 'loss', 'adjusted_on' => '2026-03-09'])->assertStatus(403);

        $plain = $this->api($manager)->getJson('/api/costing/adjustments')->assertStatus(200);
        $this->assertSame(-2, $plain->json('adjustments.0.quantity'));
        $this->assertNull($plain->json('adjustments.0.cost'));
        $seen = $this->api($this->staffWith('create menu', 'view cost'))->getJson('/api/costing/adjustments');
        $this->assertEquals(200, $seen->json('adjustments.0.cost'));
    }

    // ------------------------------------------------------------------ the cut-over

    private function shelfWithStock(): array
    {
        $runner = $this->product('Runner');
        $tote = $this->product('Tote');
        $this->stockRow($runner, '42', 'Black', 10, 2);   // 8 on the shelf
        $this->stockRow($runner, '42', 'White', 4, 0);    // + 4 of the same size: 12
        $this->stockRow($tote, null, null, 5, 0);

        return [$runner, $tote];
    }

    private function costSheet($runner, $tote, $runnerCost = 100, $toteCost = 200): array
    {
        return [
            ['item_id' => $runner->id, 'size' => '42', 'unit_cost' => $runnerCost],
            ['item_id' => $tote->id, 'size' => '', 'unit_cost' => $toteCost],
        ];
    }

    public function testTheShelfListsWhatTheSystemHoldsPerProductAndSize()
    {
        $this->costingOff();
        [$runner, $tote] = $this->shelfWithStock();

        $shelf = $this->api()->getJson('/api/costing/shelf')->assertStatus(200);
        $this->assertSame(17, $shelf->json('units'));
        $rows = collect($shelf->json('rows'));
        $this->assertSame(12, $rows->firstWhere('item_id', $runner->id)['qty'], 'colours of a size are added together');
        $this->assertSame(5, $rows->firstWhere('item_id', $tote->id)['qty']);
    }

    public function testACostSheetIsCheckedBeforeAnythingChanges()
    {
        $this->costingOff();
        [$runner, $tote] = $this->shelfWithStock();

        $missing = $this->api()->postJson('/api/costing/check', ['rows' => [['item_id' => $runner->id, 'size' => '42', 'unit_cost' => 100]]])->assertStatus(200);
        $this->assertFalse($missing->json('ok'));
        $this->assertSame(1, $missing->json('missing_count'));
        $this->assertSame($tote->id, $missing->json('missing.0.item_id'));

        $bad = $this->api()->postJson('/api/costing/check', ['rows' => array_merge($this->costSheet($runner, $tote), [['item_id' => $runner->id, 'size' => '43', 'unit_cost' => 'abc']])]);
        $this->assertSame(1, $bad->json('invalid_count'));
        $this->assertFalse($bad->json('ok'));

        $good = $this->api()->postJson('/api/costing/check', ['rows' => $this->costSheet($runner, $tote)]);
        $this->assertTrue($good->json('ok'));
        $this->assertSame(17, $good->json('units'));
        $this->assertEquals(12 * 100 + 5 * 200, $good->json('value'));
    }

    public function testGoingLiveValuesTheShelfAndOpensTheBooks()
    {
        $this->costingOff();
        [$runner, $tote] = $this->shelfWithStock();
        $today = now()->toDateString();

        // an incomplete cost sheet changes nothing
        $this->api()->postJson('/api/costing/go-live', ['start_date' => $today, 'rows' => [['item_id' => $runner->id, 'size' => '42', 'unit_cost' => 100]]])->assertStatus(422);
        $this->assertFalse(CostingSettings::enabled());
        $this->assertSame(0, CostLayer::count());
        $this->assertEquals(0, $this->balance('1100'));

        $r = $this->api()->postJson('/api/costing/go-live', ['start_date' => $today, 'rows' => $this->costSheet($runner, $tote)])->assertStatus(200);
        $this->assertSame(2, $r->json('layers'));
        $this->assertEquals(2200, $r->json('value'));

        $this->assertTrue(CostingSettings::enabled());
        $this->assertSame($today, CostingSettings::startDate());
        $this->assertSame([12], $this->layers($runner, '42')->pluck('qty_remaining')->all());
        $this->assertEquals(1200, $this->layers($runner, '42')[0]->cost_remaining);
        $this->assertEquals(2200, $this->balance('1100'));
        $this->assertEquals(-2200, $this->balance('3900'), 'Cr Opening Balance Equity');
        $opening = JournalEntry::where('source', 'opening_stock')->first();
        $this->assertSame(now()->subDay()->toDateString(), $opening->entry_date->toDateString(), 'dated the day before costing starts');

        $check = $this->api()->getJson('/api/costing/reconcile')->assertStatus(200);
        $this->assertTrue($check->json('in_step'));

        // from now on: FIFO from the opening layer, and the plain stock-up is closed
        $this->assertEquals(300, $this->sell($runner, '42', 3)->cost_total);
        $this->api()->putJson('/api/stock/general-items/stockup/' . $tote->id, ['sub_batches' => [['quantity' => 1]]])->assertStatus(422);
        $this->api()->postJson('/api/costing/go-live', ['start_date' => $today, 'rows' => $this->costSheet($runner, $tote)])->assertStatus(422);
    }

    public function testTheCutOverHasGuardrails()
    {
        $this->costingOff();
        [$runner, $tote] = $this->shelfWithStock();
        $rows = $this->costSheet($runner, $tote);

        $this->api()->postJson('/api/costing/go-live', ['start_date' => now()->addDay()->toDateString(), 'rows' => $rows])->assertStatus(422);
        $this->api()->postJson('/api/costing/go-live', ['start_date' => '2026-01-01', 'rows' => $rows])->assertStatus(422);   // not after the books start
        $this->api()->postJson('/api/costing/go-live', ['start_date' => '01/10/2026', 'rows' => $rows])->assertStatus(422);
        $this->api()->postJson('/api/costing/go-live', ['start_date' => now()->toDateString(), 'rows' => $this->costSheet($runner, $tote, 0)])->assertStatus(422);

        // only whoever may manage the books can do it
        $this->api($this->staffWith('create menu'))->postJson('/api/costing/go-live', ['start_date' => now()->toDateString(), 'rows' => $rows])->assertStatus(403);
        $this->api($this->staffWith('view cost'))->getJson('/api/costing/shelf')->assertStatus(403);
        $this->assertFalse(CostingSettings::enabled());
    }

    public function testAnyScreenCanLearnWhetherCostingIsLive()
    {
        $this->api($this->staffWith('create menu'))->getJson('/api/costing/state')->assertStatus(200)->assertJsonPath('enabled', true);
        $this->api($this->createStaffUser())->getJson('/api/costing/state')->assertStatus(403);
        $this->costingOff();
        $this->api()->getJson('/api/costing/state')->assertJsonPath('enabled', false);
    }

    // ------------------------------------------------------------------ privacy

    public function testWhatAProductCostNeverLeavesInAnOrder()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        $line = $this->sell($item, null, 2);
        $this->assertEquals(200, $line->cost_total, 'the cost is stored');

        $this->assertArrayNotHasKey('cost_total', $line->toArray());
        $this->assertArrayNotHasKey('costed_at', $line->toArray());
        $this->assertStringNotContainsString('cost_total', $line->order->load('orderItems')->toJson());
    }
}
