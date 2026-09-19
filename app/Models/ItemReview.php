<?php

namespace App\Models;

use App\Laravue\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemReview extends Model
{
    use HasFactory;
    /**
     * Get the user that owns the ItemReview
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
