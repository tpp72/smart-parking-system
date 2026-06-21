<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensePlateScan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scan_time'    => 'datetime',
        'is_suspicious' => 'boolean',
        'confidence'   => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
