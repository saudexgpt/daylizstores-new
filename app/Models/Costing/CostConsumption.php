<?php

namespace App\Models\Costing;

use Illuminate\Database\Eloquent\Model;

/** Units taken from a layer by a sold order line (or a stock adjustment), and their cost. */
class CostConsumption extends Model
{
    protected $guarded = [];

    protected $casts = ['cost' => 'decimal:2', 'provisional' => 'boolean'];
}
