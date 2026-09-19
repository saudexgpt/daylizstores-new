<?php

namespace Tests\Feature\Costing;

use App\Services\Accounting\SalesPoster;

/**
 * Gross margin, inventory valuation and restock cost — worked out by hand, and shown only to people
 * who hold "view cost".
 *
 *   Runner (size 42): 20 bought at 1,000 · sold 3 on 10 Mar at 5,000 each  -> sales 15,000, cost 3,000
 *   Tote  (no size) : 10 bought at   500 · sold 2 on 11 Mar at 5,000 each  -> sales 10,000, cost 1,000
 *   March: sales 25,000 · cost 4,000 · profit 21,000 · margin 84%
 *   Left on the shelf: Runner 17 × 1,000 = 17,000 · Tote 8 × 500 = 4,000 · total 21,000
 */
class CostingReportsTest extends CostingTestCase
{
    private $runner;
    private $tote;

    private function shop(): void
    {
        $this->runner = $this->product('Runner');
        $this->tote = $this->product('Tote');
        $this->receive([[$this->runner, '42', 20, 1000]], 0, '1010', '2026-03-02');
        $this->receive([[$this->tote, '', 10, 500]], 0, '1010', '2026-03-02');
        $this->sell($this->runner, '42', 3, '2026-03-10');
        $this->sell($this->tote, null, 2, '2026-03-11');
    }

    private function report(string $key, array $query = [], $user = null)
    {
        return $this->actingAs($user ?? $this->admin(), 'api')->getJson("/api/reports/run/$key?" . http_build_query($query));
    }

    private function march(array $more = []): array
    {
        return ['from' => '2026-03-01', 'to' => '2026-03-31'] + $more;
    }

    private function summary($response): array
    {
        return collect($response->json('summary'))->pluck('value', 'label')->all();
    }

    private function seeCosts()
    {
        return $this->staffWith('view reports', 'view cost');
    }

    public function testGrossMarginPerProduct()
    {
        $this->shop();
        $r = $this->report('gross-margin', $this->march())->assertStatus(200);

        $rows = collect($r->json('rows'));
        $runner = $rows->firstWhere('name', 'Runner');
        $this->assertSame(3, $runner['units']);
        $this->assertEquals(15000, $runner['revenue']);
        $this->assertEquals(3000, $runner['cost']);
        $this->assertEquals(12000, $runner['margin']);
        $this->assertEquals(80.0, $runner['margin_pct']);
        $this->assertEquals(90.0, $rows->firstWhere('name', 'Tote')['margin_pct']);
        $this->assertSame(['Runner', 'Tote'], $rows->pluck('name')->all(), 'most profitable first');

        $s = $this->summary($r);
        $this->assertEquals(25000, $s['Sales (costed)']);
        $this->assertEquals(4000, $s['Cost of goods']);
        $this->assertEquals(21000, $s['Gross profit']);
        $this->assertEquals(84.0, $s['Margin']);
        $this->assertEquals(0, $s['Sales not costed yet']);
        $this->assertEquals(21000, $r->json('footer.margin'));
    }

    public function testGrossMarginBySizeAndCategoryAndFilters()
    {
        $this->shop();
        $bySize = $this->report('gross-margin', $this->march(['group_by' => 'size']));
        $this->assertContains('Size', collect($bySize->json('columns'))->pluck('label')->all());
        $this->assertSame('42', collect($bySize->json('rows'))->firstWhere('name', 'Runner')['size']);

        $byCategory = $this->report('gross-margin', $this->march(['group_by' => 'category']));
        $this->assertCount(2, $byCategory->json('rows'), 'each product has its own factory category');
        $this->assertEquals(25000, $byCategory->json('footer.revenue'));

        $this->assertSame(['Tote'], collect($this->report('gross-margin', $this->march(['q' => 'tote']))->json('rows'))->pluck('name')->all());
        $this->assertSame(['Tote', 'Runner'], collect($this->report('gross-margin', $this->march(['sort' => 'margin_pct']))->json('rows'))->pluck('name')->all(), 'by margin %: 90% before 80%');
        $this->assertSame([], $this->report('gross-margin', ['from' => '2026-05-01', 'to' => '2026-05-31'])->json('rows'));
    }

    public function testSalesFromBeforeCostingAreCountedAsNotCostedNotSilentlyIgnored()
    {
        $this->shop();
        $this->costingOff();
        $this->sell($this->runner, '42', 1, '2026-03-12');   // dispatched before costing went live: no cost
        $this->costingOn();

        $s = $this->summary($this->report('gross-margin', $this->march()));
        $this->assertEquals(25000, $s['Sales (costed)'], 'the margin covers only what has a cost');
        $this->assertEquals(5000, $s['Sales not costed yet'], 'and says how much it does not cover');
    }

