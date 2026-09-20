<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's order confirmation: order number, what they ordered, totals, delivery details and where things
 * stand with payment.
 *
 * QUEUED: checkout only inserts a job; the queue worker talks to the mail server, and retries with back-off if it
 * is down — so a slow or unreachable mail server can never delay an order, duplicate it, or lose the email.
 * (The job carries only model ids, not the order's contents; it is rebuilt from the database when it runs.)
 */
class OrderDetails extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 120, 600, 1800];   // seconds between attempts
    public $timeout = 60;

    public $user; // public so the view can read them
    public $order;
    public $order_items;

    public function __construct($user, $order, $order_items)
    {
        $this->user = $user;
        $this->order = $order;
        $this->order_items = $order_items;
        $this->afterCommit();   // only queue once the surrounding database transaction has committed
    }

    public function build()
    {
        $paid = $this->order->payment_status === 'paid';
        $store = config('app.name');

        return $this
            ->subject(($paid ? 'Payment received for order ' : 'We received your order ') . $this->order->order_number . ' — ' . $store)
            ->view('emails.order_details', [
                'user' => $this->user,
                'order' => $this->order,
                'order_items' => $this->order_items,
                'paid' => $paid,
                'store' => $store,
                'baseUrl' => rtrim((string) config('app.url'), '/'),
                'trackUrl' => rtrim((string) config('app.url'), '/') . '/track/order?' . http_build_query(['order_number' => $this->order->order_number, 'email' => $this->user->email]),
            ]);
    }
}
