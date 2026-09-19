<?php

namespace App\Models\Costing;

use Illuminate\Database\Eloquent\Model;

class StockAdjustment extends Model
{
    protected $guarded = [];

    protected $casts = ['adjusted_on' => 'date:Y-m-d', 'cost' => 'decimal:2'];
}
