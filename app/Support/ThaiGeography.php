<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * เขตการปกครองไทย: จังหวัด → อำเภอ/เขต → ตำบล/แขวง → รหัสไปรษณีย์
 *
 * ข้อมูลอยู่ที่ public/data/thai-geography.json ไฟล์เดียว ใช้ร่วมกันทั้งฝั่งเซิร์ฟเวอร์
 * (ตรวจความถูกต้องและหารหัสไปรษณีย์) และฝั่งเบราว์เซอร์ (dropdown ที่ขึ้นต่อกันเป็นชั้น)
 * ที่มา: thailand-geography-data/thailand-geography-json — 77 จังหวัด · 928 อำเภอ/เขต · 7,436 ตำบล/แขวง
 *
 * รหัสไปรษณีย์ผูกกับ "ตำบล/แขวง" ไม่ใช่อำเภอ จึงต้องรู้ครบทั้ง 3 ระดับก่อนจึงเติมให้ได้
 */
class ThaiGeography
{
    public const PATH = 'data/thai-geography.json';

    /** @return array<string, array<string, array<string, int>>> */
    public static function all(): array
    {
        return Cache::rememberForever('thai_geography', function () {
            $file = public_path(self::PATH);

            return is_file($file)
                ? (json_decode((string) file_get_contents($file), true) ?: [])
                : [];
        });
    }

    /** @return list<string> */
    public static function provinces(): array
    {
        return array_keys(self::all());
    }

    /** อำเภอ/เขตของจังหวัดนั้น · @return list<string> */
    public static function districts(?string $province): array
    {
        return array_keys(self::all()[$province] ?? []);
    }

    /** ตำบล/แขวงของอำเภอนั้น · @return list<string> */
    public static function subdistricts(?string $province, ?string $district): array
    {
        return array_keys(self::all()[$province][$district] ?? []);
    }

    /** รหัสไปรษณีย์ของตำบล/แขวงนั้น — null เมื่อชุดที่ส่งมาไม่มีอยู่จริง */
    public static function postalCode(?string $province, ?string $district, ?string $subdistrict): ?string
    {
        $code = self::all()[$province][$district][$subdistrict] ?? null;

        return $code === null ? null : (string) $code;
    }

    /** ชุด จังหวัด + อำเภอ + ตำบล นี้มีอยู่จริงและอยู่ใต้กันจริงหรือไม่ */
    public static function isValidCombination(?string $province, ?string $district, ?string $subdistrict): bool
    {
        return self::postalCode($province, $district, $subdistrict) !== null;
    }
}
