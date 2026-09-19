<?php

namespace App\Models\Costing;

use App\Models\Stock\Item;
use Illuminate\Database\Eloquent\Model;

class StockReceiptLine extends Model
{
    protected $guarded = [];

    protected $casts = ['unit_cost' => 'decimal:2', 'extra_cost' => 'decimal:2'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
