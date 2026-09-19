<?php

namespace App\Http\Controllers;

use App\Models\Order\Order;
use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** A product is "running out" once its sellable balance is at or below this. */
    const LOW_STOCK_THRESHOLD = 10;

    /** How many days the sales trend covers. */
    const TREND_DAYS = 14;

    /** How long the assembled dashboard is reused (the Refresh button bypasses it). */
    const CACHE_SECONDS = 60;

    public function adminDashboard(Request $request)
    {
        // On the live data (~150k orders) the aggregates below take seconds even with the
        // indexes; a minute of staleness on a summary screen is a good trade. `fresh=1`
        // (the dashboard's Refresh button) recomputes and re-primes the cache.
        if ($request->boolean('fresh')) {
            Cache::forget('admin-dashboard');
        }

        return response()->json(
            Cache::remember('admin-dashboard', self::CACHE_SECONDS, fn () => $this->buildDashboard()),
            200
        );
    }

    private function buildDashboard(): array
    {
        $products = Item::count();

        // One grouped query instead of a COUNT per status.
        $byStatus = Order::query()
            ->selectRaw('order_status, COUNT(*) as aggregate')
            ->groupBy('order_status')
            ->pluck('aggregate', 'order_status');
        $delivered_orders = (int) ($byStatus['Delivered'] ?? 0);
        $pending_orders = (int) ($byStatus['Pending'] ?? 0);
        $transit_orders = (int) ($byStatus['On Transit'] ?? 0);
        $cancelled_orders = (int) ($byStatus['Cancelled'] ?? 0);

        // Revenue = money actually received, on orders that were not cancelled.
        $paidOrders = Order::query()
            ->where('payment_status', 'paid')
            ->where('order_status', '!=', 'Cancelled');
        $revenue_total = (float) (clone $paidOrders)->sum('total');
        $revenue_30_days = (float) (clone $paidOrders)->where('created_at', '>=', Carbon::now()->subDays(30))->sum('total');
        $awaiting_payment = Order::where('payment_status', 'pending')->where('order_status', 'Pending')->count();

        return [
            'data_summary' => compact(
                'products',
                'delivered_orders',
                'pending_orders',
                'transit_orders',
                'cancelled_orders',
                'awaiting_payment',
                'revenue_total',
                'revenue_30_days'
            ),
            'trend' => $this->dailyTrend(),
            'low_stock' => $this->lowStockProducts(),
        ];
    }

    public function itemsRunningOutOfStock()
    {
        return response()->json(['running_out_of_stock_products' => $this->lowStockProducts()], 200);
    }

    /**
     * Orders placed and money received per day for the last TREND_DAYS days,
     * with the days that had no orders included as zeros so a chart never
     * skips a date.
     */
    private function dailyTrend(): array
    {
        $from = Carbon::now()->subDays(self::TREND_DAYS - 1)->startOfDay();

        $rows = Order::query()
            ->where('created_at', '>=', $from)
            ->selectRaw("DATE(created_at) as day, COUNT(*) as orders, SUM(CASE WHEN payment_status = 'paid' AND order_status != 'Cancelled' THEN total ELSE 0 END) as revenue")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get()
            ->keyBy('day');

        $trend = [];
        for ($i = 0; $i < self::TREND_DAYS; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $trend[] = [
                'date' => $day,
                'orders' => (int) ($rows[$day]->orders ?? 0),
                'revenue' => (float) ($rows[$day]->revenue ?? 0),
            ];
        }
        return $trend;
    }

    /**
     * Enabled products whose sellable balance (stocked - reserved - sold,
     * summed over every size/colour row) is at or below the threshold.
     */
    private function lowStockProducts(): array
    {
        return ItemStock::query()
            ->join('items', 'items.id', '=', 'item_stocks.item_id')
            ->whereNull('item_stocks.deleted_at')
            ->whereNull('items.deleted_at')
            ->where('items.enabled', 1)
            ->groupBy('items.id', 'items.name')
            ->havingRaw('SUM(item_stocks.quantity_stocked - item_stocks.reserved - item_stocks.sold) <= ?', [self::LOW_STOCK_THRESHOLD])
            ->orderByRaw('SUM(item_stocks.quantity_stocked - item_stocks.reserved - item_stocks.sold) ASC')
            ->limit(10)
            ->get([
                'items.id as item_id',
                'items.name',
                DB::raw('SUM(item_stocks.quantity_stocked - item_stocks.reserved - item_stocks.sold) as total_balance'),
            ])
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'name' => $row->name,
                'total_balance' => (int) $row->total_balance,
            ])
            ->all();
    }
}
