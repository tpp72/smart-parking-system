<?php

namespace App\Models;

use App\Models\Concerns\MatchesLicensePlate;
use App\Support\LicensePlateNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ทะเบียนรถที่ผู้ใช้ผูกไว้กับบัญชีตัวเอง — ใช้ผูกรถที่เข้าแบบ Walk-in เข้ากับเจ้าของบัญชี
 * ระบบยังเป็น Plate-based: การจองยังกรอกทะเบียนตอนจอง ไม่ต้องผูกรถก่อน
 */
class UserVehicle extends Model
{
    use HasFactory;
    use MatchesLicensePlate;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** บัญชีที่เป็นเจ้าของทะเบียนนี้ — null ถ้ายังไม่มีใครผูกไว้ */
    public static function ownerOf(?string $licensePlate, ?string $plateProvince): ?User
    {
        if (! filled($licensePlate) || ! filled($plateProvince)) {
            return null;
        }

        return static::with('user')
            ->wherePlateMatches($licensePlate)
            ->where('plate_province', $plateProvince)
            ->first()?->user;
    }

    /** เก็บทะเบียนเป็นรูปแบบมาตรฐานเสมอ ไม่ว่าจะมาจากทางไหน */
    public function setLicensePlateAttribute($value): void
    {
        $this->attributes['license_plate'] = LicensePlateNormalizer::normalize($value);
    }
}
