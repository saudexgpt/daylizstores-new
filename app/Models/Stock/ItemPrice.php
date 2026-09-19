<?php

namespace App\Models\Stock;

use App\Models\Setting\Currency;
use Illuminate\Database\Eloquent\Model;

class ItemPrice extends Model
{
    //
    protected $fillable = ['item_id', 'amount'];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
