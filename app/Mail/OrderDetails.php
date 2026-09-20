<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's order confirmation: order number, what they ordered, totals, delivery details and
 * where things stand with payment. Sent straight away (not queued) from OrderEmails, which runs
 * after the checkout response, so it does not depend on a queue worker being up.
 */
class OrderDetails extends Mailable
{
    use SerializesModels;

    public $user; // public so the view can read them
    public $order;
    public $order_items;

    public function __construct($user, $order, $order_items)
    {
        $this->user = $user;
        $this->order = $order;
        $this->order_items = $order_items;
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
