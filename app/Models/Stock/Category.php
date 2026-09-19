<?php

namespace App\Models\Stock;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes, HasFactory;
    //

    /**
     * @return \Database\Factories\CategoryFactory
     */
    protected static function newFactory()
    {
        return CategoryFactory::new();
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }
}
