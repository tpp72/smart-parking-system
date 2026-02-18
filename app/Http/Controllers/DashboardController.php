<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function user()
    {
        $userId = Auth::id();
        $user = Auth::user();

        if ($user?->force_password_reset) {
            return redirect()->route('profile.edit')
                ->with('warning', 'กรุณาเปลี่ยนรหัสผ่านก่อนเข้าใช้งาน Dashboard');
        }

        $userId = $user->id;
        $now = now();

        // ===== Global slots stats (optional for user) =====
        $slotStats = DB::table('parking_slots')
            ->selectRaw("
            COUNT(*) as slots_total,
            SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as slots_available,
            SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as slots_reserved,
            SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as slots_occupied
        ")->first();

        $myVehiclesCount = DB::table('vehicles')->where('user_id', $userId)->count();

        // ===== 1) Active parking (สำคัญสุด) =====
        $activeLog = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->leftJoin('payments as p', 'p.parking_log_id', '=', 'pl.id')
            ->whereNull('pl.check_out_time')
            ->where('v.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->select([
                'pl.id as log_id',
                'v.license_plate',
                'lot.name as lot_name',
                's.slot_number',
                'pl.check_in_time',
                'p.id as payment_id',
                'p.payment_status',
                'p.total_amount',
            ])
            ->first();

        // ===== 2) Active reservation (ถ้าไม่ได้กำลังจอด) =====
        $activeReservation = DB::table('reservations as r')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'r.parking_slot_id')
            ->where('r.user_id', $userId)
            ->where('r.reserve_end', '>', $now)
            ->whereIn('r.status', ['pending', 'confirmed', 'reserved']) // ถ้าคุณมีสถานะจริงอื่น ปรับได้
            ->orderBy('r.reserve_start')
            ->select([
                'r.id as reservation_id',
                'v.license_plate',
                'lot.name as lot_name',
                's.slot_number',
                'r.reserve_start',
                'r.reserve_end',
                'r.reservation_fee',
                'r.status',
            ])
            ->first();

        // ===== Lists (รองลงมา) =====
        $activeNowList = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->whereNull('pl.check_out_time')
            ->where('v.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->limit(5)
            ->select(['pl.id as log_id', 'v.license_plate', 'lot.name as lot_name', 's.slot_number', 'pl.check_in_time'])
            ->get();

        $recentReservations = DB::table('reservations as r')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->where('r.user_id', $userId)
            ->orderByDesc('r.created_at')
            ->limit(5)
            ->select(['r.id', 'v.license_plate', 'lot.name as lot_name', 'r.reserve_start', 'r.reserve_end', 'r.status'])
            ->get();

        $recentHistory = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->where('v.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->limit(8)
            ->select(['pl.id as log_id', 'v.license_plate', 'lot.name as lot_name', 'pl.check_in_time', 'pl.check_out_time'])
            ->get();

        // แนะนำ lots ที่ว่าง (user friendly)
        $lotsAvailable = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->groupBy('lot.id', 'lot.name')
            ->orderByDesc(DB::raw("SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END)"))
            ->limit(5)
            ->selectRaw("
            lot.id, lot.name,
            SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) as available
        ")
            ->get();

        $stats = [
            'slots_total'     => (int)($slotStats->slots_total ?? 0),
            'slots_available' => (int)($slotStats->slots_available ?? 0),
            'slots_reserved'  => (int)($slotStats->slots_reserved ?? 0),
            'slots_occupied'  => (int)($slotStats->slots_occupied ?? 0),
            'my_vehicles'     => (int)$myVehiclesCount,
            'active_now'      => $activeLog ? 1 : 0,
        ];

        return view('dashboard-user', compact(
            'stats',
            'activeLog',
            'activeReservation',
            'activeNowList',
            'recentReservations',
            'recentHistory',
            'lotsAvailable'
        ));
    }


    public function admin()
    {
        $range = request('range', 'today');   // today | 7d | month
        $lotId = request('lot_id');           // nullable
        $q = trim((string) request('q', '')); // plate search

        [$from, $to] = match ($range) {
            '7d'   => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        // ===== Slot stats (รวมเป็น 1 query) =====
        $slotStats = DB::table('parking_slots')
            ->selectRaw("
            COUNT(*) as slots_total,
            SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as slots_available,
            SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as slots_reserved,
            SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as slots_occupied
        ")->first();

        $lotsTotal = DB::table('parking_lots')->count();

        $activeCount = DB::table('parking_logs')
            ->when($lotId, fn($qq) => $qq->where('parking_lot_id', $lotId))
            ->whereNull('check_out_time')
            ->count();

        $stats = [
            'lots_total'       => (int)$lotsTotal,
            'slots_total'      => (int)($slotStats->slots_total ?? 0),
            'slots_available'  => (int)($slotStats->slots_available ?? 0),
            'slots_reserved'   => (int)($slotStats->slots_reserved ?? 0),
            'slots_occupied'   => (int)($slotStats->slots_occupied ?? 0),
            'active_now'       => (int)$activeCount,
            'revenue_paid'     => (float) DB::table('payments as p')
                ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
                ->where('p.payment_status', 'paid')
                ->whereBetween('p.created_at', [$from, $to])
                ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
                ->sum('p.total_amount'),
            'unpaid_count'     => (int) DB::table('payments as p')
                ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
                ->where('p.payment_status', 'unpaid')
                ->whereBetween('p.created_at', [$from, $to])
                ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
                ->count(),
        ];

        // Active now
        $activeNow = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->whereNull('pl.check_out_time')
            ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
            ->when($q !== '', fn($qq) => $qq->where('v.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('pl.check_in_time')
            ->limit(10)
            ->select(['pl.id as log_id', 'v.license_plate', 'u.name as user_name', 'lot.name as lot_name', 's.slot_number', 'pl.check_in_time'])
            ->get();

        // Lots overview
        $lotsOverview = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->groupBy('lot.id', 'lot.name', 'lot.total_slots', 'lot.hourly_rate')
            ->orderBy('lot.id')
            ->selectRaw("
            lot.id, lot.name, lot.total_slots, lot.hourly_rate,
            SUM(CASE WHEN s.status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN s.status = 'occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN s.status = 'reserved' THEN 1 ELSE 0 END) as reserved
        ")
            ->limit(8)
            ->get();

        // Latest scans
        $latestScans = DB::table('license_plate_scans as lps')
            ->join('entry_exit_devices as d', 'd.id', '=', 'lps.device_id')
            ->leftJoin('suspicious_vehicles as sv', 'sv.license_plate', '=', 'lps.license_plate')
            ->orderByDesc('lps.scan_time')
            ->limit(6)
            ->select([
                'lps.license_plate',
                'lps.scan_time',
                'd.device_type',
                'd.location',
                DB::raw("CASE WHEN sv.id IS NULL THEN false ELSE true END as is_suspicious"),
            ])
            ->get();

        // Unpaid payments
        $unpaidPayments = DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->where('p.payment_status', 'unpaid')
            ->whereBetween('p.created_at', [$from, $to])
            ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
            ->when($q !== '', fn($qq) => $qq->where('v.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('p.created_at')
            ->limit(8)
            ->select(['p.id as payment_id', 'v.license_plate', 'p.total_hours', 'p.total_amount'])
            ->get();

        // Recent penalties
        $recentPenalties = DB::table('penalties as pe')
            ->join('parking_logs as pl', 'pl.id', '=', 'pe.parking_log_id')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->whereBetween('pe.created_at', [$from, $to])
            ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
            ->when($q !== '', fn($qq) => $qq->where('v.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('pe.created_at')
            ->limit(8)
            ->select(['v.license_plate', 'pe.reason', 'pe.amount'])
            ->get();

        // Reservations
        $reservations = DB::table('reservations as r')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereBetween('r.created_at', [$from, $to])
            ->when($lotId, fn($qq) => $qq->where('r.parking_lot_id', $lotId))
            ->when($q !== '', fn($qq) => $qq->where('v.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('r.created_at')
            ->limit(8)
            ->select(['v.license_plate', 'u.name as user_name', 'lot.name as lot_name', 'r.reserve_start', 'r.reserve_end', 'r.status'])
            ->get();

        // Recent history
        $recentHistory = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('users as u', 'u.id', '=', 'v.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->whereBetween('pl.check_in_time', [$from, $to])
            ->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
            ->when($q !== '', fn($qq) => $qq->where('v.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('pl.check_in_time')
            ->limit(10)
            ->select(['v.license_plate', 'u.name as user_name', 'lot.name as lot_name', 'pl.check_in_time', 'pl.check_out_time'])
            ->get();

        // Slots preview (optional)
        $slotsPreview = DB::table('parking_slots as s')
            ->join('parking_lots as lot', 'lot.id', '=', 's.parking_lot_id')
            ->when($lotId, fn($qq) => $qq->where('s.parking_lot_id', $lotId))
            ->orderByDesc('s.updated_at')
            ->limit(8)
            ->select(['s.slot_number', 's.status', 'lot.name as lot_name'])
            ->get();

        return view('admin.dashboard', compact(
            'stats',
            'activeNow',
            'lotsOverview',
            'latestScans',
            'unpaidPayments',
            'recentPenalties',
            'reservations',
            'recentHistory',
            'slotsPreview'
        ));
    }
}
