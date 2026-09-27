<?php

namespace App\Support;

use Normalizer;

/**
 * จัดรูปแบบเลขทะเบียนรถให้เป็นมาตรฐานเดียวของระบบ (docs/project-plan.md §4.1)
 *
 * ไม่ใช่ตัวตรวจว่าทะเบียนถูกต้องตามกฎหมาย — ทะเบียนไทยมีหลายรูปแบบ และประกาศกรมการขนส่งทางบก
 * เปิดให้ป้ายลักษณะพิเศษมีตัวอักษรมากกว่า 2 ตัว หรือผสมสระ/วรรณยุกต์ได้
 * หน้าที่ของคลาสนี้คือทำให้ค่าที่ "เป็นทะเบียนเดียวกัน" ถูกเก็บและเทียบได้ตรงกัน
 * ถ้าอ่านโครงสร้างไม่ออก ให้คงข้อมูลไว้ ไม่ตัดทิ้งและไม่เดาตำแหน่งตัวคั่น
 *
 * ตัวคั่นมาตรฐานของระบบคือ "ช่องว่าง 1 ตัว" · ฐานข้อมูลและหน้าจอใช้ค่าเดียวกัน
 */
class LicensePlateNormalizer
{
    /** ตัวเลขไทย → ตัวเลขอารบิก */
    private const THAI_DIGITS = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];

    /**
     * โครงสร้างที่ระบบแยกหมวดกับเลขได้อย่างมั่นใจ เพราะขอบเขตอยู่ตรงที่ "เปลี่ยนชนิดอักขระ"
     * ไม่ใช่การเดา — [ก-ฮ] คือพยัญชนะไทยล้วน (U+0E01–U+0E2E) สระและวรรณยุกต์ไม่เข้าเงื่อนไขนี้
     * ทะเบียนลักษณะพิเศษอย่าง "น่ารัก9999" จึงไม่ถูกแตะ
     */
    private const SPLITTABLE = [
        '/^(\d?[ก-ฮ]{1,3})(\d{1,4})$/u',   // กข1234 · 1กข1234 · ขคง123 (จักรยานยนต์)
        '/^(\d?[A-Z]{1,3})(\d{1,4})$/u',   // ป้ายใช้ข้ามแดน เช่น A1 2000
    ];

    /**
     * ค่าที่ใช้เก็บลงฐานข้อมูลและแสดงบนหน้าจอ
     *
     * ทำ: NFC · ตัดช่องว่างหัวท้าย · ยุบตัวคั่นทุกชนิดให้เหลือช่องว่างเดียว · เลขไทยเป็นเลขอารบิก
     *     อักษรละตินเป็นตัวพิมพ์ใหญ่ · เติมช่องว่างให้เฉพาะรูปแบบที่แยกได้แน่ชัด
     * ไม่ทำ: ลบสระ/วรรณยุกต์ · ตัดอักขระที่อ่านไม่ออก · ปฏิเสธค่าที่ไม่รู้จัก
     */
    public static function normalize(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        $value = class_exists(Normalizer::class)
            ? (Normalizer::normalize($plate, Normalizer::FORM_C) ?: $plate)
            : $plate;

        $value = str_replace(self::THAI_DIGITS, range(0, 9), $value);
        $value = mb_strtoupper($value, 'UTF-8');

        // ตัวคั่นทุกแบบ (ช่องว่างทุกชนิด ยัติภังค์ ขีดล่าง) และที่ติดกันหลายตัว → ช่องว่างเดียว
        $value = preg_replace('/[\s\-_\x{00A0}]+/u', ' ', $value) ?? $value;
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! str_contains($value, ' ')) {
            foreach (self::SPLITTABLE as $pattern) {
                if (preg_match($pattern, $value, $m)) {
                    return $m[1].' '.$m[2];
                }
            }
        }

        return $value;
    }

    /**
     * กุญแจสำหรับเทียบว่าเป็นทะเบียนเดียวกันหรือไม่ — ถอดเฉพาะตัวคั่นออก ไม่แตะเนื้อทะเบียน
     *
     * กข1234 · กข 1234 · กข-1234 → "กข1234"
     * น่ารัก 9999 · น่ารัก9999   → "น่ารัก9999"
     */
    public static function comparisonKey(?string $plate): ?string
    {
        $value = self::normalize($plate);

        return $value === null ? null : str_replace(' ', '', $value);
    }

    /**
     * ระบบแยกหมวดกับเลขของทะเบียนนี้ได้หรือไม่ — ใช้บอกสถานะเท่านั้น
     * ห้ามใช้เป็นเงื่อนไขปฏิเสธการบันทึก เพราะทะเบียนลักษณะพิเศษจะตกทันที
     */
    public static function isRecognizableFormat(?string $plate): bool
    {
        $value = self::normalize($plate);

        if ($value === null) {
            return false;
        }

        foreach (self::SPLITTABLE as $pattern) {
            if (preg_match($pattern, str_replace(' ', '', $value))) {
                return true;
            }
        }

        // เลข 2–3 หลัก + เลข 4 หลัก (รถบรรทุก/รถโดยสาร) รู้จักได้ต่อเมื่อมีตัวคั่นมาแล้ว
        return (bool) preg_match('/^\d{2,3} \d{4}$/u', $value);
    }

    /** อักขระที่เป็นส่วนของทะเบียนได้: อักษรไทยทั้งบล็อก (รวมสระ/วรรณยุกต์) · ละติน · ตัวเลข · ช่องว่าง */
    public const ALLOWED_PATTERN = '/^[\x{0E00}-\x{0E7F}A-Z0-9 ]+$/u';

    /**
     * ค่านี้ "เป็นทะเบียนได้หรือไม่" — กันคำมั่ว ๆ อย่าง "ฟ้าพฟฟด" ไม่ให้หลุดเข้าฐานข้อมูล
     *
     * ไม่ใช่การตรวจว่าถูกต้องตามกฎหมาย และไม่ได้บังคับรูปแบบ — ใช้เพียง 3 เงื่อนไขเชิงโครงสร้าง
     * ที่ทะเบียนทุกประเภทตามประกาศกรมการขนส่งทางบกผ่านได้ รวมป้ายลักษณะพิเศษ:
     *   1. มีตัวเลขอย่างน้อย 1 ตัว — ทุกรูปแบบมีเลขทะเบียนเสมอ ("น่ารัก 9999", "งาม 1", "43 8633")
     *   2. ใช้เฉพาะอักษรไทย/ละติน/ตัวเลข/ช่องว่าง — กันอีโมจิ HTML และอักขระแปลกปลอม
     *   3. ยาว 2–20 ตัวอักษร ตามขนาดคอลัมน์
     */
    public static function isPlausible(?string $plate): bool
    {
        $value = self::normalize($plate);

        if ($value === null) {
            return false;
        }

        $length = mb_strlen($value);

        return $length >= 2
            && $length <= 20
            && preg_match('/\d/u', $value) === 1
            && preg_match(self::ALLOWED_PATTERN, $value) === 1;
    }

    /**
     * นิพจน์ SQL ที่ถอดตัวคั่นออกจากคอลัมน์ เพื่อให้ค้นหาด้วยรูปแบบไหนก็เจอ
     * ใช้คู่กับ comparisonKey() ของคำค้น — ข้อมูลระดับนี้ (หลักร้อยแถว) ยังไม่ต้องมี index เฉพาะ
     */
    public static function sqlComparisonKey(string $column): string
    {
        return "replace(replace($column, ' ', ''), '-', '')";
    }

    /**
     * เงื่อนไขค้นหาบางส่วนสำหรับ query builder ที่ใช้ชื่อตารางย่อ (เช่น r.license_plate)
     * คืน [sql, bindings] · คืน null เมื่อคำค้นไม่เหลือเนื้อหลังถอดตัวคั่น (เช่นพิมพ์แต่ "-")
     *
     * @return array{0: string, 1: array<int, string>}|null
     */
    public static function sqlLike(string $column, ?string $term): ?array
    {
        $key = self::comparisonKey($term);

        return $key === null
            ? null
            : [self::sqlComparisonKey($column).' ilike ?', ['%'.$key.'%']];
    }
}
