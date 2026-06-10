<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'check_in_time'  => 'datetime',
        'check_out_time' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }

    public function parkingSlot()
    {
        return $this->belongsTo(ParkingSlot::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
