<?php

namespace App\Support;

/**
 * ป้ายภาษาไทยของ Audit Log (ตาราง admin_actions) — ใช้แสดงบนหน้าจอเท่านั้น
 * ฐานข้อมูลและไฟล์ CSV ยังเก็บรหัสเดิม (เช่น parking_slot.create) เพื่อให้ค้นหา/นำไปประมวลผลต่อได้
 * รหัสที่ไม่รู้จักแสดงรหัสเดิม ไม่ซ่อนข้อมูล
 */
final class AuditCatalog
{
    private const ACTIONS = [
        'auth.login'                  => 'เข้าสู่ระบบ',
        'auth.logout'                 => 'ออกจากระบบ',
        'auth.register'               => 'สมัครสมาชิก',
        'auth.email_verified'         => 'ยืนยันอีเมล',
        'auth.password_reset'         => 'ตั้งรหัสผ่านใหม่ทางอีเมล',
        'auth.password_change'        => 'เปลี่ยนรหัสผ่าน',
        'profile.update'              => 'แก้ไขโปรไฟล์',
        'profile.delete'              => 'ลบบัญชีของตัวเอง',

        'user.create'                 => 'สร้างบัญชีผู้ใช้',
        'user.update'                 => 'แก้ไขบัญชีผู้ใช้',
        'user.force_reset'            => 'ตั้งรหัสผ่านชั่วคราว',
        'user.demote_owner'           => 'ปลดเจ้าของลาน',
        'user.delete'                 => 'ลบบัญชีผู้ใช้',

        'owner_application.submit'    => 'ยื่นคำขอเป็นเจ้าของลาน',
        'owner_application.resubmit'  => 'แก้ไขและส่งคำขอใหม่',
        'owner_application.approve'   => 'อนุมัติคำขอเป็นเจ้าของลาน',
        'owner_application.reject'    => 'ไม่อนุมัติคำขอเป็นเจ้าของลาน',
        'owner_resignation.submit'    => 'ยื่นคำร้องลาออก',
        'owner_resignation.approve'   => 'อนุมัติคำร้องลาออก',
        'owner_resignation.reject'    => 'ไม่อนุมัติคำร้องลาออก',

        'parking_lot.create'          => 'เพิ่มลานจอด',
        'parking_lot.update'          => 'แก้ไขลานจอด',
        'parking_lot.delete'          => 'ลบลานจอด',
        'parking_slot.create'         => 'เพิ่มช่องจอด',
        'parking_slot.bulk_create'    => 'เพิ่มช่องจอดหลายช่อง',
        'parking_slot.update'         => 'แก้ไขช่องจอด',
        'parking_slot.delete'         => 'ลบช่องจอด',

        'reservation.create'          => 'สร้างการจอง',
        'reservation.update_vehicle'  => 'แก้ไขข้อมูลรถของการจอง',
        'reservation.confirm'         => 'ยืนยันการจอง',
        'reservation.cancel'          => 'ยกเลิกการจอง',
        'reservation.expire'          => 'การจองหมดอายุ',
        'reservation.check_in'        => 'Check-in',
        'reservation.auto_check_in'   => 'Check-in อัตโนมัติ',
        'reservation.walk_in'         => 'รับรถ Walk-in',
        'reservation.check_out'       => 'Check-out',

        'payment.deposit_created'     => 'สร้างรายการมัดจำ',
        'payment.checkout_created'    => 'สร้างรายการค่าจอด',
        'payment.mark_paid'           => 'ยืนยันรับเงิน',
        'payment.void'                => 'ยกเลิกรายการชำระเงิน',

        'suspicious_vehicle.create'   => 'เพิ่มทะเบียนในบัญชีดำ',
        'suspicious_vehicle.update'   => 'แก้ไขบัญชีดำ',
        'suspicious_vehicle.toggle'   => 'เปิดใช้ / ระงับบัญชีดำ',
        'suspicious_vehicle.delete'   => 'ลบทะเบียนออกจากบัญชีดำ',

        'ai_scan.low_accuracy'        => 'AI สแกน: ความแม่นยำต่ำ',
        'ai_scan.unreadable'          => 'AI สแกน: อ่านทะเบียนไม่ได้',
        'ai_scan.blacklist_detected'  => 'AI สแกน: พบรถในบัญชีดำ',
        'ai_scan.early_arrival'       => 'AI สแกน: รถมาก่อนเวลาจอง',
        'ai_scan.vehicle_mismatch'    => 'AI สแกน: ยี่ห้อ/สีไม่ตรงกับการจอง',
        'ai_scan.booking_not_used'    => 'AI สแกน: ใช้การจองไม่ได้ จึงรับเป็น Walk-in',
        'ai_scan.parked_elsewhere'    => 'AI สแกน: รถจอดอยู่ลานอื่น',
        'ai_scan.check_out_failed'    => 'AI สแกน: Check-out อัตโนมัติไม่สำเร็จ',
    ];

