<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * รูปแบบวันเวลาและจำนวนเงินบนหน้าจอ — ปี ค.ศ. แบบ d/m/Y เหมือนช่องเลือกวันที่ · เวลา 24 ชั่วโมง
 * การจองล่วงหน้าได้ไม่เกิน 1 วัน จึงใช้ "วันนี้ / พรุ่งนี้ / เมื่อวาน" แทนวันที่เมื่ออยู่ในช่วงนั้น
 */
final class Format
{
    public static function parse(CarbonInterface|string|null $value): ?CarbonInterface
    {
        return $value === null ? null : ($value instanceof CarbonInterface ? $value : Carbon::parse($value));
    }

    /** "วันนี้ 14:05" · "พรุ่งนี้ 09:00" · "13/09/2026 17:12" */
    public static function short(CarbonInterface|string|null $value): string
    {
        if (! $date = self::parse($value)) {
            return '—';
        }

        $day = match (true) {
            $date->isToday() => 'วันนี้',
            $date->isTomorrow() => 'พรุ่งนี้',
            $date->isYesterday() => 'เมื่อวาน',
            default => $date->format('d/m/Y'),
        };

        return $day.' '.$date->format('H:i');
    }

    /** "14:05 น." */
    public static function time(CarbonInterface|string|null $value): string
    {
        return ($date = self::parse($value)) ? $date->format('H:i').' น.' : '—';
    }

    /** ระยะเวลาเป็นนาที → "2 ชม. 15 นาที" · "45 นาที" */
    public static function duration(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return trim(($hours ? "{$hours} ชม. " : '').($rest || ! $hours ? "{$rest} นาที" : ''));
    }

    /** เงินบาท: "฿1,234.00" */
    public static function baht(float|int|string|null $amount): string
    {
        return '฿'.number_format((float) $amount, 2);
    }
}
