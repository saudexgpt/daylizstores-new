<?php

namespace Tests\Feature\Costing;

use App\Models\Costing\CostLayer;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Stock\Category;
use App\Models\Stock\Item;
use App\Models\Stock\ItemPrice;
use App\Models\Stock\ItemStock;
use App\Services\Accounting\AccountingSettings;
use App\Services\Costing\CostingSettings;
use App\Services\Costing\Costing;
use Tests\Feature\Accounting\AccountingTestCase;

/**
 * Helpers for the costing tests. Costing is switched on directly (start 2026-03-01) so each test
 * builds exactly the layers it needs through Receive stock; the go-live flow has its own tests.
 */
abstract class CostingTestCase extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->costingOn();
    }

    protected function costingOn(string $start = '2026-03-01'): void
    {
        AccountingSettings::set(CostingSettings::ENABLED, '1');
        AccountingSettings::set(CostingSettings::START, $start);
    }

    protected function costingOff(): void
    {
        AccountingSettings::set(CostingSettings::ENABLED, '0');
        AccountingSettings::set(CostingSettings::START, '');
    }

    protected function costing(): Costing
    {
        return app(Costing::class);
    }

    protected function product(string $name = 'Runner'): Item
    {
        $item = Item::factory()->create(['name' => $name, 'enabled' => true, 'category_id' => Category::factory()->create()->id]);
        $price = new ItemPrice();
        $price->item_id = $item->id;
        $price->amount = 5000;
        $price->save();

        return $item;
    }

    /** a stock row that exists but holds nothing yet */
    protected function stockRow(Item $item, ?string $size = null, ?string $color = null, int $stocked = 0, int $sold = 0): ItemStock
    {
        $s = new ItemStock();
        $s->item_id = $item->id;
        $s->size = $size;
        $s->color = $color;
        $s->quantity_stocked = $stocked;
        $s->reserved = 0;
        $s->sold = $sold;
        $s->save();

        return $s;
    }

    /**
     * Receive stock through the real service.
     * $lines: [[item, size, quantity, unit_cost, color?], …]
     */
    protected function receive(array $lines, float $extra = 0, ?string $payFrom = null, string $date = '2026-03-02', string $supplier = 'Acme Supplies')
    {
        return $this->costing()->receive([
            'received_on' => $date, 'supplier' => $supplier, 'invoice_number' => 'INV-' . random_int(100, 999),
            'extra_costs' => $extra, 'payment_account_id' => $payFrom ? $this->acct($payFrom)->id : null,
            'lines' => array_map(fn ($l) => [
                'item_id' => $l[0]->id, 'size' => $l[1], 'quantity' => $l[2], 'unit_cost' => $l[3], 'color' => $l[4] ?? null,
            ], $lines),
        ], $this->admin()->id);
    }

    /**
     * Sell $qty of a product+size through the real dispatch endpoint: an order line is created, the
     * stock is reserved (as checkout does), then the order is moved out of Pending — which is what
     * takes the goods off the shelf and freezes their cost.
     */
    protected function sell(Item $item, ?string $size, int $qty, string $date = '2026-03-10', ?ItemStock $stock = null): OrderItem
    {
        $stock ??= ItemStock::where('item_id', $item->id)->where('size', $size)->first() ?? $this->stockRow($item, $size);
        $order = $this->order($qty * 5000, $date, 'paid', 'Pending');
        $line = new OrderItem();
        $line->order_id = $order->id;
        $line->stock_id = $stock->id;
        $line->item_id = $item->id;
        $line->product_name = $item->name;
        $line->quantity = $qty;
        $line->price = 5000;
        $line->total = 5000 * $qty;
        $line->save();
        $stock->increment('reserved', $qty);

        $this->actingAs($this->admin(), 'api')->putJson('/api/order/general/change-status/' . $order->id, ['status' => 'Delivered'])->assertStatus(200);

        return $line->fresh();
    }

    protected function layers(Item $item, string $size = '')
    {
        return CostLayer::where('item_id', $item->id)->where('size', $size)->orderBy('received_on')->orderBy('id')->get();
    }

    /** balance of an account in the books (debit-normal accounts positive) */
    protected function balance(string $code): float
    {
        return (float) \DB::table('journal_lines as l')->join('accounts as a', 'a.id', '=', 'l.account_id')->where('a.code', $code)
            ->selectRaw('COALESCE(SUM(l.debit - l.credit), 0) as v')->value('v');
    }
}