    private const SUBJECTS = [
        'User'              => 'ผู้ใช้',
        'OwnerApplication'  => 'คำขอเป็นเจ้าของลาน',
        'OwnerResignation'  => 'คำร้องลาออก',
        'ParkingLot'        => 'ลานจอด',
        'ParkingSlot'       => 'ช่องจอด',
        'Reservation'       => 'การจอง',
        'Payment'           => 'การชำระเงิน',
        'SuspiciousVehicle' => 'บัญชีดำ',
        'LicensePlateScan'  => 'ผลสแกน',
    ];

    private const META_KEYS = [
        'license_plate'          => 'ทะเบียน',
        'plate_province'         => 'จังหวัด',
        'brand'                  => 'ยี่ห้อ',
        'color'                  => 'สี',
        'booked_brand'           => 'ยี่ห้อที่จอง',
        'booked_color'           => 'สีที่จอง',
        'scanned_brand'          => 'ยี่ห้อที่ AI อ่าน',
        'scanned_color'          => 'สีที่ AI อ่าน',
        'confidence'             => 'ความแม่นยำ',
        'level'                  => 'ระดับความเสี่ยง',
        'is_active'              => 'ใช้งาน',
        'reason'                 => 'เหตุผล',
        'note'                   => 'หมายเหตุ',
        'error'                  => 'ข้อผิดพลาด',
        'mode'                   => 'วิธี',
        'type'                   => 'ประเภท',
        'old_status'             => 'สถานะเดิม',
        'deposit_status'         => 'สถานะมัดจำ',
        'reserve_start'          => 'เวลาเริ่มจอง',
        'early_arrival'          => 'มาก่อนเวลา',
        'lot_full'               => 'ลานเต็ม',
        'total_amount'           => 'ยอดเงิน',
        'parking_fee'            => 'ค่าจอด',
        'deposit_deduction'      => 'หักมัดจำ',
        'reservation_discount'   => 'ส่วนลดการจอง',
        'hourly_rate'            => 'อัตราต่อชั่วโมง',
        'total_hours'            => 'จำนวนชั่วโมง',
        'reservation_id'         => 'การจอง',
        'walk_in_reservation_id' => 'การจอง Walk-in',
        'parking_lot_id'         => 'ลาน',
        'scanned_lot_id'         => 'ลานที่สแกน',
        'parked_lot_id'          => 'ลานที่จอดอยู่',
        'parking_slot_id'        => 'ช่อง',
        'slot_number'            => 'เลขช่อง',
        'slot_numbers'           => 'เลขช่อง',
        'count'                  => 'จำนวน',
        'parking_log_id'         => 'ประวัติการจอด',
        'scan_id'                => 'ผลสแกน',
        'user_id'                => 'ผู้ใช้',
        'owner_id'               => 'เจ้าของลาน',
        'uploaded_by'            => 'ผู้อัปโหลด',
        'name'                   => 'ชื่อ',
        'email'                  => 'อีเมล',
        'role'                   => 'บทบาท',
        'remember'               => 'จดจำการเข้าสู่ระบบ',
        'force_password_reset'   => 'ต้องเปลี่ยนรหัสผ่าน',
        'changes'                => 'การเปลี่ยนแปลง',
        'reservations_cancelled' => 'การจองที่ยกเลิก',
        'cars_checked_out'       => 'รถที่ Check-out',
        'lots_deleted'           => 'ลานที่ลบ',
    ];

