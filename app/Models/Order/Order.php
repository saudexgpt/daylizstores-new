<?php

namespace App\Models\Order;

use App\Laravue\Models\User;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    //
    use SoftDeletes, HasFactory;

    /**
     * @return \Database\Factories\OrderFactory
     */
    protected static function newFactory()
    {
        return OrderFactory::new();
    }

    /**
     * "Revenue" everywhere (dashboard, accounting, reports): money actually received on an
     * order that was not cancelled. One definition, so the screens can never disagree.
     */
    public function scopeRevenue($query)
    {
        return $query->where('orders.payment_status', 'paid')->where('orders.order_status', '!=', 'Cancelled');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
