<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'reserve_start' => 'datetime',
        'checked_in_at' => 'datetime',
        'completed_at'  => 'datetime',
    ];

    /** Statuses considered "active" — not yet done or cancelled */
    const ACTIVE_STATUSES = ['pending', 'confirmed', 'checked_in'];

    /** Minutes after reserve_start that check-in is still allowed (from config) */
    public static function gracePeriodMinutes(): int
    {
        return (int) config('parking.grace_period', 30);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

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

    public function logs()
    {
        return $this->hasMany(ReservationLog::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function parkingLog()
    {
        return $this->hasOne(ParkingLog::class);
    }

    /** Reservations still active — not completed, cancelled, or expired */
    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Reservations eligible to be checked in right now:
     * confirmed + reserve_start has arrived (±5 min early) + still within grace period
     */
    public function scopeCheckable($query)
    {
        return $query->where('status', 'confirmed')
            ->where('reserve_start', '<=', now()->addMinutes(5))
            ->where('reserve_start', '>=', now()->subMinutes(self::gracePeriodMinutes()));
    }
}
