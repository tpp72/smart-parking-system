<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuspiciousVehicle extends Model
{
    protected $guarded = [];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
