<?php

namespace App\Services\Reports\Definitions;

use App\Services\Reports\Column;
use App\Services\Reports\Definitions\Concerns\FiltersOrders;
use App\Services\Reports\Filter;
use App\Services\Reports\Report;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class OrdersList extends Report
{
    use FiltersOrders;

    public function key(): string
    {
        return 'orders';
    }

    public function title(): string
    {
        return 'Orders';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Every order in the period, one line each, with its status and amounts.';
    }

    public function note(): ?string
    {
        return 'Unlike the sales reports this starts with every order — pending, unpaid and cancelled included — so you can audit them. Narrow it with the status filters.';
    }

    public function filters(): array
    {
        // an audit view: default to everything, not just revenue
        return array_merge(array_map(function ($spec) {
            if (in_array($spec['key'], ['order_status', 'payment_status'], true)) {
                $spec['default'] = 'all';
            }

            return $spec;
        }, $this->orderFilterSpecs()), [
            Filter::text('q', 'Search', 'Order no., customer, email or payment ref.'),
            Filter::money('min_amount', 'Total at least (₦)'),
            Filter::money('max_amount', 'Total at most (₦)'),
        ]);
    }

    public function columns(array $filters = []): array
    {
        return [
            Column::datetime('created_at', 'Placed'),
            Column::text('order_number', 'Order no.'),
            Column::text('customer', 'Customer'),
            Column::text('location', 'Delivery'),
            Column::status('order_status', 'Order status'),
            Column::status('payment_status', 'Payment'),
            Column::money('net', 'Net sales'),
            Column::money('delivery', 'Delivery'),
            Column::money('total', 'Total'),
            Column::text('payment_reference', 'Payment ref.'),
        ];
    }

    private function base(array $f): Builder
    {
        $q = $this->applyOrderFilters(DB::table('orders')->leftJoin('users as u', 'u.id', '=', 'orders.user_id'), $f);
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $q->where(fn ($w) => $w->where('orders.order_number', 'like', $like)->orWhere('u.name', 'like', $like)
                ->orWhere('u.email', 'like', $like)->orWhere('orders.payment_reference', 'like', $like));
        }
        if (isset($f['min_amount'])) {
            $q->where('orders.total', '>=', $f['min_amount']);
        }
        if (isset($f['max_amount'])) {
            $q->where('orders.total', '<=', $f['max_amount']);
        }

        return $q;
    }

    public function query(array $f): ?Builder
    {
        return $this->base($f)
            ->select('orders.id', 'orders.created_at', 'orders.order_number', 'u.name as customer', 'orders.location', 'orders.order_status', 'orders.payment_status', 'orders.total', 'orders.delivery_cost', 'orders.payment_reference')
            ->orderByDesc('orders.created_at')->orderByDesc('orders.id');
    }

    protected function shape($r): array
    {
        return [
            'created_at' => $r->created_at, 'order_number' => $r->order_number, 'customer' => $r->customer,
            'location' => $r->location ? trim($r->location, '/') : null,
            'order_status' => $r->order_status, 'payment_status' => ucfirst((string) $r->payment_status),
            'net' => $this->money($r->total - $r->delivery_cost), 'delivery' => $this->money($r->delivery_cost),
            'total' => $this->money($r->total), 'payment_reference' => $r->payment_reference,
        ];
    }

    public function summary(array $f): array
    {
        $t = $this->base($f)->selectRaw('COUNT(*) as orders, COALESCE(SUM(orders.total),0) as total, COALESCE(SUM(orders.delivery_cost),0) as delivery')->first();

        return [
            ['label' => 'Orders', 'value' => (int) $t->orders, 'type' => 'int'],
            ['label' => 'Total value', 'value' => $this->money($t->total), 'type' => 'money'],
            ['label' => 'Net sales', 'value' => $this->money($t->total - $t->delivery), 'type' => 'money'],
            ['label' => 'Delivery', 'value' => $this->money($t->delivery), 'type' => 'money'],
        ];
    }
}
