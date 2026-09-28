<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** ต้องยืนยัน Email ก่อนใช้งานระบบหลัก (project-plan.md §5.3, §19.4) */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /** อีเมลของ System User ที่ใช้เป็นเจ้าของ Reservation แบบ Walk-in */
    const WALKIN_EMAIL = 'walkin@system.local';

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /** System User "Walkin User" (สร้างโดย migration) */
    public static function walkin(): self
    {
        return static::where('email', self::WALKIN_EMAIL)->where('is_system', true)->firstOrFail();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /** Audit log ที่ผู้ใช้คนนี้เป็นผู้กระทำ */
    public function auditActions()
    {
        return $this->hasMany(AdminAction::class, 'actor_id');
    }

    public function reservationChanges()
    {
        return $this->hasMany(ReservationLog::class, 'changed_by');
    }

    public function suspiciousVehiclesAdded()
    {
        return $this->hasMany(SuspiciousVehicle::class, 'added_by');
    }

    public function ownedParkingLots()
    {
        return $this->hasMany(ParkingLot::class, 'owner_id');
    }

    public function ownerApplication()
    {
        return $this->hasOne(OwnerApplication::class);
    }

    public function ownerResignations()
    {
        return $this->hasMany(OwnerResignation::class);
    }

    public function isApprovedOwner(): bool
    {
        return $this->role === 'owner' && $this->owner_status === 'approved';
    }
}
