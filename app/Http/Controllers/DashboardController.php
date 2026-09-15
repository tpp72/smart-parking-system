<?php

namespace App\Http\Controllers;

use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Models\SuspiciousVehicle;
use App\Queries\RevenueQuery;
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

        // ===== 1) Active parking (สำคัญสุด) =====
        $activeLog = DB::table('parking_logs as pl')
            ->join('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->leftJoin('payments as p', 'p.parking_log_id', '=', 'pl.id')
            ->whereNull('pl.check_out_time')
            ->where('r.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->select([
                'pl.id as log_id',
                'pl.license_plate',
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
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'r.parking_slot_id')
            ->where('r.user_id', $userId)
            ->whereIn('r.status', ['pending', 'confirmed'])
            ->where('r.reserve_start', '>', $now->copy()->subHour())
            ->orderBy('r.reserve_start')
            ->select([
                'r.id as reservation_id',
                'r.license_plate',
                'lot.name as lot_name',
                's.slot_number',
                'r.reserve_start',
                'r.deposit_amount',
                'r.status',
            ])
            ->first();

        // ===== Lists (รองลงมา) =====
        $activeNowList = DB::table('parking_logs as pl')
            ->join('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->whereNull('pl.check_out_time')
            ->where('r.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->limit(5)
            ->select(['pl.id as log_id', 'pl.license_plate', 'lot.name as lot_name', 's.slot_number', 'pl.check_in_time'])
            ->get();

        $recentReservations = DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->where('r.user_id', $userId)
            ->orderByDesc('r.created_at')
            ->limit(5)
            ->select(['r.id', 'r.license_plate', 'lot.name as lot_name', 'r.reserve_start', 'r.status'])
            ->get();

        $recentHistory = DB::table('parking_logs as pl')
            ->join('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->where('r.user_id', $userId)
            ->orderByDesc('pl.check_in_time')
            ->limit(8)
            ->select(['pl.id as log_id', 'pl.license_plate', 'lot.name as lot_name', 'pl.check_in_time', 'pl.check_out_time'])
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

    /**
     * Admin Dashboard = ภาพรวมทั้งระบบ ทุกลาน (project-plan.md §17.1, §5.1.1)
     * — การจัดการ (ยกเลิก / เช็คอิน / Mark as Paid / แก้ไขลาน) ยังจำกัดเฉพาะลานของ Admin ในหน้าจัดการ
     */
    public function admin()
    {
        $range = request('range', 'today');   // today | 7d | month
        $lotId = request('lot_id');           // nullable
        $q = trim((string) request('q', '')); // plate search

        $allLotIds = ParkingLot::pluck('id');

        if ($lotId && !$allLotIds->contains((int) $lotId)) {
            $lotId = null;
        }

        $scopeLotIds = $lotId ? collect([(int) $lotId]) : $allLotIds;

        [$from, $to] = match ($range) {
            '7d'   => [now()->subDays(7)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        // ===== Slot stats (รวมเป็น 1 query) =====
        $slotStats = DB::table('parking_slots')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->selectRaw("
            COUNT(*) as slots_total,
            SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as slots_available,
            SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as slots_reserved,
            SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as slots_occupied
        ")->first();

        // รายได้ = เงินที่รับจริง (มัดจำ + ค่าจอด) นับตามเวลาที่ยืนยันรับเงิน — นิยามเดียวกับฝั่ง Owner
        $paid = fn () => RevenueQuery::paid($scopeLotIds)->whereBetween('p.paid_at', [$from, $to]);

        // สถิติ AI Scan ในช่วงเวลา
        $scanStats = DB::table('license_plate_scans')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->whereBetween('scan_time', [$from, $to])
            ->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed,
            SUM(CASE WHEN result IN ('low_accuracy', 'unreadable') THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN is_suspicious THEN 1 ELSE 0 END) as suspicious
        ")->first();

        $stats = [
            'lots_total'       => $allLotIds->count(),
            'admin_lots_total' => ParkingLot::unowned()->count(),
            'slots_total'      => (int)($slotStats->slots_total ?? 0),
            'slots_available'  => (int)($slotStats->slots_available ?? 0),
            'slots_reserved'   => (int)($slotStats->slots_reserved ?? 0),
            'slots_occupied'   => (int)($slotStats->slots_occupied ?? 0),
            'active_now'       => DB::table('parking_logs')
                ->whereIn('parking_lot_id', $scopeLotIds)
                ->whereNull('check_out_time')
                ->count(),
            'revenue_paid'     => (float) $paid()->sum('p.total_amount'),
            'revenue_deposit'  => (float) $paid()->where('p.type', Payment::TYPE_DEPOSIT)->sum('p.total_amount'),
            'revenue_parking'  => (float) $paid()->where('p.type', Payment::TYPE_CHECKOUT)->sum('p.total_amount'),
            'unpaid_count'     => DB::table('payments as p')
                ->join('reservations as r', 'r.id', '=', 'p.reservation_id')
                ->whereIn('r.parking_lot_id', $scopeLotIds)
                ->where('p.type', Payment::TYPE_CHECKOUT)
                ->where('p.payment_status', Payment::STATUS_UNPAID)
                ->whereBetween('p.created_at', [$from, $to])
                ->count(),
            'reservations_checked_in' => DB::table('reservations')
                ->whereIn('parking_lot_id', $scopeLotIds)
                ->where('status', 'checked_in')
                ->whereBetween('checked_in_at', [$from, $to])
                ->count(),
            'reservations_completed'  => DB::table('reservations')
                ->whereIn('parking_lot_id', $scopeLotIds)
                ->where('status', 'completed')
                ->whereBetween('completed_at', [$from, $to])
                ->count(),
            'scans_total'      => (int)($scanStats->total ?? 0),
            'scans_passed'     => (int)($scanStats->passed ?? 0),
            'scans_failed'     => (int)($scanStats->failed ?? 0),
            'scans_suspicious' => (int)($scanStats->suspicious ?? 0),
            'blacklist_active' => SuspiciousVehicle::active()->count(),
        ];

        // Active now
        $activeNow = DB::table('parking_logs as pl')
            ->leftJoin('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->whereIn('pl.parking_lot_id', $scopeLotIds)
            ->whereNull('pl.check_out_time')
            ->when($q !== '', fn($qq) => $qq->where('pl.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('pl.check_in_time')
            ->limit(10)
            ->select(['pl.id as log_id', 'pl.license_plate', 'u.name as user_name', 'lot.name as lot_name', 's.slot_number', 'pl.check_in_time'])
            ->get();

        // Lots overview — แสดงเจ้าของลาน (NULL = ลานของ Admin)
        $lotsOverview = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->leftJoin('users as o', 'o.id', '=', 'lot.owner_id')
            ->whereIn('lot.id', $scopeLotIds)
            ->groupBy('lot.id', 'lot.name', 'lot.total_slots', 'lot.hourly_rate', 'o.name')
            ->orderBy('lot.id')
            ->selectRaw("
            lot.id, lot.name, lot.total_slots, lot.hourly_rate, o.name as owner_name,
            SUM(CASE WHEN s.status = 'available' THEN 1 ELSE 0 END) as available,
            SUM(CASE WHEN s.status = 'occupied' THEN 1 ELSE 0 END) as occupied,
            SUM(CASE WHEN s.status = 'reserved' THEN 1 ELSE 0 END) as reserved
        ")
            ->limit(9)
            ->get();

        // Latest scans — Blacklist ตรวจด้วย ทะเบียน + จังหวัด
        $latestScans = DB::table('license_plate_scans as lps')
            ->leftJoin('suspicious_vehicles as sv', function ($join) {
                $join->on('sv.license_plate', '=', 'lps.license_plate')
                     ->on('sv.plate_province', '=', 'lps.plate_province')
                     ->where('sv.is_active', true);
            })
            ->whereIn('lps.parking_lot_id', $scopeLotIds)
            ->orderByDesc('lps.scan_time')
            ->limit(6)
            ->select([
                'lps.license_plate',
                'lps.scan_time',
                'lps.source',
                DB::raw("CASE WHEN sv.id IS NULL THEN false ELSE true END as is_suspicious"),
            ])
            ->get();

        // ค่าจอดค้างชำระ
        $unpaidPayments = DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->whereIn('pl.parking_lot_id', $scopeLotIds)
            ->where('p.payment_status', Payment::STATUS_UNPAID)
            ->whereBetween('p.created_at', [$from, $to])
            ->when($q !== '', fn($qq) => $qq->where('pl.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('p.created_at')
            ->limit(8)
            ->select(['p.id as payment_id', 'pl.license_plate', 'p.total_hours', 'p.total_amount'])
            ->get();

        // Reservations
        $reservations = DB::table('reservations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->whereBetween('r.created_at', [$from, $to])
            ->when($q !== '', fn($qq) => $qq->where('r.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('r.created_at')
            ->limit(8)
            ->select(['r.license_plate', 'u.name as user_name', 'lot.name as lot_name', 'r.reserve_start', 'r.status'])
            ->get();

        // Recent history
        $recentHistory = DB::table('parking_logs as pl')
            ->leftJoin('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->whereIn('pl.parking_lot_id', $scopeLotIds)
            ->whereBetween('pl.check_in_time', [$from, $to])
            ->when($q !== '', fn($qq) => $qq->where('pl.license_plate', 'ilike', "%{$q}%"))
            ->orderByDesc('pl.check_in_time')
            ->limit(10)
            ->select(['pl.license_plate', 'u.name as user_name', 'lot.name as lot_name', 'pl.check_in_time', 'pl.check_out_time'])
            ->get();

        // Slots preview (optional)
        $slotsPreview = DB::table('parking_slots as s')
            ->join('parking_lots as lot', 'lot.id', '=', 's.parking_lot_id')
            ->whereIn('s.parking_lot_id', $scopeLotIds)
            ->orderByDesc('s.updated_at')
            ->limit(8)
            ->select(['s.slot_number', 's.status', 'lot.name as lot_name'])
            ->get();

        $pendingApplications = OwnerApplication::where('status', 'pending')->count();
        $pendingResignations = OwnerResignation::pending()->count();

        // ── Chart data ─────────────────────────────────────────────────────
        $statusKeys   = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'];
        $statusColors = [
            'pending'    => 'rgba(250,204,21,0.85)',
            'confirmed'  => 'rgba(96,165,250,0.85)',
            'checked_in' => 'rgba(52,211,153,0.85)',
            'completed'  => 'rgba(34,197,94,0.85)',
            'cancelled'  => 'rgba(239,68,68,0.85)',
            'expired'    => 'rgba(107,114,128,0.85)',
        ];
        $rawStatusCounts = DB::table('reservations')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->selectRaw('status, COUNT(*) as count')
            ->whereIn('status', $statusKeys)
            ->groupBy('status')
            ->pluck('count', 'status');

        $chartReservationStatus = [
            'labels'   => ['Pending', 'Confirmed', 'Checked In', 'Completed', 'Cancelled', 'Expired'],
            'datasets' => [[
                'data'            => array_map(fn ($s) => (int) ($rawStatusCounts[$s] ?? 0), $statusKeys),
                'backgroundColor' => array_values($statusColors),
                'borderColor'     => '#111827',
                'borderWidth'     => 2,
                'hoverOffset'     => 6,
            ]],
        ];

        $chartSlotOccupancy = [
            'labels'   => ['ว่าง', 'จอง', 'ใช้งาน'],
            'datasets' => [[
                'label'           => 'ช่องจอด',
                'data'            => [
                    $stats['slots_available'],
                    $stats['slots_reserved'],
                    $stats['slots_occupied'],
                ],
                'backgroundColor' => [
                    'rgba(34,197,94,0.75)',
                    'rgba(250,204,21,0.75)',
                    'rgba(239,68,68,0.75)',
                ],
                'borderRadius'    => 6,
                'borderWidth'     => 0,
            ]],
        ];

        $topLotsRaw = DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->selectRaw('lot.name, COUNT(*) as reservation_count')
            ->groupBy('lot.id', 'lot.name')
            ->orderByDesc('reservation_count')
            ->limit(5)
            ->get();

        $chartTopLots = [
            'labels'   => $topLotsRaw->pluck('name')->toArray(),
            'datasets' => [[
                'label'           => 'การจอง',
                'data'            => $topLotsRaw->pluck('reservation_count')->map(fn ($v) => (int) $v)->toArray(),
                'backgroundColor' => 'rgba(96,165,250,0.75)',
                'borderRadius'    => 6,
                'borderWidth'     => 0,
            ]],
        ];

        return view('admin.dashboard', compact(
            'stats',
            'range',
            'activeNow',
            'lotsOverview',
            'latestScans',
            'unpaidPayments',
            'reservations',
            'recentHistory',
            'slotsPreview',
            'pendingApplications',
            'pendingResignations',
            'chartReservationStatus',
            'chartSlotOccupancy',
            'chartTopLots'
        ));
    }
}
