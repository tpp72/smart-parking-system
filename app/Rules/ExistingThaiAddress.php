<?php

namespace App\Rules;

use App\Support\ThaiGeography;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ตรวจว่า จังหวัด + อำเภอ/เขต + ตำบล/แขวง ที่ส่งมาอยู่ใต้กันจริงตามเขตการปกครองไทย
 * ผูกไว้ที่ช่องตำบล/แขวง เพราะเป็นชั้นล่างสุดและเป็นตัวกำหนดรหัสไปรษณีย์
 *
 * จำเป็นต้องตรวจฝั่งเซิร์ฟเวอร์ เพราะ dropdown ที่ขึ้นต่อกันเป็นชั้นทำงานด้วย JavaScript
 * ผู้ใช้ส่งค่าที่ไม่เข้าคู่กันมาได้ถ้าข้ามหน้าเว็บไป
 */
class ExistingThaiAddress implements ValidationRule
{
    public function __construct(
        private ?string $province,
        private ?string $district,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $subdistrict = is_string($value) ? $value : null;

        if (ThaiGeography::isValidCombination($this->province, $this->district, $subdistrict)) {
            return;
        }

        if (! in_array($this->province, ThaiGeography::provinces(), true)) {
            $fail('กรุณาเลือกจังหวัดจากรายการ');

            return;
        }

        if (! in_array($this->district, ThaiGeography::districts($this->province), true)) {
            $fail('อำเภอ / เขตนี้ไม่ได้อยู่ในจังหวัดที่เลือก');

            return;
        }

        $fail('ตำบล / แขวงนี้ไม่ได้อยู่ในอำเภอ / เขตที่เลือก');
    }
}
