<?php

namespace App\Models\Order;

use App\Models\Stock\Item;
use App\Models\Stock\ItemStock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    //
    use SoftDeletes;

    // what a product cost you is never sent to a browser with the order (customers see their own orders)
    protected $hidden = ['cost_total', 'costed_at'];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function stock()
    {
        return $this->belongsTo(ItemStock::class);
    }
}
