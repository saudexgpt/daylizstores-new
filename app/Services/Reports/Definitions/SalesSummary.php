<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SalesSummary extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'sales-summary';
    }

    public function title(): string
    {
        return 'Sales summary';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Orders, units and sales for each day, week or month.';
    }

    public function note(): ?string
    {
        return 'Net sales = order total minus delivery charges. With the default filters (paid, not cancelled) this is the same revenue the dashboard and the books use.';
    }

    public function filters(): array
    {
        return array_merge($this->orderFilterSpecs(), [
            Filter::select('group_by', 'Group by', Filter::options(['day' => 'Day', 'week' => 'Week', 'month' => 'Month']), 'day'),
        ]);
    }

    public function columns(array $filters = []): array
    {
        $group = $filters['group_by'] ?? 'day';
        // a day or a week start is a real date (shown as "5 Mar 2026"); a month is already a label ("Mar 2026")
        $period = $group === 'month' ? Column::text('period', 'Month') : Column::date('period', $group === 'week' ? 'Week starting' : 'Date');

        return [
            $period,
            Column::int('orders', 'Orders'),
            Column::int('units', 'Units sold'),
            Column::money('gross', 'Order total'),
            Column::money('delivery', 'Delivery'),
            Column::money('net', 'Net sales'),
            Column::money('average', 'Avg. order (net)'),
        ];
    }

    private function bucket(string $groupBy): string
    {
        return match ($groupBy) {
            'week' => 'DATE_SUB(DATE(orders.created_at), INTERVAL WEEKDAY(orders.created_at) DAY)',
            'month' => "DATE_FORMAT(orders.created_at, '%Y-%m')",
            default => 'DATE(orders.created_at)',
        };
    }

    protected function all(array $f): array
    {
        return $this->remember('all', $f, fn () => $this->compute($f));
    }

    private function compute(array $f): array
    {
        $bucket = $this->bucket($f['group_by'] ?? 'day');

        $orders = $this->applyOrderFilters(DB::table('orders'), $f)
            ->groupBy(DB::raw($bucket))->orderBy(DB::raw($bucket))
            ->selectRaw("$bucket as period, COUNT(*) as orders, SUM(orders.total) as gross, SUM(orders.delivery_cost) as delivery")
            ->get()->keyBy('period');

        $units = $this->applyOrderFilters(DB::table('orders'), $f)
            ->join('order_items as oi', function ($j) {
                $j->on('oi.order_id', '=', 'orders.id')->whereNull('oi.deleted_at');
            })
            ->groupBy(DB::raw($bucket))
            ->selectRaw("$bucket as period, SUM(oi.quantity) as units")
            ->pluck('units', 'period');

        return $orders->map(function ($o, $period) use ($units, $f) {
            $net = (float) $o->gross - (float) $o->delivery;

            return [
                'period' => $this->label((string) $period, $f['group_by'] ?? 'day'),
                'orders' => (int) $o->orders,
                'units' => (int) ($units[$period] ?? 0),
                'gross' => $this->money($o->gross),
                'delivery' => $this->money($o->delivery),
                'net' => $this->money($net),
                'average' => $o->orders ? $this->money($net / $o->orders) : 0.0,
            ];
        })->values()->all();
    }

    private function label(string $period, string $groupBy): string
    {
        return $groupBy === 'month' ? Carbon::createFromFormat('Y-m-d', $period . '-01')->format('M Y') : $period;
    }

    public function footer(array $f): ?array
    {
        $rows = $this->all($f);
        if (!$rows) {
            return null;
        }
        $orders = array_sum(array_column($rows, 'orders'));
        $net = round(array_sum(array_column($rows, 'net')), 2);

        return [
            'period' => 'Total', 'orders' => $orders, 'units' => array_sum(array_column($rows, 'units')),
            'gross' => round(array_sum(array_column($rows, 'gross')), 2), 'delivery' => round(array_sum(array_column($rows, 'delivery')), 2),
            'net' => $net, 'average' => $orders ? round($net / $orders, 2) : 0.0,
        ];
    }

    public function summary(array $f): array
    {
        $t = $this->footer($f) ?? ['orders' => 0, 'units' => 0, 'gross' => 0, 'delivery' => 0, 'net' => 0, 'average' => 0];

        return [
            ['label' => 'Net sales', 'value' => $t['net'], 'type' => 'money'],
            ['label' => 'Orders', 'value' => $t['orders'], 'type' => 'int'],
            ['label' => 'Units sold', 'value' => $t['units'], 'type' => 'int'],
            ['label' => 'Avg. order (net)', 'value' => $t['average'], 'type' => 'money'],
            ['label' => 'Delivery charged', 'value' => $t['delivery'], 'type' => 'money'],
        ];
    }
}
