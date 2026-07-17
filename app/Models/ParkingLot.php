<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParkingLot extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class);
    }

    public function parkingLogs()
    {
        return $this->hasMany(ParkingLog::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeReservable($query)
    {
        return $query->where('reservations_enabled', true);
    }

    /** ลานจอดที่ยังไม่มีเจ้าของ — อยู่ในความดูแลของ Admin */
    public function scopeUnowned($query)
    {
        return $query->whereNull('owner_id');
    }

    /** ลานจอดของ owner คนที่ระบุ */
    public function scopeOwnedBy($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }
}
