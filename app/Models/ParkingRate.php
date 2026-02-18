<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingRate extends Model
{
    protected $guarded = [];

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }
}
