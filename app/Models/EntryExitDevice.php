<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryExitDevice extends Model
{
    protected $guarded = [];

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }

    public function licensePlateScans()
    {
        return $this->hasMany(LicensePlateScan::class, 'device_id');
    }
}
