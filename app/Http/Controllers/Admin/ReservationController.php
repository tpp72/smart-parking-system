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
 * (การยืนยันเกิดจาก Mark as Paid เงินมัดจำในหน้า Payments)
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
                'parkingLog:id,reservation_id,check_in_time',
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

        return view('admin.reservations.index', compact(
            'reservations',
            'lots',
            'q',
            'status',
            'lotId',
            'from',
            'to',
            'checkableIds'
        ));
    }

    /** ยกเลิกการจองก่อน Check-in (จัดการเหตุผิดปกติ) — ไม่คืนเงินมัดจำที่ชำระแล้ว */
    public function cancel(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $result = $this->reservations->cancel($reservation, Auth::user(), 'Admin ยกเลิกการจอง');

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        notify_user(
            $reservation->user_id,
            'การจองถูกยกเลิก',
            "การจอง #{$reservation->id} ถูกยกเลิกโดยผู้ดูแลระบบ"
        );

        admin_audit('reservation.cancel', $reservation, [
            'deposit_forfeited' => $result['deposit_forfeited'],
        ]);

        return back()->with('success', "ยกเลิกการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    /** Check-In รถของการจองนี้โดยตรง (แทนหน้า Manual Check-In แยก) */
    public function checkIn(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        // Manual Check-in (Fallback) — เฉพาะการจองที่ confirmed · รถที่มาก่อนเวลาจองเข้าได้หลังเจ้าหน้าที่ตรวจสอบ
        $result = $this->checkInService->checkInReservation($reservation, Auth::user(), allowEarly: true);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        $slot = $result['slot'];

        notify_user(
            $reservation->user_id,
            'เช็คอินสำเร็จ',
            "รถทะเบียน {$reservation->license_plate} เข้าจอดที่ช่อง {$slot->slot_number} แล้ว (การจอง #{$reservation->id})"
        );

        admin_audit('parking_log.check_in', $reservation, [
            'parking_lot_id'  => $slot->parking_lot_id,
            'parking_slot_id' => $slot->id,
        ]);

        return back()->with('success',
            "Check-In สำเร็จ! ทะเบียน {$reservation->license_plate} → ช่อง {$slot->slot_number}"
        );
    }

    /** Check-Out รถของการจองนี้โดยตรง (แทนหน้า Manual Check-Out แยก) */
    public function checkOut(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        if ($reservation->status !== 'checked_in') {
            return back()->withErrors(['error' => "ไม่สามารถเช็คเอาท์ได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $log = $reservation->parkingLog;
        abort_if(!$log, 404, 'ไม่พบ Parking Log ของการจองนี้');

        $allowedLotIds = ParkingLot::unowned()->pluck('id')->all();
        $result = $this->checkOutService->checkOut($log, $allowedLotIds);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        admin_audit('parking_log.check_out', $log, [
            'total_hours'          => $result['totalHours'],
            'parking_fee'          => $result['parkingFee'],
            'reservation_discount' => $result['deposit'],
            'total_amount'         => $result['totalAmount'],
        ]);

        return back()->with('success', sprintf(
            'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
            $reservation->license_plate,
            $result['totalHours'],
            $result['parkingFee'],
            $result['totalAmount'],
        ));
    }
}
