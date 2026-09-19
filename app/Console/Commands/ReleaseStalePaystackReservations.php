<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use App\Services\PaystackService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ReleaseStalePaystackReservations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:release-stale-paystack';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Releases reserved stock for Paystack orders that never confirmed payment shortly after checkout, rather than leaving it locked for the 21-day order:treat-pending window';

    // A completed Paystack charge confirms within seconds, so this window is
    // generous enough to never touch a genuinely in-progress payment, while
    // still releasing an abandoned checkout's reservation quickly instead of
    // holding real inventory hostage for weeks.
    private const STALE_AFTER_MINUTES = 45;

    public function handle()
    {
        $paystack = new PaystackService();
        $cutoff = now()->subMinutes(self::STALE_AFTER_MINUTES);

        Order::with('orderItems.stock')
            ->where('payment_method', 'Paystack')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('payment_reference')
            ->chunkById(200, function (Collection $orders) use ($paystack) {
                foreach ($orders as $order) {
                    $this->resolveOrder($order, $paystack);
                }
            });
    }

    private function resolveOrder($order, $paystack)
    {
        try {
            $verification = $paystack->verifyTransaction($order->payment_reference);
            $data = $verification['data'] ?? [];
            $verifiedSuccess = ($verification['status'] ?? false) && ($data['status'] ?? null) === 'success';
            $amountMatches = isset($data['amount']) && (int) $data['amount'] === (int) round($order->total * 100);
            if ($verifiedSuccess && $amountMatches) {
                // The payment actually succeeded but the webhook/callback
                // never reached us — recover the sale instead of releasing
                // the stock a paying customer is entitled to.
                $order->payment_status = 'paid';
                $order->save();
                return;
            }
        } catch (\Throwable $e) {
            // Verification call itself failed (network hiccup, bad/missing
            // key) — fall through and release once the grace period has
            // elapsed anyway; nothing below depends on Paystack's answer
            // beyond the "did it secretly already succeed" recovery above.
        }

        $this->releaseReservedQuantities($order);
        $order->order_status = 'Cancelled';
        $order->payment_status = 'cancelled';
        $order->save();
    }

    private function releaseReservedQuantities($order)
    {
        foreach ($order->orderItems as $orderItem) {
            $stock = $orderItem->stock;
            if ($stock && $stock->reserved >= $orderItem->quantity) {
                $stock->reserved -= $orderItem->quantity;
                $stock->save();
            }
        }
    }
}
