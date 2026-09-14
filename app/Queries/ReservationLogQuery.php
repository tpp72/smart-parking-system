<?php

namespace App\Queries;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reservation Log (ประวัติสถานะการจอง) พร้อมตัวกรอง — ใช้ร่วมกันระหว่าง Admin (ทั้งระบบ) และ Owner (ลานของตัวเอง)
 * รวมรายการที่ระบบเป็นผู้เปลี่ยนสถานะ (changed_by ว่าง) เช่น Auto Check-in / Walk-in / Expire
 */
class ReservationLogQuery
{
    /** @param iterable<int>|null $lotIds null = ทุกลาน */
    public static function build(Request $request, ?iterable $lotIds = null): Builder
    {
        $q = trim((string) $request->query('q', ''));

        return DB::table('reservation_logs as rl')
            ->join('reservations as r', 'r.id', '=', 'rl.reservation_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->leftJoin('users as u', 'u.id', '=', 'rl.changed_by')
            ->select([
                'rl.id',
                'rl.reservation_id',
                'rl.old_status',
                'rl.new_status',
                'rl.changed_by',
                'rl.note',
                'rl.created_at',
                'r.license_plate',
                'r.plate_province',
                'r.is_walk_in',
                'lot.id as lot_id',
                'lot.name as lot_name',
                'u.name as changed_by_name',
                'u.email as changed_by_email',
                'u.role as changed_by_role',
            ])
            ->when($lotIds !== null, fn ($query) => $query->whereIn('r.parking_lot_id', $lotIds))
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('r.license_plate', 'ilike', "%{$q}%")
                    ->orWhere('u.name', 'ilike', "%{$q}%")
                    ->orWhere('u.email', 'ilike', "%{$q}%")
                    ->orWhere('rl.note', 'ilike', "%{$q}%")
                    ->orWhereRaw('CAST(rl.reservation_id AS TEXT) = ?', [ltrim($q, '#')]);
            }))
            ->when($request->query('lot_id'), fn ($query, $lotId) => $query->where('r.parking_lot_id', $lotId))
            ->when($request->query('old_status'), fn ($query, $status) => $query->where('rl.old_status', $status))
            ->when($request->query('new_status'), fn ($query, $status) => $query->where('rl.new_status', $status))
            ->when($request->query('changed_by') === 'system', fn ($query) => $query->whereNull('rl.changed_by'))
            ->when($request->query('from'), fn ($query, $date) => $query->whereDate('rl.created_at', '>=', $date))
            ->when($request->query('to'), fn ($query, $date) => $query->whereDate('rl.created_at', '<=', $date))
            ->orderByDesc('rl.id');
    }
}
