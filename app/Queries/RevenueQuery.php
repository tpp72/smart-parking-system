<?php

namespace App\Queries;

use App\Models\Payment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * รายได้ของลาน = เงินที่รับจริง (project-plan.md §16.1, §17.2)
 * มัดจำที่ paid + ยอดค่าจอดหลัง Check-out ที่ paid (หักมัดจำแล้วจึงไม่นับซ้ำ) — ใช้เวลาที่ยืนยันรับเงิน (paid_at)
 */
class RevenueQuery
{
    /** @param iterable<int> $lotIds */
    public static function paid(iterable $lotIds): Builder
    {
        return DB::table('payments as p')
            ->join('reservations as r', 'r.id', '=', 'p.reservation_id')
            ->whereIn('r.parking_lot_id', $lotIds)
            ->where('p.payment_status', Payment::STATUS_PAID)
            // ยอด 0 ที่ระบบปิดให้เอง (ส่วนลดครอบคลุมค่าจอด) ไม่ใช่เงินที่รับ
            ->where('p.total_amount', '>', 0);
    }
}
