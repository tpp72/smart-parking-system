<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingLot extends Model
{
    protected $guarded = [];

    public function rates()
    {
        return $this->hasMany(ParkingRate::class);
    }

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function devices()
    {
        return $this->hasMany(EntryExitDevice::class);
    }

    public function parkingLogs()
    {
        return $this->hasMany(ParkingLog::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
