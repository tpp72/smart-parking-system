<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $guarded = [];

    public function parkingLog()
    {
        return $this->belongsTo(ParkingLog::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
