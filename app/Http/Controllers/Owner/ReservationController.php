<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Owner ดู Reservation ของลานตัวเอง — การยืนยันการจองเกิดจาก Mark as Paid เงินมัดจำในหน้า Payments
 */
class ReservationController extends Controller
{
    public function __construct(
        private CheckInService  $checkInService,
        private CheckOutService $checkOutService,
    ) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $lotId = $request->query('lot_id');
        $from = $request->query('from');
        $to = $request->query('to');

        $ownedLots = ParkingLot::where('owner_id', Auth::id())->get(['id', 'name']);
        $ownedLotIds = $ownedLots->pluck('id');

        $reservations = Reservation::with([
            'user:id,name,email',
            'parkingLot:id,name,hourly_rate',
            'parkingSlot:id,parking_lot_id,slot_number',
            'parkingLog:id,reservation_id,check_in_time,hourly_rate',
        ])
            ->whereIn('parking_lot_id', $ownedLotIds)
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('license_plate', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"));
            }))
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_start', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        $statuses = Reservation::STATUSES;

        $checkableIds = Reservation::manuallyCheckable()
            ->whereIn('id', $reservations->pluck('id'))
            ->pluck('id')
            ->all();

        return view('owner.reservations.index', compact(
            'reservations', 'ownedLots', 'q', 'status', 'lotId', 'from', 'to', 'statuses', 'checkableIds'
        ));
    }

    /** Check-In รถของการจองนี้โดยตรง (แทนหน้า Manual Check-In แยก) */
    public function checkIn(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        abort_unless(in_array($reservation->parking_lot_id, $ownedLotIds, true), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');

        // Manual Check-in (Fallback) — เฉพาะการจองที่ confirmed · รถที่มาก่อนเวลาจองเข้าได้หลังเจ้าหน้าที่ตรวจสอบ
        $result = $this->checkInService->checkInReservation($reservation, Auth::user(), allowEarly: true);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        $slot = $result['slot'];

        return back()->with('success',
            "Check-In สำเร็จ! ทะเบียน {$reservation->license_plate} → ช่อง {$slot->slot_number}"
        );
    }

    /** Check-Out รถของการจองนี้โดยตรง (แทนหน้า Manual Check-Out แยก) */
    public function checkOut(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        abort_unless(in_array($reservation->parking_lot_id, $ownedLotIds, true), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');

        // Manual Check-out (Fallback) — เข้าสู่ Checkout Flow เดียวกับ Auto Check-out
        $result = $this->checkOutService->checkOut($reservation, Auth::user());

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', "Check-Out สำเร็จ! ทะเบียน {$reservation->license_plate} | " . CheckOutService::summary($result['payment']));
    }
}
