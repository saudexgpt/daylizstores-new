<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Collection;

class TreatUnpaidPendingOrders extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'order:treat-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command reverses and cancels all pending unpaid orders after 24hours';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }
    private function releasePendingUnpaidOrderQuantities($order)
    {
        $orderItems = $order->orderItems;
        foreach ($orderItems as $orderItem) {
            $stock = $orderItem->stock;
            $order_quantity = $orderItem->quantity;
            $reserved = $stock->reserved;

            if ($reserved >= $order_quantity) {
                $stock->reserved -= $order_quantity;
                $stock->save();
            }
        }
    }
    private function cancelOrder($order)
    {
        $order->order_status = 'Cancelled';
        $order->payment_status = 'cancelled';
        $order->save();
    }
    private function cancelUnpaidOrders()
    {
        set_time_limit(0);
        $twenty_one_days_ago = date('Y-m-d H:i:s', strtotime('-504 hours'));
        Order::with('orderItems.stock')->where('valid_till', '<', $twenty_one_days_ago)->where('payment_status', 'pending')
            ->chunkById(200, function (Collection $orders) {
                foreach ($orders as $order) {
                    $this->releasePendingUnpaidOrderQuantities($order);
                    $this->cancelOrder($order);
                }
            }, $column = 'id');
    }

    public function handle()
    {
        $this->cancelUnpaidOrders();
    }
}
