<?php

namespace App\Support;

/**
 * แหล่งเดียวของป้ายสถานะภาษาไทย (UI Phase 1 Foundation)
 *
 * ค่าภาษาอังกฤษในฐานข้อมูล (pending, paid, occupied …) ห้ามแสดงบนหน้าจอ — ใช้ <x-ui.status> หรือ StatusCatalog::label()
 * ทุกสถานะสื่อด้วย 3 อย่างพร้อมกัน: ข้อความไทย + รูปทรง (shape) + สีหมึก (tone) ไม่พึ่งสีอย่างเดียว
 *
 * audience: 'user' = ลูกค้า · 'staff' = Owner/Admin — บางสถานะใช้ถ้อยคำต่างกันตามผู้ดู (project-plan / PRODUCT.md)
 */
final class StatusCatalog
{
    public const TONES = ['neutral', 'info', 'success', 'warning', 'danger'];

    public const SHAPES = ['pending', 'confirmed', 'active', 'done', 'cancelled', 'expired', 'void', 'alert', 'available', 'reserved', 'occupied', 'unknown'];

    public const AUDIENCES = ['user', 'staff'];

    private const UNKNOWN = ['label' => 'ไม่ทราบสถานะ', 'tone' => 'neutral', 'shape' => 'unknown'];

    /** label เป็น string (ทุกผู้ดูเหมือนกัน) หรือ ['user' => …, 'staff' => …] */
    private const CATALOG = [
        'reservation' => [
            'pending'    => ['label' => ['user' => 'รอเจ้าหน้าที่ยืนยันรับเงิน', 'staff' => 'รอยืนยันรับมัดจำ'], 'tone' => 'warning', 'shape' => 'pending'],
            'confirmed'  => ['label' => 'ยืนยันแล้ว', 'tone' => 'info', 'shape' => 'confirmed'],
            'checked_in' => ['label' => 'เช็คอินแล้ว', 'tone' => 'success', 'shape' => 'active'],
            'completed'  => ['label' => 'เสร็จสิ้น', 'tone' => 'neutral', 'shape' => 'done'],
            'cancelled'  => ['label' => 'ยกเลิก', 'tone' => 'danger', 'shape' => 'cancelled'],
            'expired'    => ['label' => 'หมดอายุ', 'tone' => 'neutral', 'shape' => 'expired'],
        ],
        'payment' => [
            'unpaid' => ['label' => 'ยังไม่ชำระ', 'tone' => 'warning', 'shape' => 'pending'],
            'paid'   => ['label' => 'ชำระแล้ว', 'tone' => 'success', 'shape' => 'done'],
            'void'   => ['label' => 'ยกเลิก', 'tone' => 'neutral', 'shape' => 'void'],
        ],
        'slot' => [
            'available' => ['label' => 'ว่าง', 'tone' => 'success', 'shape' => 'available'],
            'reserved'  => ['label' => 'จอง', 'tone' => 'warning', 'shape' => 'reserved'],
            'occupied'  => ['label' => 'ใช้งาน', 'tone' => 'danger', 'shape' => 'occupied'],
        ],
        'scan' => [
            'passed'       => ['label' => 'ผ่านเกณฑ์', 'tone' => 'success', 'shape' => 'done'],
            'low_accuracy' => ['label' => 'ความแม่นยำต่ำ', 'tone' => 'warning', 'shape' => 'alert'],
            'unreadable'   => ['label' => 'อ่านทะเบียนไม่ได้', 'tone' => 'danger', 'shape' => 'cancelled'],
        ],
        // คำขอเป็น Owner และคำร้องลาออก
        'review' => [
            'pending'  => ['label' => 'รอพิจารณา', 'tone' => 'warning', 'shape' => 'pending'],
            'approved' => ['label' => 'อนุมัติแล้ว', 'tone' => 'success', 'shape' => 'done'],
            'rejected' => ['label' => 'ไม่อนุมัติ', 'tone' => 'danger', 'shape' => 'cancelled'],
        ],
    ];

    /**
     * @return array{type: string, key: string, label: string, tone: string, shape: string, known: bool}
     */
    public static function resolve(string $type, ?string $value, string $audience = 'staff'): array
    {
        $entry = self::CATALOG[$type][(string) $value] ?? null;
        $known = $entry !== null;
        $entry ??= self::UNKNOWN;

        $label = $entry['label'];
        if (is_array($label)) {
            $label = $label[in_array($audience, self::AUDIENCES, true) ? $audience : 'staff'];
        }

        return [
            'type'  => $type,
            'key'   => $known ? (string) $value : 'unknown',
            'label' => $label,
            'tone'  => $entry['tone'],
            'shape' => $entry['shape'],
            'known' => $known,
        ];
    }

    public static function label(string $type, ?string $value, string $audience = 'staff'): string
    {
        return self::resolve($type, $value, $audience)['label'];
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::CATALOG);
    }

    /** @return array<int, string> */
    public static function values(string $type): array
    {
        return array_keys(self::CATALOG[$type] ?? []);
    }
}
