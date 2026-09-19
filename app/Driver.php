<?php

namespace App;

use App\Laravue\Models\User;
use App\Models\Invoice\DispatchedWaybill;
use App\Models\Logistics\VehicleDriver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    //
    use SoftDeletes;
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicleDriver()
    {
        return $this->hasOne(VehicleDriver::class);
    }

}
