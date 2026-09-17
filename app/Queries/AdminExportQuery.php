<?php

namespace App\Queries;

use App\Models\ParkingLot;
use App\Models\Payment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * ข้อมูลสำหรับ CSV Export ของ Admin — ทั้งระบบ ทุกลาน กรองลานได้ (project-plan.md §17.5.1)
 * ตัวกรองรับค่าที่ผ่าน Validation แล้ว: q, lot_id, from, to และตัวกรองเฉพาะประเภท
 */
class AdminExportQuery
{
    public static function reservations(array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->leftJoin('users as o', 'o.id', '=', 'lot.owner_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'r.parking_slot_id')
            ->leftJoin('payments as d', fn ($join) => $join->on('d.reservation_id', '=', 'r.id')->where('d.type', Payment::TYPE_DEPOSIT))
            ->select([
                'r.id', 'r.created_at', 'r.reserve_start', 'r.status', 'r.is_walk_in',
                'r.license_plate', 'r.plate_province', 'r.brand', 'r.color',
                'r.deposit_amount', 'r.reservation_fee', 'r.checked_in_at', 'r.completed_at',
                'lot.name as lot_name', 'o.name as owner_name',
                'u.name as user_name', 'u.email as user_email',
                's.slot_number',
                'd.payment_status as deposit_status', 'd.paid_at as deposit_paid_at',
            ])
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('r.license_plate', 'ilike', "%{$q}%")
                    ->orWhere('u.name', 'ilike', "%{$q}%")
                    ->orWhere('u.email', 'ilike', "%{$q}%");
            }))
            ->when($filters['lot_id'] ?? null, fn ($query, $lotId) => $query->where('r.parking_lot_id', $lotId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('r.status', $status))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('r.reserve_start', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('r.reserve_start', '<=', $date))
            ->orderByDesc('r.id');
    }

    public static function parkingLogs(array $filters): Builder
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return DB::table('parking_logs as pl')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('users as o', 'o.id', '=', 'lot.owner_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->leftJoin('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('payments as p', 'p.parking_log_id', '=', 'pl.id')
            ->select([
                'pl.id', 'pl.reservation_id', 'pl.license_plate', 'pl.plate_province', 'pl.brand', 'pl.color',
                'pl.check_in_time', 'pl.check_out_time', 'pl.hourly_rate',
                'r.is_walk_in', 'u.name as user_name',
                'lot.name as lot_name', 'o.name as owner_name', 's.slot_number',
                'p.total_hours', 'p.parking_fee', 'p.deposit_deduction', 'p.reservation_discount',
                'p.total_amount', 'p.payment_status', 'p.paid_at',
            ])
            ->when($q !== '', fn ($query) => $query->where('pl.license_plate', 'ilike', "%{$q}%"))
            ->when($filters['lot_id'] ?? null, fn ($query, $lotId) => $query->where('pl.parking_lot_id', $lotId))
            ->when(($filters['state'] ?? null) === 'parked', fn ($query) => $query->whereNull('pl.check_out_time'))
            ->when(($filters['state'] ?? null) === 'completed', fn ($query) => $query->whereNotNull('pl.check_out_time'))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('pl.check_in_time', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('pl.check_in_time', '<=', $date))
            ->orderByDesc('pl.id');
    }

    /** รายได้รายวัน แยกตามลาน = เงินที่รับจริง นับตามวันที่ยืนยันรับเงิน (§16.1) — ไม่ระบุช่วงวัน = เดือนปัจจุบัน */
    public static function dailyRevenue(array $filters): Builder
    {
        $lotIds = !empty($filters['lot_id']) ? [(int) $filters['lot_id']] : ParkingLot::pluck('id');

        return RevenueQuery::paid($lotIds)
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->leftJoin('users as o', 'o.id', '=', 'lot.owner_id')
            ->whereDate('p.paid_at', '>=', $filters['from'] ?? now()->startOfMonth()->toDateString())
            ->whereDate('p.paid_at', '<=', $filters['to'] ?? now()->toDateString())
            ->groupByRaw('DATE(p.paid_at)')
            ->groupBy('lot.id', 'lot.name', 'o.name')
            ->selectRaw("
                DATE(p.paid_at) as day, lot.id as lot_id, lot.name as lot_name, o.name as owner_name,
                SUM(CASE WHEN p.type = ? THEN p.total_amount ELSE 0 END) as deposit_revenue,
                SUM(CASE WHEN p.type = ? THEN p.total_amount ELSE 0 END) as parking_revenue,
                SUM(p.total_amount) as total_revenue,
                COUNT(*) as transactions
            ", [Payment::TYPE_DEPOSIT, Payment::TYPE_CHECKOUT])
            ->orderBy('day')
            ->orderBy('lot.id');
    }
}
