<?php

namespace App\Rules;

use App\Support\LicensePlateNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * กันข้อความที่เป็นทะเบียนไม่ได้ เช่น "ฟ้าพฟฟด" หรืออีโมจิ
 *
 * ไม่ได้ตรวจว่าทะเบียนถูกต้องตามกฎหมาย และไม่บังคับรูปแบบ — ป้ายลักษณะพิเศษอย่าง
 * "น่ารัก 9999" หรือ "งาม 1" ผ่านกฎนี้ปกติ (ดู LicensePlateNormalizer::isPlausible)
 */
class PlausibleLicensePlate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (LicensePlateNormalizer::isPlausible(is_string($value) ? $value : null)) {
            return;
        }

        $normalized = LicensePlateNormalizer::normalize(is_string($value) ? $value : null);

        if ($normalized !== null && preg_match('/\d/u', $normalized) !== 1) {
            $fail('เลขทะเบียนต้องมีตัวเลขอย่างน้อย 1 ตัว เช่น กข 1234 หรือ น่ารัก 9999');

            return;
        }

        $fail('เลขทะเบียนใช้ได้เฉพาะตัวอักษรไทย อังกฤษ และตัวเลข');
    }
}
