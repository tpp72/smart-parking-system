<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Queries\RevenueQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ภาพรวมของเจ้าของลาน — งานที่ต้องทำก่อน (มัดจำรอยืนยัน / ค่าจอดค้าง / การจองที่ถึงเวลาเข้าลาน)
 * → สถานะรายลาน ณ ตอนนี้ → รถที่จอดอยู่ + การจองที่กำลังจะมาถึง (กราฟรายได้อยู่หน้ารายได้)
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $ownerId = $user->id;

        // บัญชี Owner ที่ยังไม่ได้รับอนุมัติ (ข้อมูลเก่า) — แสดงสถานะคำขอแทนภาพรวม
        if ($user->owner_status !== 'approved') {
            return view('owner.dashboard', [
                'ownerStatus' => $user->owner_status,
                'application' => OwnerApplication::where('user_id', $ownerId)->latest()->first(),
                'resignation' => null,
            ]);
        }

        $lotIds = ParkingLot::ownedBy($ownerId)->pluck('id');

        // ── งานที่ต้องทำ ─────────────────────────────────────────────────
        $unpaid = fn (string $type) => Payment::where('type', $type)
            ->where('payment_status', Payment::STATUS_UNPAID)
            ->whereHas('reservation', fn ($q) => $q->whereIn('parking_lot_id', $lotIds));

        $tasks = [
            'deposits' => ['count' => $unpaid(Payment::TYPE_DEPOSIT)->count(), 'amount' => (float) $unpaid(Payment::TYPE_DEPOSIT)->sum('total_amount')],
            'checkouts' => ['count' => $unpaid(Payment::TYPE_CHECKOUT)->count(), 'amount' => (float) $unpaid(Payment::TYPE_CHECKOUT)->sum('total_amount')],
            // ยืนยันแล้วและถึงเวลาเข้าลาน (ยังไม่เลย 60 นาที) — ถ้ากล้องอ่านไม่ได้ ต้อง Manual Check-in
            'arriving' => Reservation::checkable()->whereIn('parking_lot_id', $lotIds)->count(),
        ];

        // ── สถานะรายลาน ณ ตอนนี้ + รายได้วันนี้ ────────────────────────────
        $revenueTodayByLot = RevenueQuery::paid($lotIds)
            ->whereDate('p.paid_at', now()->toDateString())
            ->groupBy('r.parking_lot_id')
            ->selectRaw('r.parking_lot_id, SUM(p.total_amount) as revenue')
            ->pluck('revenue', 'parking_lot_id');

        $lots = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->where('lot.owner_id', $ownerId)
            ->groupBy('lot.id', 'lot.name', 'lot.hourly_rate', 'lot.reservations_enabled')
            ->orderBy('lot.name')
            ->selectRaw("
                lot.id, lot.name, lot.hourly_rate, lot.reservations_enabled,
                COUNT(s.id) as total,
                SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN s.status='reserved' THEN 1 ELSE 0 END) as reserved,
                SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) as occupied
            ")
            ->get()
            ->each(fn ($lot) => $lot->revenue_today = (float) ($revenueTodayByLot[$lot->id] ?? 0));

        $totals = [
            'lots' => $lots->count(),
            'slots' => (int) $lots->sum('total'),
            'available' => (int) $lots->sum('available'),
            'reserved' => (int) $lots->sum('reserved'),
            'occupied' => (int) $lots->sum('occupied'),
            'revenue_today' => (float) $lots->sum('revenue_today'),
        ];

        // ── รถที่จอดอยู่ + การจองที่กำลังจะมาถึง (24 ชม.) ──────────────────
        $parked = Reservation::with(['parkingLot:id,name', 'parkingSlot:id,slot_number', 'parkingLog:id,reservation_id,check_in_time'])
            ->whereIn('parking_lot_id', $lotIds)
            ->where('status', 'checked_in')
            ->get()
            ->sortByDesc(fn (Reservation $r) => $r->parkingLog?->check_in_time)
            ->take(8)
            ->values();

        $upcoming = Reservation::with(['parkingLot:id,name', 'parkingSlot:id,slot_number', 'user:id,name', 'depositPayment'])
            ->whereIn('parking_lot_id', $lotIds)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '>=', now()->subMinutes(Reservation::gracePeriodMinutes()))
            ->where('reserve_start', '<=', now()->addDay())
            ->orderBy('reserve_start')
            ->limit(8)
            ->get();

        // คำร้องลาออกล่าสุด (รอพิจารณา / ไม่อนุมัติ)
        $resignation = OwnerResignation::where('user_id', $ownerId)->latest('id')->first();

        return view('owner.dashboard', compact('tasks', 'lots', 'totals', 'parked', 'upcoming', 'resignation')
            + ['ownerStatus' => 'approved', 'application' => null]);
    }
}
