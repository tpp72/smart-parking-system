<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Penalty extends Model
{
    protected $guarded = [];

    public function parkingLog()
    {
        return $this->belongsTo(ParkingLog::class);
    }
}
