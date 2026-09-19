<?php

namespace Tests\Feature\Reports;

use App\Laravue\Models\User;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use App\Services\Reports\ReportRegistry;
use Tests\Feature\Accounting\AccountingTestCase;

/**
 * Every report against one small business whose numbers are worked out by hand (March 2026):
 *
 *   order  date   customer status      payment  total   delivery  lines
 *   #1     03-05  Ada      Delivered   paid     10,500    500     Runner ×2 = 8,000 · Tote ×1 = 2,000
 *   #2     03-05  Bola     On Transit  paid      6,000  1,000     Tote ×2 = 5,000
 *   #3     03-20  Ada      Delivered   paid      4,400    400     Runner ×1 = 4,000
 *   #4     03-06  Bola     Pending     pending   9,000      0     Runner ×1 = 9,000   (unpaid → not revenue)
 *   #5     03-07  Ada      Cancelled   paid      7,000      0     Tote ×1 = 7,000     (cancelled → not revenue)
 *   #6     04-02  Bola     Delivered   paid      3,000      0     Tote ×1 = 3,000     (other month)
 *
 * Revenue for March (paid, not cancelled) = #1 + #2 + #3: total 20,900 · delivery 1,900 · net 19,000.
 */
class ReportCenterTest extends AccountingTestCase
{
    private User $ada;
    private User $bola;
    private Item $runner;
    private Item $tote;

    private function business(): void
    {
        $footwear = Category::factory()->create(['name' => 'Footwear']);
        $bags = Category::factory()->create(['name' => 'Bags']);
        $this->runner = $this->makeItem('Runner', $footwear, 4000, 20, 5);
        $this->tote = $this->makeItem('Tote', $bags, 2500, 5, 5);
        $this->ada = User::factory()->customer()->create(['name' => 'Ada Obi', 'email' => 'ada@example.com', 'created_at' => '2026-03-02 09:00:00']);
        $this->bola = User::factory()->customer()->create(['name' => 'Bola Ade', 'email' => 'bola@example.com', 'created_at' => '2026-03-15 09:00:00']);

        $this->sale($this->ada, 10500, '2026-03-05', 'paid', 'Delivered', 500, [[$this->runner, 2, 8000], [$this->tote, 1, 2000]]);
        $this->sale($this->bola, 6000, '2026-03-05', 'paid', 'On Transit', 1000, [[$this->tote, 2, 5000]]);
        $this->sale($this->ada, 4400, '2026-03-20', 'paid', 'Delivered', 400, [[$this->runner, 1, 4000]]);
        $this->sale($this->bola, 9000, '2026-03-06', 'pending', 'Pending', 0, [[$this->runner, 1, 9000]]);
        $this->sale($this->ada, 7000, '2026-03-07', 'paid', 'Cancelled', 0, [[$this->tote, 1, 7000]]);
        $this->sale($this->bola, 3000, '2026-04-02', 'paid', 'Delivered', 0, [[$this->tote, 1, 3000]]);
    }

    private function makeItem(string $name, Category $category, float $price, int $stocked, int $sold): Item
    {
        $item = Item::factory()->create(['name' => $name, 'enabled' => true, 'category_id' => $category->id]);
        $p = new ItemPrice();
        $p->item_id = $item->id;
        $p->amount = $price;
        $p->save();
        $s = new ItemStock();
        $s->item_id = $item->id;
        $s->color = 'Black';
        $s->size = '42';
        $s->quantity_stocked = $stocked;
        $s->reserved = 0;
        $s->sold = $sold;
        $s->save();

        return $item;
    }

