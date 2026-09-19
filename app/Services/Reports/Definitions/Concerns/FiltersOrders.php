<?php

namespace App\Services\Reports\Definitions\Concerns;

use App\Services\Reports\Filter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;

/**
 * The order filters every sales report shares. The defaults (paid, not cancelled) are the
 * definition of revenue used by the dashboard and the ledger — Order::revenue() — so a sales
 * report for a period agrees with them unless the reader deliberately widens the filters.
 */
trait FiltersOrders
{
    protected function orderFilterSpecs(): array
    {
        return [
            Filter::dateRange('Order date'),
            Filter::select('order_status', 'Order status', Filter::options([
                'not_cancelled' => 'Not cancelled', 'all' => 'All statuses',
                'Pending' => 'Pending', 'On Transit' => 'On transit', 'Delivered' => 'Delivered', 'Cancelled' => 'Cancelled',
            ]), 'not_cancelled'),
            Filter::select('payment_status', 'Payment', Filter::options([
                'paid' => 'Paid', 'all' => 'Any', 'pending' => 'Pending', 'cancelled' => 'Cancelled',
            ]), 'paid'),
        ];
    }

    /** $q must select FROM `orders` (no alias) */
    protected function applyOrderFilters(Builder $q, array $f): Builder
    {
        $q->whereNull('orders.deleted_at')
            ->where('orders.created_at', '>=', $f['from'] . ' 00:00:00')
            ->where('orders.created_at', '<', Carbon::parse($f['to'])->addDay()->toDateString() . ' 00:00:00');

        $status = $f['order_status'] ?? 'not_cancelled';
        if ($status === 'not_cancelled') {
            $q->where('orders.order_status', '!=', 'Cancelled');
        } elseif ($status !== 'all') {
            $q->where('orders.order_status', $status);
        }

        $payment = $f['payment_status'] ?? 'paid';
        if ($payment !== 'all') {
            $q->where('orders.payment_status', $payment);
        }

        return $q;
    }

    protected function money($value): float
    {
        return round((float) $value, 2);
    }

    protected function share($part, $whole): ?float
    {
        return (float) $whole > 0 ? round((float) $part / (float) $whole * 100, 1) : null;
    }
}
