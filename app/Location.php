<?php

namespace App;

use App\Laravue\Models\User;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    //
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