    private function sale(User $customer, float $total, string $date, string $payment, string $status, float $delivery, array $lines): Order
    {
        $order = $this->order($total, $date, $payment, $status, $delivery);
        $order->user_id = $customer->id;
        $order->save();
        foreach ($lines as [$item, $qty, $lineTotal]) {
            $l = new OrderItem();
            $l->order_id = $order->id;
            $l->stock_id = ItemStock::where('item_id', $item->id)->value('id');
            $l->item_id = $item->id;
            $l->product_name = $item->name;
            $l->quantity = $qty;
            $l->price = $lineTotal / $qty;
            $l->total = $lineTotal;
            $l->save();
        }

        return $order;
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

    private function row($response, string $column, string $value): ?array
    {
        return collect($response->json('rows'))->firstWhere($column, $value);
    }

    // ------------------------------------------------------------------ catalogue, permissions, validation

    public function testCatalogueListsEveryReportWithItsFiltersAndColumnsButNoServerRules()
    {
        $response = $this->actingAs($this->admin(), 'api')->getJson('/api/reports/catalog')->assertStatus(200);

        $keys = collect($response->json('reports'))->pluck('key')->all();
        $this->assertCount(16, $keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
        $this->assertEqualsCanonicalizing(['Sales', 'Inventory', 'Customers', 'Finance'], $response->json('groups'));
        foreach ($response->json('reports') as $report) {
            $this->assertNotEmpty($report['title']);
            $this->assertNotEmpty($report['description']);
            $this->assertNotEmpty($report['columns'], $report['key']);
            foreach ($report['filters'] as $filter) {
                $this->assertArrayNotHasKey('rules', $filter, 'validation rules are server-side only');
                $this->assertContains($filter['type'], ['date_range', 'date', 'select', 'text', 'money', 'number']);
            }
        }
    }

    public function testEveryReportRunsAndExportsWithDefaultsOnAnEmptyAndAPopulatedBusiness()
    {
        foreach ([false, true] as $populated) {
            if ($populated) {
                $this->business();
            }
            $admin = $this->admin();
            foreach (app(ReportRegistry::class)->all()->keys() as $key) {
                $r = $this->actingAs($admin, 'api')->getJson("/api/reports/run/$key")->assertStatus(200);
                $this->assertIsArray($r->json('rows'), $key);
                $this->assertIsArray($r->json('summary'), $key);
                $this->assertNotEmpty($r->json('columns'), $key);
                $this->assertSame(1, $r->json('pagination.page'), $key);

                $csv = $this->actingAs($admin, 'api')->get("/api/reports/export/$key?format=csv")->assertStatus(200);
                $this->assertStringStartsWith("\xEF\xBB\xBF", $csv->streamedContent(), "$key csv");

                $json = $this->actingAs($admin, 'api')->getJson("/api/reports/export/$key?format=json")->assertStatus(200);
                $this->assertIsArray($json->json('rows'), "$key json");
                $this->assertSame($r->json('pagination.total'), count($json->json('rows')), "$key: the export has every row the screen counts");
            }
        }
    }

    public function testAccessFollowsPermissions()
    {
        $this->getJson('/api/reports/catalog')->assertStatus(401);
        $this->actingAs($this->createCustomerUser(), 'api')->getJson('/api/reports/catalog')->assertStatus(403);
        $this->actingAs($this->createStaffUser(), 'api')->getJson('/api/reports/catalog')->assertStatus(403);

        // reports only: the sales / stock / customer reports, not the books
        $reports = $this->staffWith('view reports');
        $catalog = $this->actingAs($reports, 'api')->getJson('/api/reports/catalog')->assertStatus(200);
        $groups = collect($catalog->json('reports'))->pluck('group')->unique()->values()->all();
        $this->assertEqualsCanonicalizing(['Sales', 'Inventory', 'Customers'], $groups);
        $this->assertCount(8, $catalog->json('reports'));
        $this->report('sales-summary', [], $reports)->assertStatus(200);
        $this->report('profit-loss', [], $reports)->assertStatus(403);
        $this->actingAs($reports, 'api')->get('/api/reports/export/profit-loss?format=csv')->assertStatus(403);

        // accounting only: the financial statements, but none of the sales / stock / customer reports
        $books = $this->staffWith('view accounting');
        $finance = $this->actingAs($books, 'api')->getJson('/api/reports/catalog')->assertStatus(200);
        $this->assertSame(['Finance'], collect($finance->json('reports'))->pluck('group')->unique()->values()->all());
        $this->assertCount(6, $finance->json('reports'));
        $this->report('profit-loss', [], $books)->assertStatus(200);
        $this->report('sales-summary', [], $books)->assertStatus(403);

        // both: everything
        $both = $this->staffWith('view reports', 'view accounting');
        $this->assertCount(14, $this->actingAs($both, 'api')->getJson('/api/reports/catalog')->json('reports'));
        $this->report('profit-loss', [], $both)->assertStatus(200);

        $this->report('no-such-report')->assertStatus(404);
    }

    public function testBadFiltersAreRejected()
    {
        $this->report('sales-summary', ['from' => '03/01/2026'])->assertStatus(422);
        $this->report('sales-summary', ['from' => '2026-03-31', 'to' => '2026-03-01'])->assertStatus(422)->assertJsonValidationErrors('to');
        $this->report('sales-summary', ['group_by' => 'decade'])->assertStatus(422);
        $this->report('sales-summary', ['order_status' => 'Nonsense'])->assertStatus(422);
        $this->report('sales-by-product', ['category_id' => '99999'])->assertStatus(422);
        $this->report('orders', ['min_amount' => '-5'])->assertStatus(422);
        $this->report('orders', ['per_page' => 5])->assertStatus(422);
        $this->report('general-ledger', ['account_id' => '99999'])->assertStatus(422);
        $this->actingAs($this->admin(), 'api')->getJson('/api/reports/export/orders?format=pdf')->assertStatus(422)->assertJsonValidationErrors('format');
    }

    // ------------------------------------------------------------------ sales

    public function testSalesSummaryDefaultsToRevenueAndGroupsByDayWeekMonth()
    {
        $this->business();

        $day = $this->report('sales-summary', $this->march())->assertStatus(200);
        $this->assertCount(2, $day->json('rows'));
        $first = $this->row($day, 'period', '2026-03-05');
        $this->assertSame(2, $first['orders']);
        $this->assertSame(5, $first['units']);
        $this->assertEquals(16500, $first['gross']);
        $this->assertEquals(1500, $first['delivery']);
        $this->assertEquals(15000, $first['net']);
        $this->assertEquals(7500, $first['average']);
        $this->assertEquals(4000, $this->row($day, 'period', '2026-03-20')['net']);
        $this->assertSame('Total', $day->json('footer.period'));
        $this->assertEquals(19000, $day->json('footer.net'));
        $this->assertEquals(20900, $day->json('footer.gross'));
        $this->assertSame(3, $day->json('footer.orders'));
        $this->assertEquals(19000, $this->summary($day)['Net sales']);
        $this->assertSame(6, $this->summary($day)['Units sold']);

        $week = $this->report('sales-summary', $this->march(['group_by' => 'week']));
        $this->assertSame(['2026-03-02', '2026-03-16'], collect($week->json('rows'))->pluck('period')->all(), 'weeks start on Monday');
        $this->assertSame('Week starting', $week->json('columns.0.label'));

        $month = $this->report('sales-summary', $this->march(['group_by' => 'month']));
        $this->assertCount(1, $month->json('rows'));
        $this->assertSame('Mar 2026', $month->json('rows.0.period'));
        $this->assertEquals(19000, $month->json('rows.0.net'));
    }

    public function testSalesFiltersWidenAndNarrowTheOrdersCounted()
    {
        $this->business();

        // every order in March, whatever its state
        $all = $this->report('sales-summary', $this->march(['order_status' => 'all', 'payment_status' => 'all', 'group_by' => 'month']));
        $this->assertSame(5, $all->json('footer.orders'));
        $this->assertEquals(36900, $all->json('footer.gross'));

        // only what is still to be paid for
        $unpaid = $this->report('sales-summary', $this->march(['order_status' => 'all', 'payment_status' => 'pending', 'group_by' => 'month']));
        $this->assertSame(1, $unpaid->json('footer.orders'));
        $this->assertEquals(9000, $unpaid->json('footer.gross'));

        // one delivery status
        $delivered = $this->report('sales-summary', $this->march(['order_status' => 'Delivered', 'group_by' => 'month']));
        $this->assertSame(2, $delivered->json('footer.orders'));

        // the period boundary is inclusive of the last day
        $lastDay = $this->report('sales-summary', ['from' => '2026-03-20', 'to' => '2026-03-20']);
        $this->assertCount(1, $lastDay->json('rows'));
        $this->assertEquals(4000, $lastDay->json('footer.net'));
    }

    public function testSalesReportAgreesWithTheBooks()
    {
        $this->business();

        $net = $this->report('sales-summary', $this->march(['group_by' => 'month']))->json('footer.net');
        $revenueScope = (float) Order::revenue()->whereBetween('orders.created_at', ['2026-03-01 00:00:00', '2026-03-31 23:59:59'])
            ->selectRaw('SUM(orders.total - orders.delivery_cost) as v')->value('v');
        $this->assertEquals($revenueScope, $net, 'the report and Order::revenue() (the dashboard) agree');

        $pl = $this->report('profit-loss', $this->march());
        $this->assertEquals($net, $this->row($pl, 'code', '4000')['amount'], 'and the ledger books exactly the same sales revenue');
    }

    public function testSalesByProductAndCategory()
    {
        $this->business();

        $product = $this->report('sales-by-product', $this->march())->assertStatus(200);
        $this->assertSame(['Runner', 'Tote'], collect($product->json('rows'))->pluck('product')->all(), 'best seller first');
        $runner = $this->row($product, 'product', 'Runner');
        $this->assertSame(3, $runner['units']);
        $this->assertSame(2, $runner['orders']);
        $this->assertEquals(12000, $runner['revenue']);
        $this->assertEquals(4000, $runner['average_price']);
        $this->assertEquals(63.2, $runner['share']);
        $this->assertSame('Footwear', $runner['category']);
        $this->assertEquals(36.8, $this->row($product, 'product', 'Tote')['share']);
        $this->assertEquals(19000, $this->summary($product)['Revenue']);
        $this->assertSame(6, $this->summary($product)['Units sold']);

        $bags = $this->report('sales-by-product', $this->march(['category_id' => Category::where('name', 'Bags')->value('id')]));
        $this->assertSame(['Tote'], collect($bags->json('rows'))->pluck('product')->all());
        $this->assertSame(['Runner'], collect($this->report('sales-by-product', $this->march(['q' => 'runn']))->json('rows'))->pluck('product')->all());
        $this->assertSame(['Runner', 'Tote'], collect($this->report('sales-by-product', $this->march(['sort' => 'name']))->json('rows'))->pluck('product')->all());

        $category = $this->report('sales-by-category', $this->march());
        $this->assertSame(['Footwear', 'Bags'], collect($category->json('rows'))->pluck('category')->all());
        $this->assertEquals(12000, $category->json('rows.0.revenue'));
        $this->assertEquals(19000, $category->json('footer.revenue'));
        $this->assertSame('Footwear', $this->summary($category)['Top category']);
    }

    public function testSalesByCustomerOrdersAndFilters()
    {
        $this->business();

        $customers = $this->report('sales-by-customer', $this->march())->assertStatus(200);
        $this->assertSame(['Ada Obi', 'Bola Ade'], collect($customers->json('rows'))->pluck('name')->all());
        $ada = $customers->json('rows.0');
        $this->assertSame(2, $ada['orders']);
        $this->assertEquals(14900, $ada['spent']);
        $this->assertEquals(7450, $ada['average']);
        $this->assertSame('2026-03-05 12:00:00', $ada['first_order']);
        $this->assertSame('2026-03-20 12:00:00', $ada['last_order']);
        $this->assertEquals(20900, $this->summary($customers)['Total spent']);
        $this->assertSame(2, $this->summary($customers)['Customers']);

        $this->assertSame(['Ada Obi'], collect($this->report('sales-by-customer', $this->march(['min_amount' => 10000]))->json('rows'))->pluck('name')->all());
        $this->assertSame(['Bola Ade'], collect($this->report('sales-by-customer', $this->march(['q' => 'bola@']))->json('rows'))->pluck('name')->all());
        $this->assertSame(1, $this->summary($this->report('sales-by-customer', $this->march(['min_amount' => 10000])))['Customers']);
    }

    public function testOrdersReportListsEverythingByDefaultAndFilters()
    {
        $this->business();

        $orders = $this->report('orders', $this->march())->assertStatus(200);
        $this->assertSame(5, $orders->json('pagination.total'), 'an audit view: unpaid and cancelled orders included');
        $this->assertSame(['2026-03-20 12:00:00', '2026-03-07 12:00:00', '2026-03-06 12:00:00'], array_slice(collect($orders->json('rows'))->pluck('created_at')->all(), 0, 3), 'newest first');
        $this->assertEquals(36900, $this->summary($orders)['Total value']);

        $one = $this->row($this->report('orders', $this->march(['payment_status' => 'pending'])), 'payment_status', 'Pending');
        $this->assertEquals(9000, $one['total']);

        $this->assertSame(2, $this->report('orders', $this->march(['min_amount' => 9000]))->json('pagination.total'));
        $this->assertSame(1, $this->report('orders', $this->march(['min_amount' => 9000, 'max_amount' => 10000]))->json('pagination.total'));
        $number = Order::orderBy('id')->first()->order_number;
        $this->assertSame(1, $this->report('orders', $this->march(['q' => $number]))->json('pagination.total'));
        $this->assertSame(3, $this->report('orders', $this->march(['q' => 'Ada']))->json('pagination.total'));
        $this->assertEquals(10000, $this->report('orders', $this->march(['q' => $number]))->json('rows.0.net'), 'order #1: 10,500 less 500 delivery');

        $narrowed = $this->report('orders', $this->march(['order_status' => 'not_cancelled', 'payment_status' => 'paid']));
        $this->assertSame(3, $narrowed->json('pagination.total'), 'the same three orders the sales reports count');
    }

    public function testListingsPageWithoutRepeatingOrSkippingRows()
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->order(1000 + $i, '2026-03-10');   // all on one date: the tiebreaker must keep pages stable
        }
        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $r = $this->report('orders', $this->march(['per_page' => 10, 'page' => $page]))->assertStatus(200);
            $this->assertSame(23, $r->json('pagination.total'));
            $this->assertSame(3, $r->json('pagination.last_page'));
            $seen = array_merge($seen, collect($r->json('rows'))->pluck('total')->all());
        }
        $this->assertCount(23, $seen);
        $this->assertCount(23, array_unique($seen), 'no row repeated across pages');
    }

    // ------------------------------------------------------------------ inventory & customers

    public function testStockLevelsAndTheRestockReport()
    {
        $this->business();   // Runner: 20 stocked, 5 sold → 15 · Tote: 5 stocked, 5 sold → 0

        $all = $this->report('stock-levels')->assertStatus(200);
        $this->assertCount(2, $all->json('rows'));
        $runner = $this->row($all, 'name', 'Runner');
        $this->assertSame(15, $runner['balance']);
        $this->assertSame('In stock', $runner['status']);
        $this->assertEquals(60000, $runner['value'], '15 available × ₦4,000');
        $this->assertSame('Out of stock', $this->row($all, 'name', 'Tote')['status']);
        $this->assertEquals(60000, $this->summary($all)['Retail value of stock']);
        $this->assertSame(15, $this->summary($all)['Units available']);
        $this->assertSame(1, $this->summary($all)['Out of stock']);

        $this->assertSame(['Tote'], collect($this->report('stock-levels', ['stock' => 'out'])->json('rows'))->pluck('name')->all());
        $this->assertSame(['Runner'], collect($this->report('stock-levels', ['stock' => 'in'])->json('rows'))->pluck('name')->all());
        $this->assertSame(['Runner'], collect($this->report('stock-levels', ['stock' => 'low', 'threshold' => 15])->json('rows'))->pluck('name')->all());
        $variant = $this->report('stock-levels', ['level' => 'variant']);
        $this->assertSame('Black / 42', $variant->json('rows.0.variant'));

        $restock = $this->report('out-of-stock')->assertStatus(200);
        $this->assertSame(['Tote'], collect($restock->json('rows'))->pluck('name')->all());
        $this->assertSame('Out of stock', $restock->json('rows.0.status'));
        $this->assertSame(1, $this->summary($restock)['Out of stock']);
        $this->assertSame(1, $restock->json('pagination.total'));
    }

    public function testNewCustomersReport()
    {
        $this->business();   // Ada joined 03-02, Bola 03-15; both have bought

        $joined = User::factory()->customer()->create(['name' => 'Chika Eze', 'email' => 'chika@example.com', 'created_at' => '2026-03-25 10:00:00']);
        User::factory()->customer()->create(['name' => 'Earlier Person', 'created_at' => '2026-02-10 10:00:00']);
        User::factory()->staff()->create(['name' => 'Staff Member', 'created_at' => '2026-03-11 10:00:00']);

        $r = $this->report('new-customers', $this->march())->assertStatus(200);
        $this->assertSame(['Chika Eze', 'Bola Ade', 'Ada Obi'], collect($r->json('rows'))->pluck('name')->all(), 'newest first; staff and other months excluded');
        $ada = $this->row($r, 'name', 'Ada Obi');
        $this->assertSame(2, $ada['orders'], 'paid, non-cancelled orders only');
        $this->assertEquals(14900, $ada['spent']);
        $this->assertSame(0, $this->row($r, 'name', 'Chika Eze')['orders']);
        $this->assertSame(3, $this->summary($r)['New customers']);
        $this->assertSame(2, $this->summary($r)['Have bought']);
        $this->assertEquals(66.7, $this->summary($r)['Conversion']);

        $this->assertSame(['Chika Eze'], collect($this->report('new-customers', $this->march(['ordered' => 'no']))->json('rows'))->pluck('name')->all());
        $this->assertSame(2, $this->report('new-customers', $this->march(['ordered' => 'yes']))->json('pagination.total'));
        $this->assertSame(['Chika Eze'], collect($this->report('new-customers', $this->march(['q' => 'chika']))->json('rows'))->pluck('name')->all());
        $this->assertNotNull($joined);
    }

    // ------------------------------------------------------------------ finance

    private function books(): void
    {
        $this->business();
        $this->expense(1000, '6100', '2026-03-10');
        $this->expense(500, '6200', '2026-03-12');
        $this->income(2000, '4900', '2026-03-15');
    }

    public function testIncomeAndExpensesTieToTheProfitAndLossStatement()
    {
        $this->books();

        // manual entries only: income 2,000 - expenses 1,500
        $manual = $this->report('income-expenses', $this->march(['source' => 'manual']))->assertStatus(200);
        $this->assertSame(3, $manual->json('pagination.total'));
        $this->assertEquals(2000, $this->summary($manual)['Income']);
        $this->assertEquals(1500, $this->summary($manual)['Expenses']);
        $this->assertEquals(500, $this->summary($manual)['Profit']);

        // everything: the report books today's sales first, then adds 19,000 + 1,900 delivery of sales income
        $everything = $this->report('income-expenses', $this->march())->assertStatus(200);
        $this->assertEquals(22900, $this->summary($everything)['Income']);
        $this->assertEquals(1500, $this->summary($everything)['Expenses']);
        $this->assertEquals(21400, $this->summary($everything)['Profit']);

        $pl = $this->report('profit-loss', $this->march());
        $this->assertEquals(21400, $this->summary($pl)['Net profit'], 'same profit as the statement');
        $this->assertEquals(22900, $this->summary($pl)['Income']);

        // narrowing
        $this->assertSame(2, $this->report('income-expenses', $this->march(['kind' => 'expense']))->json('pagination.total'));
        $this->assertSame(1, $this->report('income-expenses', $this->march(['account_id' => $this->acct('6200')->id]))->json('pagination.total'));
        $this->assertSame(1, $this->report('income-expenses', $this->march(['q' => 'Expense 6100']))->json('pagination.total'));
        $this->assertSame(1, $this->report('income-expenses', $this->march(['kind' => 'expense', 'min_amount' => 800]))->json('pagination.total'));
        // two sales days, each posted as a revenue line and a delivery line
        $this->assertSame(4, $this->report('income-expenses', $this->march(['source' => 'sales']))->json('pagination.total'));
        $row = $this->row($this->report('income-expenses', $this->march(['kind' => 'expense', 'q' => '6100'])), 'expense', 1000.0);
        $this->assertNull($row['income']);
        $this->assertStringContainsString('6100', $row['account']);
    }

    public function testVoidedEntriesAreHiddenUnlessAskedForAndStillTieOut()
    {
        $this->books();
        $entry = $this->expense(300, '6100', '2026-03-11');
        $this->ledger()->void($entry, 'entered twice', $this->admin()->id);

        $hidden = $this->report('income-expenses', $this->march(['source' => 'manual', 'kind' => 'expense']));
        $this->assertSame(2, $hidden->json('pagination.total'));
        $this->assertEquals(1500, $this->summary($hidden)['Expenses']);

        $shown = $this->report('income-expenses', $this->march(['source' => 'all', 'kind' => 'expense', 'voided' => 'show']));
        $this->assertSame(4, $shown->json('pagination.total'), 'the two live expenses, the voided original and its reversal');
        $this->assertContains('Void', collect($shown->json('rows'))->pluck('status')->all());
        $this->assertEquals(1500, $this->summary($shown)['Expenses'], 'original and reversal cancel, so the total still ties');
    }

    public function testCategoryBreakdown()
    {
        $this->books();

        $r = $this->report('category-breakdown', $this->march())->assertStatus(200);
        $this->assertSame(['6100', '6200'], collect($r->json('rows'))->pluck('code')->all(), 'biggest first');
        $this->assertEquals(1000, $r->json('rows.0.amount'));
        $this->assertEquals(66.7, $r->json('rows.0.share'));
        $this->assertEquals(33.3, $r->json('rows.1.share'));
        $this->assertEquals(1500, $r->json('footer.amount'));
        $this->assertEquals(1500, $this->summary($r)['Total expenses']);

        $income = $this->report('category-breakdown', $this->march(['kind' => 'income']));
        $this->assertEquals(22900, $this->summary($income)['Total income']);
        $this->assertSame('4000', $income->json('rows.0.code'));
    }

    public function testProfitAndLossStatementRows()
    {
        $this->books();

        $r = $this->report('profit-loss', $this->march())->assertStatus(200);
        $rows = collect($r->json('rows'));
        $this->assertSame(['header', 'line', 'line', 'line', 'subtotal'], $rows->take(5)->pluck('_kind')->all(), 'Income: sales, delivery, other income, then the total');
        $this->assertEquals(22900, $rows->firstWhere('label', 'Total income')['amount']);
        $this->assertEquals(1500, $rows->firstWhere('label', 'Total operating expenses')['amount']);
        $this->assertEquals(21400, $rows->firstWhere('label', 'Net profit')['amount']);
        $this->assertEquals(22900, $rows->firstWhere('label', 'Gross profit')['amount'], 'no cost of sales booked');
        $this->assertNull($rows->firstWhere('label', 'Income')['amount'], 'headers carry no figure');
        $this->assertContains('previous', collect($r->json('columns'))->pluck('key')->all());

        $solo = $this->report('profit-loss', $this->march(['compare' => 'no']));
        $this->assertNotContains('previous', collect($solo->json('columns'))->pluck('key')->all());

        // a loss is shown as a loss
        $this->expense(90000, '6100', '2026-03-28');
        $loss = $this->report('profit-loss', $this->march());
        $this->assertNotNull(collect($loss->json('rows'))->firstWhere('label', 'Net loss'));
        $this->assertEquals(68600, $this->summary($loss)['Net loss']);
    }

    public function testBalanceSheetTrialBalanceAndLedger()
    {
        $this->books();

        $bs = $this->report('balance-sheet', ['as_at' => '2026-03-31'])->assertStatus(200);
        $this->assertSame('Yes', $this->summary($bs)['Balances?']);
        $this->assertEquals(21400, $this->summary($bs)['Total assets'], 'cash: 20,900 sales + 2,000 income - 1,500 expenses');
        $this->assertEquals(21400, $this->summary($bs)['Equity']);
        $rows = collect($bs->json('rows'));
        $this->assertEquals(21400, $rows->firstWhere('label', 'Total assets')['amount']);
        $this->assertEquals(21400, $rows->firstWhere('label', 'Total liabilities and equity')['amount']);
        $this->assertEquals(21400, $rows->firstWhere('label', 'Profit to date')['amount']);

        $tb = $this->report('trial-balance', ['as_at' => '2026-03-31'])->assertStatus(200);
        $this->assertSame('Yes', $this->summary($tb)['Balances?']);
        $this->assertEquals($tb->json('footer.debit'), $tb->json('footer.credit'));
        $this->assertEquals(22900, $tb->json('footer.debit'), 'debits: cash 21,400 + expenses 1,500; credits: income 22,900');

        // ledger for the bank account: defaults to the deposit account (1010)
        $gl = $this->report('general-ledger', $this->march())->assertStatus(200);
        $kinds = collect($gl->json('rows'))->pluck('_kind')->all();
        $this->assertSame('opening', $kinds[0]);
        $this->assertSame('closing', end($kinds));
        $this->assertCount(7, $gl->json('rows'), 'opening + two sales days + two expenses + one income + closing');
        $this->assertEquals(21400, $gl->json('rows.6.balance'));
        $this->assertEquals(21400, $this->summary($gl)['Closing balance']);
        $this->assertEquals(0, $this->summary($gl)['Opening balance']);

        $expenses = $this->report('general-ledger', $this->march(['account_id' => $this->acct('6100')->id]));
        $this->assertEquals(1000, $expenses->json('rows.1.debit'));
        $this->assertEquals(1000, $expenses->json('rows.2.balance'));
    }

    // ------------------------------------------------------------------ exports

    public function testCsvExportStreamsTheFilteredRowsAndTotals()
    {
        $this->business();

        $response = $this->actingAs($this->admin(), 'api')->get('/api/reports/export/sales-summary?' . http_build_query($this->march()) . '&format=csv')->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('sales-summary_2026-03-01_to_2026-03-31', $response->headers->get('Content-Disposition'));
        $lines = preg_split('/\r?\n/', trim(ltrim($response->streamedContent(), "\xEF\xBB\xBF")));
        $this->assertSame('Date,Orders,"Units sold","Order total",Delivery,"Net sales","Avg. order (net)"', $lines[0]);
        $this->assertSame('2026-03-05,2,5,16500,1500,15000,7500', $lines[1]);
        $this->assertSame('2026-03-20,1,1,4400,400,4000,4000', $lines[2]);
        $this->assertSame('Total,3,6,20900,1900,19000,6333.33', $lines[3], 'the footer row is exported too');
        $this->assertCount(4, $lines);
    }

    public function testCsvExportNeutralisesSpreadsheetFormulas()
    {
        $this->business();
        $this->runner->name = '=HYPERLINK("http://evil.example","click")';
        $this->runner->save();

        $csv = $this->actingAs($this->admin(), 'api')->get('/api/reports/export/sales-by-product?' . http_build_query($this->march()) . '&format=csv')->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
    }

    public function testCsvExportOfAListingIsNotPagedAndMatchesTheFilters()
    {
        for ($i = 1; $i <= 23; $i++) {
            $this->order(1000 + $i, '2026-03-10');
        }
        $this->order(5000, '2026-04-10');

        $csv = $this->actingAs($this->admin(), 'api')->get('/api/reports/export/orders?' . http_build_query($this->march()) . '&format=csv')->streamedContent();
        $lines = array_filter(preg_split('/\r?\n/', trim(ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertCount(24, $lines, 'a header and all 23 March orders, not one page of them');
    }

    public function testJsonExportForExcelReturnsEverythingUnderTheCeilingAndRefusesAboveIt()
    {
        $this->business();

        $ok = $this->actingAs($this->admin(), 'api')->getJson('/api/reports/export/orders?' . http_build_query($this->march()) . '&format=json')->assertStatus(200);
        $this->assertCount(5, $ok->json('rows'));
        $this->assertNotEmpty($ok->json('columns'));
        $this->assertSame('orders', $ok->json('key'));

        config(['reports.excel_limit' => 2]);
        $this->actingAs($this->admin(), 'api')->getJson('/api/reports/export/orders?' . http_build_query($this->march()) . '&format=json')
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'CSV'));
    }
}
