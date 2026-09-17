<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin จัดการ Reservation ของลาน Admin (owner_id = NULL):
 * ดู / ค้นหา / ยกเลิก — ไม่มีการสร้าง แก้ไข ลบ หรือยืนยันด้วยมือ
 * (การยืนยันเกิดจาก Mark as Paid เงินมัดจำในหน้า Payments · Audit Log บันทึกใน Service)
 */
class ReservationController extends Controller
{
    public function __construct(
        private CheckInService     $checkInService,
        private CheckOutService    $checkOutService,
        private ReservationService $reservations,
    ) {}

    /** Admin จัดการการจองได้เฉพาะของลานที่ยังไม่มีเจ้าของ */
    private function assertReservationLotUnowned(Reservation $reservation): void
    {
        abort_unless(
            ParkingLot::where('id', $reservation->parking_lot_id)->whereNull('owner_id')->exists(),
            403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้'
        );
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $lotId = $request->query('lot_id');

        $from = $request->query('from'); // YYYY-MM-DD
        $to   = $request->query('to');   // YYYY-MM-DD

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);
        $lotIds = $lots->pluck('id');

        $reservations = Reservation::query()
            ->with([
                'user:id,name,email',
                'parkingLot:id,name,hourly_rate',
                'parkingSlot:id,parking_lot_id,slot_number',
                'parkingLog:id,reservation_id,check_in_time,check_out_time,hourly_rate',
                'depositPayment',
            ])
            ->whereIn('parking_lot_id', $lotIds)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('license_plate', 'like', "%{$q}%")
                        ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('user', fn($x) => $x->where('email', 'like', "%{$q}%"));
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_start', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        $checkableIds = Reservation::manuallyCheckable()
            ->whereIn('id', $reservations->pluck('id'))
            ->pluck('id')
            ->all();

        // รถที่จอดอยู่: ยอดประมาณถ้า Check-out ตอนนี้ (สูตรเดียวกับ Check-out จริง — หักมัดจำและส่วนลดแล้ว)
        $estimates = $reservations->getCollection()
            ->filter(fn (Reservation $r) => $r->status === 'checked_in' && $r->parkingLog && ! $r->parkingLog->check_out_time)
            ->mapWithKeys(fn (Reservation $r) => [$r->id => $this->checkOutService->calculate($r, $r->parkingLog, now())]);

        return view('staff.reservations', compact(
            'reservations', 'lots', 'q', 'status', 'lotId', 'from', 'to', 'checkableIds', 'estimates'
        ) + ['statuses' => Reservation::STATUSES, 'scope' => 'admin']);
    }

    /** ยกเลิกการจองก่อน Check-in (จัดการเหตุผิดปกติ) — ไม่คืนเงินมัดจำที่ชำระแล้ว */
    public function cancel(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $result = $this->reservations->cancel($reservation, Auth::user(), 'ผู้ดูแลระบบยกเลิกการจอง');

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', "ยกเลิกการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    /** Manual Check-in (Fallback) — เฉพาะการจองที่ confirmed · รถที่มาก่อนเวลาจองเข้าได้หลังเจ้าหน้าที่ตรวจสอบ */
    public function checkIn(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $result = $this->checkInService->checkInReservation($reservation, Auth::user(), allowEarly: true);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        $slot = $result['slot'];

        return back()->with('success',
            "Check-in สำเร็จ — ทะเบียน {$reservation->license_plate} เข้าช่อง {$slot->slot_number}"
        );
    }

    /** Manual Check-out (Fallback) — เข้าสู่ Checkout Flow เดียวกับ Auto Check-out */
    public function checkOut(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $result = $this->checkOutService->checkOut($reservation, Auth::user());

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', "Check-out สำเร็จ — ทะเบียน {$reservation->license_plate} · " . CheckOutService::summary($result['payment']));
    }
}
