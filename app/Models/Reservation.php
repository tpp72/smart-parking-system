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

    /**
     * แยกป้ายทะเบียนที่เก็บเป็นสตริงเดียว (เช่น "กข 1234 กรุงเทพมหานคร") ออกเป็น [เลขทะเบียน, จังหวัด]
     * ใช้กับ reservation เก่าที่จองไว้ก่อนมีคอลัมน์ plate_province แยกต่างหาก — ถ้าคำสุดท้ายไม่ตรงกับ
     * จังหวัดใดเลย (ไม่มีจังหวัดต่อท้าย) จะคืนจังหวัดว่าง
     *
     * @return array{0: string, 1: string}
     */
    public static function splitPlate(?string $plate): array
    {
        $plate = trim((string) $plate);
        $provinces = config('thai_provinces');

        $lastSpace = strrpos($plate, ' ');
        if ($lastSpace !== false) {
            $possibleProvince = substr($plate, $lastSpace + 1);
            if (in_array($possibleProvince, $provinces, true)) {
                return [trim(substr($plate, 0, $lastSpace)), $possibleProvince];
            }
        }

        return [$plate, ''];
    }

    /** จังหวัดของป้ายทะเบียนนี้ — ใช้คอลัมน์ plate_province ถ้ามี ไม่งั้น parse จาก license_plate (ข้อมูลเก่า) */
    public function resolvedProvince(): string
    {
        return $this->plate_province ?: self::splitPlate($this->license_plate)[1];
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
