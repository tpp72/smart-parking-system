<?php

namespace App\Models\Concerns;

use App\Support\LicensePlateNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * เทียบทะเบียนโดยไม่สนตัวคั่น — ข้อมูลที่บันทึกใหม่เป็นรูปแบบมาตรฐานอยู่แล้ว
 * แต่ข้อมูลเก่าหรือค่าที่นำเข้าจากที่อื่นอาจใช้ยัติภังค์หรือไม่มีตัวคั่น จึงเทียบด้วยกุญแจที่ถอดตัวคั่นออก
 *
 * ทำการเทียบในฐานข้อมูล ไม่ดึงทั้งตารางมา normalize ใน PHP
 */
trait MatchesLicensePlate
{
    public function scopeWherePlateMatches(Builder $query, ?string $plate, string $column = 'license_plate'): Builder
    {
        $key = LicensePlateNormalizer::comparisonKey($plate);

        if ($key === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw(
            LicensePlateNormalizer::sqlComparisonKey($query->qualifyColumn($column)).' = ?',
            [$key]
        );
    }

    /** ค้นหาบางส่วน (ช่องค้นหา) — พิมพ์แบบไหนก็เจอ เช่น "กข1234" เจอ "กข 1234" */
    public function scopeWherePlateLike(Builder $query, string $term, string $column = 'license_plate'): Builder
    {
        $key = LicensePlateNormalizer::comparisonKey($term);

        if ($key === null) {
            return $query;
        }

        return $query->whereRaw(
            LicensePlateNormalizer::sqlComparisonKey($query->qualifyColumn($column)).' ilike ?',
            ['%'.$key.'%']
        );
    }
}
