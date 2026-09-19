<?php

namespace Tests\Feature\Costing;

use App\Models\Costing\CostConsumption;
use App\Models\Costing\StockAdjustment;
use App\Models\Order\OrderItem;
use App\Models\Stock\ItemStock;
use App\Services\Accounting\LedgerException;
use App\Services\Costing\CostingSetup;

/**
 * The costing rules, with every number worked out by hand: FIFO order, landed cost, kobo-exact
 * rounding, oversold shortfall, adjustments, and the guarantee that the books and the shelf agree.
 */
class CostingTest extends CostingTestCase
{
    // ------------------------------------------------------------------ receiving

    public function testAReceiptRaisesStockCreatesALayerAndBooksInventory()
    {
        $item = $this->product();
        $receipt = $this->receive([[$item, '42', 10, 1000, 'Black'], [$item, '42', 5, 1000, 'White']], 0, '1010');

        $this->assertSame('RCV-' . str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT), $receipt->reference);
        $this->assertEquals(15000, $receipt->landed_total);

        // the colours are separate shelf rows; the size has ONE layer holding both
        $this->assertSame(10, ItemStock::where(['item_id' => $item->id, 'size' => '42', 'color' => 'Black'])->value('quantity_stocked'));
        $this->assertSame(5, ItemStock::where(['item_id' => $item->id, 'size' => '42', 'color' => 'White'])->value('quantity_stocked'));
        $layers = $this->layers($item, '42');
        $this->assertCount(1, $layers);
        $this->assertSame(15, $layers[0]->qty_remaining);
        $this->assertEquals(15000, $layers[0]->cost_remaining);