    public function testInventoryValuation()
    {
        $this->shop();
        app(SalesPoster::class)->sync();
        $r = $this->report('inventory-valuation')->assertStatus(200);

        $rows = collect($r->json('rows'));
        $runner = $rows->firstWhere('name', 'Runner');
        $this->assertSame(17, $runner['units']);
        $this->assertEquals(1000, $runner['unit_cost']);
        $this->assertEquals(17000, $runner['value']);
        $this->assertSame('2026-03-02', $runner['oldest']);
        $this->assertSame('2026-03-10', $runner['last_sold']);
        $this->assertGreaterThan(100, $runner['idle_days']);
        $this->assertEquals(4000, $rows->firstWhere('name', 'Tote')['value']);

        $s = $this->summary($r);
        $this->assertEquals(21000, $s['Stock at cost']);
        $this->assertEquals(21000, $s['Inventory in the books'], 'the books agree with the shelf');
        $this->assertSame('Yes', $s['Books and shelf agree?']);
        $this->assertEquals(21000, $r->json('footer.value'));
    }

    public function testDeadStockFilterAndAnOutOfStepWarning()
    {
        $this->shop();
        app(SalesPoster::class)->sync();

        $this->assertCount(2, $this->report('inventory-valuation', ['idle_days' => 1])->json('rows'));
        $this->assertSame([], $this->report('inventory-valuation', ['idle_days' => 3000])->json('rows'), 'nothing has been unsold for 3,000 days');
        $this->assertSame(['Runner'], collect($this->report('inventory-valuation', ['q' => 'run'])->json('rows'))->pluck('name')->all());

        \DB::table('item_stocks')->where('item_id', $this->runner->id)->update(['quantity_stocked' => 30]);   // the shelf drifts from the layers
        $this->assertSame('No — see Accounting overview', $this->summary($this->report('inventory-valuation'))['Books and shelf agree?']);
    }

    public function testCostReportsAreOnlyForThoseWhoMaySeeCosts()
    {
        $this->shop();
        $reportsOnly = $this->staffWith('view reports');

        foreach (['gross-margin', 'inventory-valuation'] as $key) {
            $this->report($key, [], $reportsOnly)->assertStatus(403);
            $this->report($key, [], $this->seeCosts())->assertStatus(200);
        }
        $catalog = $this->actingAs($reportsOnly, 'api')->getJson('/api/reports/catalog')->json('reports');
        $keys = collect($catalog)->pluck('key')->all();
        $this->assertNotContains('gross-margin', $keys);
        $this->assertNotContains('inventory-valuation', $keys);
        $this->assertCount(8, $keys);

        $withCost = $this->actingAs($this->seeCosts(), 'api')->getJson('/api/reports/catalog')->json('reports');
        $this->assertContains('gross-margin', collect($withCost)->pluck('key')->all());
    }

    // ------------------------------------------------------------------ restock cost

    private function soldOutProduct()
    {
        $item = $this->product('Sold Out Bag');
        $this->receive([[$item, '', 10, 700]], 0, '1010', '2026-03-02');
        $this->sell($item, null, 10, now()->subDays(5)->toDateString());   // all sold recently: 10 in 90 days -> ceil(10/90 × 30) = 4 to reorder

        return $item;
    }

    public function testTheRestockListPricesTheSuggestedOrderForThoseWhoMaySeeCosts()
    {
        $this->soldOutProduct();

        $withCost = $this->actingAs($this->staffWith('create menu', 'view cost'), 'api')->getJson('/api/stock/restock')->assertStatus(200);
        $row = $withCost->json('rows.0');
        $this->assertSame(4, $row['suggested_qty']);
        $this->assertEquals(700, $row['unit_cost']);
        $this->assertEquals(2800, $row['order_cost']);

        $plain = $this->actingAs($this->staffWith('create menu'), 'api')->getJson('/api/stock/restock')->assertStatus(200);
        $this->assertArrayNotHasKey('unit_cost', $plain->json('rows.0'));
        $this->assertArrayNotHasKey('order_cost', $plain->json('rows.0'));

        $csv = $this->actingAs($this->staffWith('create menu', 'view cost'), 'api')->get('/api/stock/restock?format=csv')->streamedContent();
        $this->assertStringContainsString('Est. cost of suggested restock', $csv);
        $plainCsv = $this->actingAs($this->staffWith('create menu'), 'api')->get('/api/stock/restock?format=csv')->streamedContent();
        $this->assertStringNotContainsString('cost', $plainCsv);
    }

    public function testTheOutOfStockReportTotalsTheCashNeeded()
    {
        $this->soldOutProduct();

        $withCost = $this->report('out-of-stock', [], $this->seeCosts())->assertStatus(200);
        $this->assertContains('order_cost', collect($withCost->json('columns'))->pluck('key')->all());
        $this->assertEquals(2800, $this->summary($withCost)['Est. cost of suggested order']);
        $this->assertEquals(2800, $withCost->json('rows.0.order_cost'));

        $plain = $this->report('out-of-stock', [], $this->staffWith('view reports'))->assertStatus(200);
        $this->assertNotContains('order_cost', collect($plain->json('columns'))->pluck('key')->all());
        $this->assertArrayNotHasKey('Est. cost of suggested order', $this->summary($plain));
        $this->assertArrayNotHasKey('order_cost', $plain->json('rows.0'));
    }
}
