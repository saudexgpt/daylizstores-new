<?php

namespace App\Models\Setting;

use App\Models\Stock\Item;
use App\Models\Stock\ItemTax;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    //
    public function items()
    {
        return $this->belongsToMany(Item::class)
            ->using(ItemTax::class)
            ->withTimestamps();
    }
}
