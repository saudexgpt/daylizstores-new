<?php

namespace App\Services\Orders;

use App\Mail\OrderDetails;
use App\Models\Order\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the customer their order details once an order is placed (bank transfer) or paid (card).
 *
 * It runs AFTER the response has been sent (see OrdersController::emailOrderDetails), so a slow or
 * unavailable mail server can never delay checkout, roll back an order, or need a queue worker.
 * It never throws: a failed email is logged and the order stands.
 */
class OrderEmails
{
    public function send(int $orderId): bool
    {
        try {
            $order = Order::with(['customer', 'orderItems'])->find($orderId);
            $email = $order && $order->customer ? trim((string) $order->customer->email) : '';
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;   // nowhere to send it
            }

            Mail::to($email)->send(new OrderDetails($order->customer, $order, $order->orderItems));

            return true;
        } catch (\Throwable $e) {
            Log::warning("Order details email for order #{$orderId} could not be sent: " . $e->getMessage());

            return false;
        }
    }
}
