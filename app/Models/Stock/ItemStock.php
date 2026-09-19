<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class ItemStock extends Model
{

    use SoftDeletes;
    protected $hidden = [
        'created_at', 'updated_at',
    ];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
