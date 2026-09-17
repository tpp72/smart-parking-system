<?php

namespace App\Http\Controllers;

use App\Models\LicensePlateScan;
use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Queries\RevenueQuery;
use App\Services\CheckOutService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function user(CheckOutService $checkOut)
    {
        $user = Auth::user();

        if ($user?->force_password_reset) {
            return redirect()->route('profile.edit')
                ->with('warning', 'กรุณาเปลี่ยนรหัสผ่านก่อนเข้าใช้งานหน้าหลัก');
        }

        // บัตรทุกใบที่ยังไม่จบ: กำลังจอดก่อน แล้วเรียงตามเวลาเริ่มจอง (ใกล้หมดเวลาเช็คอินก่อน)
        $tickets = Reservation::with(['parkingLot:id,name,hourly_rate', 'parkingSlot:id,slot_number', 'depositPayment', 'parkingLog'])
            ->where('user_id', $user->id)
            ->active()
            ->get()
            ->sortBy(fn (Reservation $r) => [$r->status === 'checked_in' ? 0 : 1, $r->reserve_start->getTimestamp()])
            ->values();

        // รถที่จอดอยู่: ประมาณค่าจอด ณ ตอนนี้ด้วยสูตรเดียวกับตอน Check-out (ยอดจริงคิดตอนออก)
        $estimates = $tickets
            ->filter(fn (Reservation $r) => $r->status === 'checked_in' && $r->parkingLog && ! $r->parkingLog->check_out_time)
            ->mapWithKeys(fn (Reservation $r) => [$r->id => $checkOut->calculate($r, $r->parkingLog, now())]);

        $recentHistory = DB::table('parking_logs as pl')
            ->join('reservations as r', 'r.id', '=', 'pl.reservation_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('payments as p', 'p.parking_log_id', '=', 'pl.id')
            ->where('r.user_id', $user->id)
            ->whereNotNull('pl.check_out_time')
            ->orderByDesc('pl.check_out_time')
            ->limit(3)
            ->select(['pl.id as log_id', 'pl.license_plate', 'pl.plate_province', 'lot.name as lot_name', 'pl.check_in_time', 'pl.check_out_time', 'p.total_amount', 'p.payment_status'])
            ->get();

        // แนะนำลานที่จองได้ทันที (เงื่อนไขเดียวกับหน้าจอง) — กดแล้วไปหน้าจองพร้อมเลือกลานนั้นไว้
        $lotsAvailable = ParkingLot::reservable()
            ->withAvailableSlot()
            ->withCount(['slots as available' => fn ($query) => $query->where('status', 'available')])
            ->orderByDesc('available')
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'hourly_rate']);

        return view('dashboard-user', compact('tickets', 'estimates', 'recentHistory', 'lotsAvailable'));
    }

    /**
     * Admin Dashboard = ภาพรวมทั้งระบบ ทุกลาน (project-plan.md §17.1, §5.1.1)
     * ตัวกรอง ?lot_id= (ว่าง = ทุกลาน) และ ?range= today | 7d | month ใช้กับ KPI กราฟ และรายการ — ทุกตัวเลขบอกขอบเขตของตัวเอง
     * งานที่รอผู้ดูแลระบบและจำนวนบัญชีดำไม่ขึ้นกับตัวกรอง · การจัดการยังจำกัดเฉพาะลานของ Admin ในหน้าจัดการ
     */
    public function admin()
    {
        $range = in_array(request('range'), ['today', '7d', 'month'], true) ? request('range') : 'today';

        $lots = ParkingLot::with('owner:id,name')->orderBy('name')->get(['id', 'name', 'owner_id', 'hourly_rate']);
        $lot = $lots->firstWhere('id', (int) request('lot_id')); // null = ทุกลาน
        $scopeLotIds = $lot ? collect([$lot->id]) : $lots->pluck('id');

        [$from, $to] = match ($range) {
            '7d'    => [now()->subDays(6)->startOfDay(), now()->endOfDay()], // 7 วันล่าสุด รวมวันนี้
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };

        // ── ตอนนี้ (ไม่ขึ้นกับช่วงเวลา) ────────────────────────────────────
        $slotStats = DB::table('parking_slots')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as occupied
            ")->first();

        // รอยืนยันรับเงิน ณ ตอนนี้ แยกมัดจำ / ค่าจอด (ไม่ผูกกับช่วงเวลา — นิยามเดียวกับหน้ารายได้ของเจ้าของลาน)
        $unpaid = function (string $type, $lotIds): array {
            $query = fn () => Payment::where('type', $type)
                ->where('payment_status', Payment::STATUS_UNPAID)
                ->whereHas('reservation', fn ($q) => $q->whereIn('parking_lot_id', $lotIds));

            return ['count' => $query()->count(), 'amount' => (float) $query()->sum('total_amount')];
        };

        // ── ในช่วงเวลาที่เลือก ────────────────────────────────────────────
        $paid = fn () => RevenueQuery::paid($scopeLotIds)->whereBetween('p.paid_at', [$from, $to]);

        $scanStats = DB::table('license_plate_scans')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->whereBetween('scan_time', [$from, $to])
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN result = 'passed' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN result IN ('low_accuracy', 'unreadable') THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN is_suspicious THEN 1 ELSE 0 END) as suspicious
            ")->first();

        $logsBetween = fn (string $column) => DB::table('parking_logs')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->whereBetween($column, [$from, $to]);

        $stats = [
            'lots_total'       => $lots->count(),
            'admin_lots_total' => $lots->whereNull('owner_id')->count(),
            'slots_total'      => (int) ($slotStats->total ?? 0),
            'slots_available'  => (int) ($slotStats->available ?? 0),
            'slots_reserved'   => (int) ($slotStats->reserved ?? 0),
            'slots_occupied'   => (int) ($slotStats->occupied ?? 0),
            'active_now'       => DB::table('parking_logs')->whereIn('parking_lot_id', $scopeLotIds)->whereNull('check_out_time')->count(),
            'unpaid_deposits'  => $unpaid(Payment::TYPE_DEPOSIT, $scopeLotIds),
            'unpaid_checkouts' => $unpaid(Payment::TYPE_CHECKOUT, $scopeLotIds),
            'revenue_paid'     => (float) $paid()->sum('p.total_amount'),
            'revenue_deposit'  => (float) $paid()->where('p.type', Payment::TYPE_DEPOSIT)->sum('p.total_amount'),
            'revenue_parking'  => (float) $paid()->where('p.type', Payment::TYPE_CHECKOUT)->sum('p.total_amount'),
            'check_ins'        => $logsBetween('check_in_time')->count(),
            'check_outs'       => $logsBetween('check_out_time')->count(),
            'walk_ins'         => DB::table('parking_logs as pl')
                ->join('reservations as r', 'r.id', '=', 'pl.reservation_id')
                ->whereIn('pl.parking_lot_id', $scopeLotIds)
                ->where('r.is_walk_in', true)
                ->whereBetween('pl.check_in_time', [$from, $to])
                ->count(),
            'scans_total'      => (int) ($scanStats->total ?? 0),
            'scans_passed'     => (int) ($scanStats->passed ?? 0),
            'scans_failed'     => (int) ($scanStats->failed ?? 0),
            'scans_suspicious' => (int) ($scanStats->suspicious ?? 0),
            // บัญชีดำเป็นรายการกลางของทั้งระบบ ไม่ผูกกับลาน
            'blacklist_active' => SuspiciousVehicle::active()->count(),
        ];

        // ── งานที่รอผู้ดูแลระบบ (ไม่ขึ้นกับตัวกรอง) ─────────────────────────
        $adminLotIds = $lots->whereNull('owner_id')->pluck('id');
        $adminDeposits = $unpaid(Payment::TYPE_DEPOSIT, $adminLotIds);
        $adminCheckouts = $unpaid(Payment::TYPE_CHECKOUT, $adminLotIds);

        $tasks = [
            'applications' => OwnerApplication::where('status', 'pending')->count(),
            'resignations' => OwnerResignation::pending()->count(),
            'payments'     => [
                'count'  => $adminDeposits['count'] + $adminCheckouts['count'],
                'amount' => $adminDeposits['amount'] + $adminCheckouts['amount'],
            ],
        ];

        // ── การจองใหม่ในช่วงเวลา (รวม Walk-in) แยกตามสถานะปัจจุบัน ─────────────
        $statusCounts = DB::table('reservations')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $reservationStatus = collect(Reservation::STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => (int) ($statusCounts[$status] ?? 0)]);

        // ── ลานที่มีการจองใหม่มากที่สุดในช่วงเวลา (เทียบทุกลานเสมอ) ──────────────
        $topLots = DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereBetween('r.created_at', [$from, $to])
            ->selectRaw('lot.id, lot.name, COUNT(*) as total')
            ->groupBy('lot.id', 'lot.name')
            ->orderByDesc('total')
            ->orderBy('lot.name')
            ->limit(5)
            ->get();

        // ── ภาพรวมทุกลาน: สถานะช่อง ณ ตอนนี้ + รถที่จอดอยู่ + รับเงินในช่วงเวลา (แสดงทุกลาน ไม่จำกัดจำนวน) ──
        $slotsByLot = DB::table('parking_slots')
            ->selectRaw("
                parking_lot_id,
                SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as occupied
            ")
            ->groupBy('parking_lot_id')
            ->get()
            ->keyBy('parking_lot_id');

        $parkedByLot = DB::table('parking_logs')
            ->whereNull('check_out_time')
            ->selectRaw('parking_lot_id, COUNT(*) as total')
            ->groupBy('parking_lot_id')
            ->pluck('total', 'parking_lot_id');

        $revenueByLot = RevenueQuery::paid($lots->pluck('id'))
            ->whereBetween('p.paid_at', [$from, $to])
            ->selectRaw('r.parking_lot_id, SUM(p.total_amount) as revenue')
            ->groupBy('r.parking_lot_id')
            ->pluck('revenue', 'parking_lot_id');

        $lotsOverview = $lots->map(fn (ParkingLot $l) => (object) [
            'id'          => $l->id,
            'name'        => $l->name,
            'owner_name'  => $l->owner?->name,
            'hourly_rate' => (float) $l->hourly_rate,
            'available'   => (int) ($slotsByLot[$l->id]->available ?? 0),
            'reserved'    => (int) ($slotsByLot[$l->id]->reserved ?? 0),
            'occupied'    => (int) ($slotsByLot[$l->id]->occupied ?? 0),
            'parked'      => (int) ($parkedByLot[$l->id] ?? 0),
            'revenue'     => (float) ($revenueByLot[$l->id] ?? 0),
        ]);

        // ── รถที่จอดอยู่ตอนนี้ + สแกนล่าสุด ───────────────────────────────────
        $parked = ParkingLog::with([
            'parkingLot:id,name',
            'parkingSlot:id,slot_number',
            'reservation:id,user_id,is_walk_in',
            'reservation.user:id,name',
        ])
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->whereNull('check_out_time')
            ->orderByDesc('check_in_time')
            ->limit(8)
            ->get();

        $latestScans = LicensePlateScan::with('parkingLot:id,name')
            ->whereIn('parking_lot_id', $scopeLotIds)
            ->orderByDesc('scan_time')
            ->limit(6)
            ->get(['id', 'parking_lot_id', 'license_plate', 'plate_province', 'confidence', 'result', 'is_suspicious', 'scan_time']);

        return view('admin.dashboard', compact(
            'range', 'lots', 'lot', 'stats', 'tasks', 'reservationStatus', 'topLots', 'lotsOverview', 'parked', 'latestScans'
        ));
    }
}
