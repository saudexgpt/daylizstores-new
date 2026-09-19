<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemMedia extends Model
{
    use HasFactory;
    protected $hidden = [
        'created_at', 'updated_at',
    ];
    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
