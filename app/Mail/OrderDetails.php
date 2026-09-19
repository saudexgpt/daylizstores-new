<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;


class OrderDetails extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user; //make it public so that the view resource can access it
    public $order;
    public $order_items;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user, $order, $order_items)
    {
        //
        $this->user = $user;
        $this->order = $order;
        $this->order_items = $order_items;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $user = $this->user;
        $order = $this->order;
        $order_items = $this->order_items;
        return $this->view('emails.order_details', compact('user', 'order', 'order_items'));
    }
}
