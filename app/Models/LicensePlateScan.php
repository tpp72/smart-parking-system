<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicensePlateScan extends Model
{
    /** อ่านทะเบียน + จังหวัดได้และ Accuracy > เกณฑ์ → เข้าสู่ Matching / Auto Check-in ได้ */
    const RESULT_PASSED = 'passed';

    /** Accuracy ไม่เกินเกณฑ์ (หรือ AI ไม่ส่ง Accuracy มา) → ไม่ผ่าน */
    const RESULT_LOW_ACCURACY = 'low_accuracy';

    /** AI อ่านทะเบียนไม่ได้ — รวมถึงอ่านเลขทะเบียนได้แต่อ่านจังหวัดไม่ได้ */
    const RESULT_UNREADABLE = 'unreadable';

    protected $guarded = [];

    protected $casts = [
        'scan_time'    => 'datetime',
        'is_suspicious' => 'boolean',
        'confidence'   => 'float',
    ];

    /** จัดผลการตรวจตามเกณฑ์ Accuracy > threshold (project-plan.md §10.3–10.4) */
    public static function classify(?string $licensePlate, ?string $plateProvince, ?float $confidence): string
    {
        if ($licensePlate === null || trim($licensePlate) === ''
            || $plateProvince === null || trim($plateProvince) === '') {
            return self::RESULT_UNREADABLE;
        }

        $threshold = (float) config('carscan.accuracy_threshold', 85);

        return $confidence !== null && $confidence > $threshold
            ? self::RESULT_PASSED
            : self::RESULT_LOW_ACCURACY;
    }

    public function passed(): bool
    {
        return $this->result === self::RESULT_PASSED;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parkingLot()
    {
        return $this->belongsTo(ParkingLot::class);
    }
}
