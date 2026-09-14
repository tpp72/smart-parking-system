<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_walk_in'      => 'boolean',
        'reserve_start'   => 'datetime',
        'checked_in_at'   => 'datetime',
        'completed_at'    => 'datetime',
        'deposit_amount'  => 'decimal:2',
        'reservation_fee' => 'decimal:2',
    ];

    const STATUSES = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'];

    /** Statuses considered "active" — not yet done or cancelled */
    const ACTIVE_STATUSES = ['pending', 'confirmed', 'checked_in'];

    /**
     * State machine ของ Reservation (project-plan.md §7.3, §20)
     * pending → confirmed → checked_in → completed · pending/confirmed → cancelled | expired
     * (Walk-in ถูกสร้างในสถานะ checked_in โดยตรง)
     */
    const TRANSITIONS = [
        'pending'    => ['confirmed', 'cancelled', 'expired'],
        'confirmed'  => ['checked_in', 'cancelled', 'expired'],
        'checked_in' => ['completed'],
        'completed'  => [],
        'cancelled'  => [],
        'expired'    => [],
    ];

    /** สถานะที่หมดอายุได้เมื่อไม่ Check-in ภายในเวลา */
    const EXPIRABLE_STATUSES = ['pending', 'confirmed'];

    /** ต้อง Check-in ภายในกี่นาทีหลัง reserve_start (1 ชั่วโมง) */
    public static function gracePeriodMinutes(): int
    {
        return (int) config('parking.grace_period', 60);
    }

    /** Deposit = hourly_rate × 1 ชั่วโมง */
    public static function depositFor(ParkingLot $lot): float
    {
        return round((float) $lot->hourly_rate * 1, 2);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
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

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function depositPayment()
    {
        return $this->hasOne(Payment::class)->where('type', Payment::TYPE_DEPOSIT);
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

    /** Reservation ที่ User จองเอง (ไม่ใช่ Walk-in) */
    public function scopeBooking($query)
    {
        return $query->where('is_walk_in', false);
    }

    /**
     * Auto Check-in ได้ตอนนี้: confirmed + ถึงเวลาจองแล้ว + ยังไม่เลย grace period
     * (รถที่มาก่อนเวลาจองไม่ Auto Check-in — ให้เจ้าหน้าที่ Manual Check-in)
     */
    public function scopeCheckable($query)
    {
        return $query->where('status', 'confirmed')
            ->where('reserve_start', '<=', now())
            ->where('reserve_start', '>=', now()->subMinutes(self::gracePeriodMinutes()));
    }

    /**
     * เลยช่วง Check-in แล้ว (เกิน reserve_start + 60 นาที) และยังไม่ Check-in → ต้อง Expire
     * ขอบเวลาเป็นส่วนเติมเต็มของ checkable(): ครบ 60 นาทีพอดียังเช็คอินได้
     */
    public function scopeOverdue($query)
    {
        return $query->whereIn('status', self::EXPIRABLE_STATUSES)
            ->where('reserve_start', '<', now()->subMinutes(self::gracePeriodMinutes()));
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::EXPIRABLE_STATUSES, true)
            && $this->reserve_start->lt(now()->subMinutes(self::gracePeriodMinutes()));
    }

    /** Manual Check-in ได้ตอนนี้: confirmed + ยังไม่เลย grace period (มาก่อนเวลาจองได้) */
    public function scopeManuallyCheckable($query)
    {
        return $query->where('status', 'confirmed')
            ->where('reserve_start', '>=', now()->subMinutes(self::gracePeriodMinutes()));
    }
}