        // Dr Inventory, Cr the bank it was paid from
        $this->assertEquals(15000, $this->balance('1100'));
        $this->assertEquals(-15000, $this->balance('1010'));
    }

    public function testAnUnpaidDeliveryIsOwedToTheSupplier()
    {
        $item = $this->product();
        $this->receive([[$item, '', 4, 2500]]);   // no payment account: bought on credit

        $this->assertEquals(10000, $this->balance('1100'));
        $this->assertEquals(-10000, $this->balance('2000'), 'Accounts Payable is credited');
    }

    public function testExtraCostsAreSpreadByValueAndTheTotalIsExact()
    {
        $a = $this->product('A');
        $b = $this->product('B');
        // 10,000 of A and 10,000 of B, plus 1,000 freight: 500 each
        $r = $this->receive([[$a, 'M', 10, 1000], [$b, 'L', 5, 2000]], 1000, '1010');
        $this->assertEquals(10500, $this->layers($a, 'M')[0]->cost_total);
        $this->assertEquals(10500, $this->layers($b, 'L')[0]->cost_total);
        $this->assertEquals(21000, $r->landed_total);
        $this->assertEquals(21000, $this->balance('1100'));

        // 100.01 across three equal lines cannot divide into kobo: 33.33 + 33.33 + 33.35, never lost
        $c = $this->product('C');
        $d = $this->product('D');
        $e = $this->product('E');
        $this->receive([[$c, '', 1, 100], [$d, '', 1, 100], [$e, '', 1, 100]], 100.01, '1010');
        $sum = $this->layers($c)->sum('cost_total') + $this->layers($d)->sum('cost_total') + $this->layers($e)->sum('cost_total');
        $this->assertEquals(400.01, round($sum, 2), 'items 300.00 + extras 100.01, to the kobo');
    }

    public function testDeliveryValidation()
    {
        $item = $this->product();
        $bad = function (array $line, string $expect, float $extra = 0, ?int $account = null) use ($item) {
            try {
                $this->costing()->receive(['received_on' => '2026-03-02', 'supplier' => 'S', 'extra_costs' => $extra, 'payment_account_id' => $account, 'lines' => [$line + ['item_id' => $item->id]]]);
                $this->fail("expected: $expect");
            } catch (LedgerException $e) {
                $this->assertStringContainsString($expect, $e->getMessage());
            }
        };
        $bad(['quantity' => 5, 'unit_cost' => 0], 'cannot be zero');
        $bad(['quantity' => 0, 'unit_cost' => 10], 'at least 1');
        $bad(['quantity' => 5, 'unit_cost' => 10], 'bank or cash', 0, $this->acct('6100')->id);   // an expense account is not somewhere money comes from

        try {   // one product+size cannot carry two costs in one delivery
            $this->costing()->receive(['received_on' => '2026-03-02', 'supplier' => 'S', 'lines' => [
                ['item_id' => $item->id, 'size' => 'M', 'color' => 'Red', 'quantity' => 1, 'unit_cost' => 10],
                ['item_id' => $item->id, 'size' => 'M', 'color' => 'Blue', 'quantity' => 1, 'unit_cost' => 11],
            ]]);
            $this->fail('two costs for one size');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('share one cost', $e->getMessage());
        }
        $this->assertSame(0, ItemStock::where('item_id', $item->id)->count(), 'a rejected delivery leaves no trace');
    }

    // ------------------------------------------------------------------ FIFO

    public function testSalesTakeTheOldestLayerFirst()
    {
        $item = $this->product();
        $this->receive([[$item, 'M', 10, 100]], 0, '1010', '2026-03-02');   // older, cheaper
        $this->receive([[$item, 'M', 10, 200]], 0, '1010', '2026-03-05');   // newer, dearer

        $line = $this->sell($item, 'M', 15);   // 10 × 100 + 5 × 200
        $this->assertEquals(2000, $line->cost_total);
        $this->assertNotNull($line->costed_at);
        $layers = $this->layers($item, 'M');
        $this->assertSame([0, 5], $layers->pluck('qty_remaining')->all());
        $this->assertEquals(1000, $layers[1]->cost_remaining);
        $this->assertCount(2, CostConsumption::where('order_item_id', $line->id)->get(), 'the line remembers both layers it drew from');

        $second = $this->sell($item, 'M', 5);   // the rest of the newer layer
        $this->assertEquals(1000, $second->cost_total);
        $this->assertSame([0, 0], $this->layers($item, 'M')->pluck('qty_remaining')->all());
    }

    public function testCostIsFrozenWhenLaterDeliveriesCostMore()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010', '2026-03-02');
        $line = $this->sell($item, null, 4);
        $this->receive([[$item, '', 10, 999]], 0, '1010', '2026-03-20');

        $this->assertEquals(400, $line->fresh()->cost_total, 'a dearer delivery later does not rewrite what earlier sales cost');
    }

    public function testACostThatDoesNotDivideIntoKoboIsSplitWithoutLosingAKobo()
    {
        $item = $this->product();
        // 3 × 33.33 + 0.01 extra = 100.00 for 3 units: 33.33 + 33.34 + 33.33
        $this->receive([[$item, '', 3, 33.33]], 0.01, '1010');
        $costs = [];
        for ($i = 0; $i < 3; $i++) {
            $costs[] = (float) $this->sell($item, null, 1)->cost_total;
        }
        $this->assertEquals(100.00, round(array_sum($costs), 2), 'what was sold adds up to exactly what was paid');
        $layer = $this->layers($item)[0];
        $this->assertSame(0, $layer->qty_remaining);
        $this->assertEquals(0, $layer->cost_remaining);
    }

    public function testDifferentSizesAreCostedSeparately()
    {
        $item = $this->product();
        $this->receive([[$item, 'S', 10, 100], [$item, 'XL', 10, 300]], 0, '1010');

        $this->assertEquals(300, $this->sell($item, 'S', 3)->cost_total);
        $this->assertEquals(900, $this->sell($item, 'XL', 3)->cost_total);
    }

    public function testCancellingAnOrderNeverTouchesTheLayers()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        $stock = ItemStock::where('item_id', $item->id)->first();

        $order = $this->order(5000, '2026-03-10', 'paid', 'Pending');
        $line = new OrderItem();
        $line->order_id = $order->id;
        $line->stock_id = $stock->id;
        $line->item_id = $item->id;
        $line->product_name = 'Runner';
        $line->quantity = 3;
        $line->price = 5000;
        $line->total = 15000;
        $line->save();
        $stock->increment('reserved', 3);

        $this->actingAs($this->admin(), 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Cancelled'])->assertStatus(200);

        $this->assertNull($line->fresh()->costed_at);
        $this->assertSame(10, $this->layers($item)[0]->qty_remaining);
    }

    public function testNothingIsCostedWhileCostingIsOff()
    {
        $this->costingOff();
        $item = $this->product();
        $this->stockRow($item, null, null, 10);

        $line = $this->sell($item, null, 2);
        $this->assertNull($line->costed_at);
        $this->assertNull($line->cost_total);
        $this->assertSame(0, \DB::table('cost_consumptions')->count());
    }

    // ------------------------------------------------------------------ oversold

    public function testSellingMoreThanTheLayersHoldIsCostedProvisionallyThenTruedUp()
    {
        $item = $this->product();
        $this->receive([[$item, '', 5, 100]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 5);                       // uses the whole layer

        $line = $this->sell($item, null, 3);               // 3 more, with nothing left: sold anyway
        $this->assertEquals(300, $line->cost_total, 'costed at the latest known cost (100) for now');
        $this->assertTrue((bool) CostConsumption::where('order_item_id', $line->id)->value('provisional'));
        $this->assertSame(3, (int) \DB::table('cost_consumptions')->where('provisional', true)->sum('quantity'));

        $this->receive([[$item, '', 10, 150]], 0, '1010', '2026-03-12');   // the delivery that was missing

        $this->assertEquals(450, $line->fresh()->cost_total, 'trued up to what those 3 units really cost (3 × 150)');
        $this->assertSame(0, \DB::table('cost_consumptions')->where('provisional', true)->count());
        $this->assertSame(7, $this->layers($item)->last()->qty_remaining, 'and the new layer is drawn down by them');
    }

    // ------------------------------------------------------------------ adjustments

    public function testALossTakesStockOffAtFifoCostAndBooksTheShrinkage()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        $stock = ItemStock::where('item_id', $item->id)->first();

        $adj = $this->costing()->adjust(['item_stock_id' => $stock->id, 'quantity' => -2, 'reason' => 'damage', 'adjusted_on' => '2026-03-08'], $this->admin()->id);

        $this->assertEquals(200, $adj->cost);
        $this->assertSame(8, $stock->fresh()->quantity_stocked);
        $this->assertSame(8, $this->layers($item)[0]->qty_remaining);
        $this->assertEquals(200, $this->balance('6960'), 'Stock Loss & Damages is debited');
        $this->assertEquals(800, $this->balance('1100'));
    }

    public function testAdjustmentsCannotTakeStockThatIsReservedForOrders()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        $stock = ItemStock::where('item_id', $item->id)->first();
        $stock->reserved = 8;
        $stock->save();

        $this->expectException(LedgerException::class);
        $this->expectExceptionMessage('Only 2 unit(s) are free');
        $this->costing()->adjust(['item_stock_id' => $stock->id, 'quantity' => -3, 'reason' => 'loss', 'adjusted_on' => '2026-03-08']);
    }

    public function testStockFoundNeedsACostAndBecomesALayer()
    {
        $item = $this->product();
        $this->receive([[$item, '', 4, 100]], 0, '1010');
        $stock = ItemStock::where('item_id', $item->id)->first();

        try {
            $this->costing()->adjust(['item_stock_id' => $stock->id, 'quantity' => 3, 'reason' => 'count_difference', 'adjusted_on' => '2026-03-08']);
            $this->fail('a gain with no cost');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('unit cost', $e->getMessage());
        }
        $this->assertSame(4, $stock->fresh()->quantity_stocked, 'the refused adjustment left the shelf alone');

        $this->costing()->adjust(['item_stock_id' => $stock->id, 'quantity' => 3, 'reason' => 'count_difference', 'adjusted_on' => '2026-03-08', 'unit_cost' => 120]);
        $this->assertSame(7, $stock->fresh()->quantity_stocked);
        $this->assertSame([4, 3], $this->layers($item)->pluck('qty_remaining')->all());
        $this->assertEquals(760, $this->balance('1100'), '400 + 3 × 120');
    }

    public function testBeforeGoLiveAnAdjustmentOnlyMovesTheQuantity()
    {
        $this->costingOff();
        $item = $this->product();
        $stock = $this->stockRow($item, 'M', null, 10);

        $adj = $this->costing()->adjust(['item_stock_id' => $stock->id, 'quantity' => -4, 'reason' => 'count_difference', 'adjusted_on' => '2026-03-08']);
        $this->assertSame(6, $stock->fresh()->quantity_stocked);
        $this->assertEquals(0, $adj->cost);
        $this->assertNull($adj->journal_entry_id);
        $this->assertSame(0, \DB::table('cost_layers')->count());
    }

    // ------------------------------------------------------------------ voiding a delivery

    public function testAnUntouchedDeliveryCanBeVoidedAndASoldOneCannot()
    {
        $item = $this->product();
        $r1 = $this->receive([[$item, '', 10, 100]], 0, '1010');
        $this->costing()->voidReceipt($r1, 'entered against the wrong supplier', $this->admin()->id);

        $this->assertSame('void', $r1->fresh()->status);
        $this->assertSame(0, ItemStock::where('item_id', $item->id)->first()->quantity_stocked);
        $this->assertSame(0, $this->layers($item)[0]->qty_remaining);
        $this->assertEquals(0, $this->balance('1100'), 'the inventory journal is reversed');
        $this->assertEquals(0, $this->balance('1010'));

        $r2 = $this->receive([[$item, '', 10, 100]], 0, '1010');
        $this->sell($item, null, 1);
        try {
            $this->costing()->voidReceipt($r2, 'oops', $this->admin()->id);
            $this->fail('a delivery that has been partly sold');
        } catch (LedgerException $e) {
            $this->assertStringContainsString('already been sold', $e->getMessage());
        }
        $this->assertSame('posted', $r2->fresh()->status);
    }

    // ------------------------------------------------------------------ the books and the shelf agree

    public function testInventoryInTheBooksEqualsTheLayersAfterEverything()
    {
        $item = $this->product();
        $other = $this->product('Tote');
        $this->receive([[$item, 'M', 20, 100], [$other, '', 10, 50]], 30, '1010', '2026-03-02');
        $this->sell($item, 'M', 7, '2026-03-10');
        $this->sell($other, null, 4, '2026-03-11');
        $this->costing()->adjust(['item_stock_id' => ItemStock::where('item_id', $item->id)->first()->id, 'quantity' => -2, 'reason' => 'damage', 'adjusted_on' => '2026-03-12']);
        $this->receive([[$item, 'M', 10, 130]], 0, null, '2026-03-15');
        $this->sell($item, 'M', 20, '2026-03-16');   // crosses layers

        app(\App\Services\Accounting\SalesPoster::class)->sync();   // books the cost of sales
        $check = app(CostingSetup::class)->reconcile();

        $this->assertSame(0, $check['unit_differences'], 'units in the layers equal the shelf, size by size');
        $this->assertEquals($check['stock_value'], $check['books_value'], 'and so does the value');
        $this->assertTrue($check['in_step']);
        $this->assertSame(0, $check['provisional_units']);
    }

    public function testAnOutOfStepShelfIsReported()
    {
        $item = $this->product();
        $this->receive([[$item, '', 10, 100]], 0, '1010');
        ItemStock::where('item_id', $item->id)->update(['quantity_stocked' => 12]);   // someone edited the count behind costing's back

        $check = app(CostingSetup::class)->reconcile();
        $this->assertFalse($check['in_step']);
        $this->assertSame(1, $check['unit_differences']);
        $this->assertSame(['shelf' => 12, 'layers' => 10], array_intersect_key($check['differences'][0], ['shelf' => 1, 'layers' => 1]));
    }

    public function testTheAdjustmentRecordKeepsItsReference()
    {
        $item = $this->product();
        $this->receive([[$item, '', 5, 100]], 0, '1010');
        $adj = $this->costing()->adjust(['item_stock_id' => ItemStock::where('item_id', $item->id)->first()->id, 'quantity' => -1, 'reason' => 'loss', 'adjusted_on' => '2026-03-08']);
        $this->assertSame('ADJ-' . str_pad((string) $adj->id, 6, '0', STR_PAD_LEFT), StockAdjustment::find($adj->id)->reference);
    }
}
