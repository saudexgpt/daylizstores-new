<?php

namespace App\Models\Stock;

use App\Models\ItemDiscount;
use App\Models\ItemReview;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Setting\Tax;

use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{

    use SoftDeletes, HasFactory;
    protected $hidden = [
        'created_at', 'updated_at',
    ];
    protected $fillable = ['category_id', 'name', 'slug', 'description'];

    /**
     * @return \Database\Factories\ItemFactory
     */
    protected static function newFactory()
    {
        return ItemFactory::new();
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function itemStocks()
    {
        return $this->hasMany(ItemStock::class);
    }
    public function media()
    {
        return $this->hasMany(ItemMedia::class);
    }
    public function discounts()
    {
        return $this->hasMany(ItemDiscount::class, 'item_id', 'id');
    }
    public function price()
    {
        return $this->hasOne(ItemPrice::class);
    }
    public function sizePrices()
    {
        return $this->hasMany(ItemSizePrice::class);
    }
    public function reviews()
    {
        return $this->hasMany(ItemReview::class, 'item_id', 'id');
    }

    /**
     * Resolve the effective price for a given size: a size-specific override
     * if one exists, otherwise the item's single base price. Sizes are the
     * only pricing dimension — color never affects price.
     */
    public function priceForSize($size)
    {
        $sizePrice = $this->sizePrices->firstWhere('size', $size);
        return $sizePrice ? $sizePrice->amount : ($this->price->amount ?? null);
    }
}
