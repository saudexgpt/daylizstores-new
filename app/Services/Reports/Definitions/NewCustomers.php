<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NewCustomers extends Report
{
    public function key(): string
    {
        return 'new-customers';
    }

    public function title(): string
    {
        return 'New customers';
    }

    public function group(): string
    {
        return 'Customers';
    }

    public function description(): string
    {
        return 'Customers who signed up in the period, and whether they went on to buy.';
    }

    public function note(): ?string
    {
        return '"Orders" and "Total spent" count paid, non-cancelled orders since the customer joined — the same revenue rule as the sales reports.';
    }

    public function filters(): array
    {
        return [
            Filter::dateRange('Joined'),
            Filter::select('ordered', 'Purchases', Filter::options(['all' => 'All new customers', 'yes' => 'Has bought', 'no' => 'Has not bought yet']), 'all'),
            Filter::text('q', 'Customer', 'Name, email or phone'),
        ];
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::datetime('joined', 'Joined'),
            Column::text('name', 'Customer'),
            Column::text('email', 'Email'),
            Column::text('phone', 'Phone'),
            Column::int('orders', 'Orders'),
            Column::money('spent', 'Total spent'),
            Column::datetime('last_order', 'Last order'),
        ];
    }

    private function joinedBetween(array $f): \Closure
    {
        return fn ($q) => $q->where('u.role', 'customer')->whereNull('u.deleted_at')
            ->where('u.created_at', '>=', $f['from'] . ' 00:00:00')
            ->where('u.created_at', '<', Carbon::parse($f['to'])->addDay()->toDateString() . ' 00:00:00');
    }

    public function query(array $f): ?Builder
    {
        $joined = $this->joinedBetween($f);

        // what each new customer has bought, worked out in ONE pass over their orders (a per-customer
        // join + GROUP BY over the whole orders table took over half a minute on the live data)
        $bought = DB::table('orders')->whereNull('deleted_at')
            ->where('payment_status', 'paid')->where('order_status', '!=', 'Cancelled')
            ->whereIn('user_id', $joined(DB::table('users as u'))->select('u.id'))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as n, SUM(total) as spent, MAX(created_at) as last_order');

        $q = $joined(DB::table('users as u'))
            ->leftJoinSub($bought, 'o', 'o.user_id', '=', 'u.id')
            ->selectRaw('u.id, u.created_at as joined, u.name, u.email, u.phone, COALESCE(o.n, 0) as orders, COALESCE(o.spent, 0) as spent, o.last_order');

        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('u.name', 'like', $like)->orWhere('u.email', 'like', $like)->orWhere('u.phone', 'like', $like));
        }
        match ($f['ordered'] ?? 'all') {
            'yes' => $q->whereNotNull('o.user_id'),
            'no' => $q->whereNull('o.user_id'),
            default => null,
        };

        return $q->orderByDesc('u.created_at')->orderByDesc('u.id');
    }

    protected function shape($r): array
    {
        return [
            'joined' => $r->joined, 'name' => $r->name, 'email' => $r->email, 'phone' => $r->phone,
            'orders' => (int) $r->orders, 'spent' => round((float) $r->spent, 2), 'last_order' => $r->last_order,
        ];
    }

    public function summary(array $f): array
    {
        $t = DB::query()->fromSub($this->query($f)->reorder(), 't')
            ->selectRaw('COUNT(*) as customers, COALESCE(SUM(orders > 0),0) as buyers, COALESCE(SUM(spent),0) as spent')->first();

        return [
            ['label' => 'New customers', 'value' => (int) $t->customers, 'type' => 'int'],
            ['label' => 'Have bought', 'value' => (int) $t->buyers, 'type' => 'int'],
            ['label' => 'Conversion', 'value' => $t->customers ? round($t->buyers / $t->customers * 100, 1) : null, 'type' => 'percent'],
            ['label' => 'Total spent', 'value' => round((float) $t->spent, 2), 'type' => 'money'],
        ];
    }
}
