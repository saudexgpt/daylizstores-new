<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemSizePrice extends Model
{
    use SoftDeletes;

    protected $fillable = ['item_id', 'size', 'amount'];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
