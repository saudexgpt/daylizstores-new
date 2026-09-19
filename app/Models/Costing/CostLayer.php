<?php

namespace App\Models\Costing;

use Illuminate\Database\Eloquent\Model;

/**
 * What is left of one delivery of one product+size, and what that remainder cost. FIFO sells
 * the oldest layer first. `cost_remaining` is the total still on the shelf, so a layer's
 * value is always exact even when its landed cost does not divide evenly into kobo.
 */
class CostLayer extends Model
{
    protected $guarded = [];

    protected $casts = ['received_on' => 'date:Y-m-d', 'cost_total' => 'decimal:2', 'cost_remaining' => 'decimal:2'];
}
