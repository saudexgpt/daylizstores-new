<?php

namespace App\Models\Order;

use App\Dispatcher;
use Illuminate\Database\Eloquent\Model;

class DispatchedOrder extends Model
{
    //
    public function dispatcher()
    {
        return $this->belongsTo(Dispatcher::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