    /** ค่าที่เป็นรหัส → ข้อความ (ตามชื่อฟิลด์) */
    private const META_VALUES = [
        'reason' => [
            'lot_full'              => 'ลานเต็ม',
            'reservation_cancelled' => 'การจองถูกยกเลิก',
            'reservation_expired'   => 'การจองหมดอายุ',
            'owner_demoted'         => 'ปลดเจ้าของลาน',
            'owner_resignation'     => 'เจ้าของลานลาออก',
            'user_deleted'          => 'ลบบัญชี',
        ],
        'level' => ['low' => 'ต่ำ', 'medium' => 'กลาง', 'high' => 'สูง'],
        'type'  => ['deposit' => 'มัดจำ', 'checkout' => 'ค่าจอด'],
        'mode'  => ['manual' => 'เจ้าหน้าที่ทำเอง', 'auto' => 'อัตโนมัติ'],
    ];

    private const ID_KEYS = [
        'reservation_id', 'walk_in_reservation_id', 'parking_lot_id', 'scanned_lot_id', 'parked_lot_id',
        'parking_slot_id', 'parking_log_id', 'scan_id', 'user_id', 'owner_id', 'uploaded_by',
    ];

    private const MONEY_KEYS = ['total_amount', 'parking_fee', 'deposit_deduction', 'reservation_discount', 'hourly_rate'];

    public static function action(?string $action): string
    {
        return self::ACTIONS[(string) $action] ?? (string) $action;
    }

    /** กลุ่มของ action (ส่วนหน้าจุด) — ใช้จัดกลุ่มตัวเลือกในตัวกรอง */
    public static function group(?string $action): string
    {
        return match (strtok((string) $action, '.')) {
            'auth', 'profile' => 'บัญชีและการเข้าสู่ระบบ',
            'user' => 'จัดการผู้ใช้',
            'owner_application', 'owner_resignation' => 'เจ้าของลาน',
            'parking_lot', 'parking_slot' => 'ลานและช่องจอด',
            'reservation' => 'การจอง',
            'payment' => 'การชำระเงิน',
            'suspicious_vehicle' => 'บัญชีดำ',
            'ai_scan' => 'AI สแกน',
            default => 'อื่น ๆ',
        };
    }

    public static function subject(?string $type): string
    {
        return self::SUBJECTS[(string) $type] ?? (string) $type;
    }

    /**
     * Meta (JSON) → รายการ [label, value] ที่อ่านได้
     *
     * @return list<array{label: string, value: string}>
     */
    public static function meta(?string $json): array
    {
        $data = $json ? json_decode($json, true) : null;

        if (! is_array($data)) {
            return [];
        }

        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = ['label' => self::META_KEYS[$key] ?? (string) $key, 'value' => self::value((string) $key, $value)];
        }

        return $rows;
    }

    private static function value(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'ใช่' : 'ไม่ใช่';
        }

        if ($key === 'changes' && is_array($value)) {
            return collect($value)->map(function ($change, $field) {
                $from = is_array($change) ? ($change['from'] ?? null) : null;
                $to = is_array($change) ? ($change['to'] ?? null) : $change;

                return (self::META_KEYS[$field] ?? $field).': '.self::scalar($from).' → '.self::scalar($to);
            })->implode(' · ');
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($v) => self::scalar($v))->implode(', ');
        }

        if (isset(self::META_VALUES[$key][(string) $value])) {
            return self::META_VALUES[$key][(string) $value];
        }

        return match (true) {
            in_array($key, self::ID_KEYS, true) => '#'.$value,
            in_array($key, self::MONEY_KEYS, true) && is_numeric($value) => Format::baht($value),
            $key === 'confidence' && is_numeric($value) => number_format((float) $value, 1).'%',
            $key === 'old_status' => StatusCatalog::label('reservation', (string) $value, 'staff'),
            $key === 'deposit_status' => StatusCatalog::label('payment', (string) $value, 'staff'),
            $key === 'role' => Navigation::ROLE_LABELS[(string) $value] ?? (string) $value,
            default => self::scalar($value),
        };
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => '—',
            is_bool($value) => $value ? 'ใช่' : 'ไม่ใช่',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
