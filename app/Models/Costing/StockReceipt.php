<?php

namespace App\Models\Costing;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Model;

/** A delivery from a supplier: what arrived, what it cost, and how it was paid. */
class StockReceipt extends Model
{
    protected $guarded = [];

    protected $casts = ['received_on' => 'date:Y-m-d', 'items_total' => 'decimal:2', 'extra_costs' => 'decimal:2', 'landed_total' => 'decimal:2'];

    public function lines()
    {
        return $this->hasMany(StockReceiptLine::class);
    }

    public function layers()
    {
        return $this->hasMany(CostLayer::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
