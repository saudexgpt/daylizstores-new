<?php

namespace Tests\Feature\Stock;

use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestockListTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $name, array $variants = [], bool $enabled = true, ?Category $category = null, float $price = 1000): Item
    {
        $item = Item::factory()->create([
            'name' => $name, 'enabled' => $enabled,
            'category_id' => ($category ?? Category::factory()->create())->id,
        ]);
        $p = new ItemPrice();
        $p->item_id = $item->id;
        $p->amount = $price;
        $p->save();
        foreach ($variants as $v) {
            $s = new ItemStock();
            $s->item_id = $item->id;
            $s->color = $v['color'] ?? null;
            $s->size = $v['size'] ?? null;
            $s->quantity_stocked = $v['stocked'] ?? 0;
            $s->reserved = $v['reserved'] ?? 0;
            $s->sold = $v['sold'] ?? 0;
            $s->save();
        }

        return $item;
    }

    /** a sale of $qty units of $item's first stock row */
    private function sale(Item $item, int $qty, string $date, string $payment = 'paid', string $status = 'Delivered'): void
    {
        $order = Order::factory()->create();
        $order->payment_status = $payment;
        $order->order_status = $status;
        $order->created_at = $date . ' 10:00:00';
        $order->save();
        $line = new OrderItem();
        $line->order_id = $order->id;
        $line->stock_id = ItemStock::where('item_id', $item->id)->value('id');
        $line->item_id = $item->id;
        $line->product_name = $item->name;
        $line->quantity = $qty;
        $line->price = 1000;
        $line->total = 1000 * $qty;
        $line->save();
    }

    private function restock(string $query = '', $user = null)
    {
        return $this->actingAs($user ?? $this->createAdminUser(), 'api')->getJson('/api/stock/restock' . ($query ? "?$query" : ''));
    }

    private function names($response): array
    {
        return collect($response->json('rows'))->pluck('name')->sort()->values()->all();
    }

    public function testOnlyActiveOutOfStockProductsAreListedByDefault()
    {
        $this->product('Healthy', [['stocked' => 50]]);
        $this->product('Low', [['stocked' => 5]]);
        $this->product('Sold out', [['stocked' => 10, 'sold' => 10]]);
        $this->product('Never stocked');                                   // no stock rows at all
        $this->product('Oversold', [['stocked' => 2, 'sold' => 5]]);
        $this->product('Reserved away', [['stocked' => 4, 'reserved' => 4]]);
        $this->product('Disabled and empty', [['stocked' => 0]], false);

        $response = $this->restock()->assertStatus(200);

        $this->assertSame(['Never stocked', 'Oversold', 'Reserved away', 'Sold out'], $this->names($response));
        $this->assertSame('oversold', collect($response->json('rows'))->firstWhere('name', 'Oversold')['status']);
        $this->assertSame(-3, collect($response->json('rows'))->firstWhere('name', 'Oversold')['balance']);
        $this->assertSame('out', collect($response->json('rows'))->firstWhere('name', 'Sold out')['status']);
        $this->assertSame(['total' => 4, 'out' => 3, 'oversold' => 1, 'low' => 0], $response->json('summary'));
    }

    public function testDisabledProductsCanBeIncludedOrShownAlone()
    {
        $this->product('Active empty', [['stocked' => 0]]);
        $this->product('Disabled empty', [['stocked' => 0]], false);

        $this->assertSame(['Disabled empty'], $this->names($this->restock('enabled=disabled')));
        $this->assertSame(['Active empty', 'Disabled empty'], $this->names($this->restock('enabled=all')));
    }

    public function testLowStockUsesTheThreshold()
    {
        $this->product('Nine left', [['stocked' => 9]]);
        $this->product('Twenty left', [['stocked' => 20]]);
        $this->product('Zero', [['stocked' => 0]]);

        $this->assertSame(['Nine left'], $this->names($this->restock('stock=low')));
        $this->assertSame(['Nine left', 'Zero'], $this->names($this->restock('stock=both')));
        $this->assertSame(['Nine left', 'Twenty left', 'Zero'], $this->names($this->restock('stock=both&threshold=25')));
    }

    public function testAProductIsOutOnlyWhenEveryVariantIsButVariantsCanBeListedSeparately()
    {
        $this->product('Shoes', [
            ['color' => 'Black', 'size' => '42', 'stocked' => 5, 'sold' => 5],   // sold out
            ['color' => 'Black', 'size' => '43', 'stocked' => 8],                 // plenty
        ]);

        $this->assertSame([], $this->names($this->restock()), 'the product as a whole still has stock');

        $variants = $this->restock('level=variant')->assertStatus(200);
        $this->assertSame(['Shoes'], $this->names($variants));
        $this->assertSame('Black / 42', $variants->json('rows.0.variant'));
        $this->assertSame(0, $variants->json('rows.0.balance'));
        $this->assertNotNull($variants->json('rows.0.stock_id'));
    }

    public function testSalesRateAndSuggestedQuantityUseOnlyPaidNonCancelledRecentSales()
    {
        $item = $this->product('Fast mover', [['stocked' => 100, 'sold' => 100]]);
        $this->sale($item, 30, now()->subDays(10)->toDateString());                         // counts
        $this->sale($item, 15, now()->subDays(20)->toDateString());                         // counts
        $this->sale($item, 500, now()->subDays(10)->toDateString(), 'pending', 'Pending');  // unpaid: ignored
        $this->sale($item, 500, now()->subDays(10)->toDateString(), 'paid', 'Cancelled');   // cancelled: ignored
        $this->sale($item, 7, now()->subDays(200)->toDateString());                         // outside the 90-day window
        $this->product('No sales', [['stocked' => 0]]);

        $rows = collect($this->restock()->json('rows'));

        $fast = $rows->firstWhere('name', 'Fast mover');
        $this->assertSame(45, $fast['sold_recent']);
        $this->assertSame(now()->subDays(10)->toDateString(), substr($fast['last_sold'], 0, 10));
        // 45 units in 90 days = 0.5 a day; 30 days of cover = 15 units
        $this->assertSame(15, $fast['suggested_qty']);
        $this->assertNull($rows->firstWhere('name', 'No sales')['suggested_qty'], 'nothing sold recently, so no suggestion');

        // a longer cover window asks for more
        $this->assertSame(30, collect($this->restock('cover=60')->json('rows'))->firstWhere('name', 'Fast mover')['suggested_qty']);
    }

    public function testFiltersSearchSortAndPaging()
    {
        $shoes = Category::factory()->create(['name' => 'Shoes']);
        $bags = Category::factory()->create(['name' => 'Bags']);
        $this->product('Red sneaker', [['stocked' => 0]], true, $shoes, 5000);
        $this->product('Blue sneaker', [['stocked' => 0]], true, $shoes, 3000);
        $this->product('Tote bag', [['stocked' => 0]], true, $bags, 1000);

        $this->assertSame(['Blue sneaker', 'Red sneaker'], $this->names($this->restock('category_id=' . $shoes->id)));
        $this->assertSame(['Tote bag'], $this->names($this->restock('q=tote')));
        $this->assertSame(['Tote bag', 'Blue sneaker', 'Red sneaker'], collect($this->restock('sort=price')->json('rows'))->pluck('name')->all());
        $this->assertSame(['Red sneaker', 'Blue sneaker', 'Tote bag'], collect($this->restock('sort=price&direction=desc')->json('rows'))->pluck('name')->all());

        $page2 = $this->restock('per_page=2&page=2')->assertStatus(200);
        $this->assertCount(1, $page2->json('rows'));
        $this->assertSame(3, $page2->json('pagination.total'));
        $this->assertSame(2, $page2->json('pagination.last_page'));
    }

    public function testBadFiltersAreRejectedNotIgnored()
    {
        $this->restock('stock=nonsense')->assertStatus(422);
        $this->restock('category_id=99999')->assertStatus(422);
        $this->restock('per_page=100000')->assertStatus(422);
        $this->restock('sort=password')->assertStatus(422);
    }

    public function testCsvExportMatchesTheFiltersAndDefusesSpreadsheetFormulas()
    {
        $this->product('=HYPERLINK("http://evil.example","click")', [['stocked' => 0]]);
        $this->product('Plain item', [['stocked' => 0]]);
        $this->product('Fine', [['stocked' => 9]]);

        $response = $this->restock('format=csv')->assertStatus(200);

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'UTF-8 BOM so Excel reads it correctly');
        $this->assertStringContainsString('Product,"Size / colour",Category,Status', $csv);
        $this->assertStringContainsString('Plain item', $csv);
        $this->assertStringNotContainsString('Fine,', $csv, 'a product with stock is not exported');
        $this->assertStringContainsString("'=HYPERLINK", $csv, 'a text cell starting with = is neutralised');
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function testOnlyProductManagersCanSeeIt()
    {
        $this->product('Empty', [['stocked' => 0]]);

        $this->getJson('/api/stock/restock')->assertStatus(401);   // first: actingAs() below stays signed in
        $this->restock('', $this->createCustomerUser())->assertStatus(403);
        $this->restock('', $this->createStaffUser())->assertStatus(403);

        $manager = $this->createStaffUser();
        $manager->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'create menu', 'guard_name' => 'api']));
        $this->restock('', $manager)->assertStatus(200);
    }
}
