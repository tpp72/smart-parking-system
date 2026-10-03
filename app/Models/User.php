<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\RateLimiter;

/** ต้องยืนยัน Email ก่อนใช้งานระบบหลัก (project-plan.md §5.3, §19.4) */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    /** อีเมลของ System User ที่ใช้เป็นเจ้าของ Reservation แบบ Walk-in */
    const WALKIN_EMAIL = 'walkin@system.local';

    /** ส่งลิงก์ยืนยันอีเมลได้ครั้งละ 1 ครั้งต่อช่วงนี้ (วินาที) — นับรวมทุกช่องทาง ทั้งตอนสมัครและตอนกดส่งใหม่ */
    const VERIFICATION_COOLDOWN = 60;

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * ทุกช่องทางที่ส่งลิงก์ยืนยัน (สมัครสมาชิก · กดส่งใหม่) ผ่านเมธอดนี้ จึงเริ่มนับ cooldown ที่นี่จุดเดียว
     * กดส่งใหม่ทันทีหลังสมัครจึงถูกกันด้วย ไม่ใช่แค่การกดซ้ำ
     */
    public function sendEmailVerificationNotification(): void
    {
        RateLimiter::hit($this->verificationCooldownKey(), self::VERIFICATION_COOLDOWN);

        parent::sendEmailVerificationNotification();
    }

    /** วินาทีที่เหลือก่อนส่งลิงก์ยืนยันได้อีกครั้ง · 0 = ส่งได้เลย */
    public function verificationCooldownRemaining(): int
    {
        return RateLimiter::tooManyAttempts($this->verificationCooldownKey(), 1)
            ? max(1, RateLimiter::availableIn($this->verificationCooldownKey()))
            : 0;
    }

    private function verificationCooldownKey(): string
    {
        return 'verify-email:'.$this->getKey();
    }

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
