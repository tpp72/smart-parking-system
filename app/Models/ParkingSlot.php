<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParkingSlot extends Model
{
    protected $guarded = [];

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class, 'parking_lot_id');
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
