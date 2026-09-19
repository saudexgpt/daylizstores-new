<?php

namespace App;

use App\Laravue\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    //
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
