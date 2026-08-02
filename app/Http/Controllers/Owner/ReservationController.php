<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'vehicle:id,user_id,license_plate',
            'parkingLot:id,name,hourly_rate',
            'parkingSlot:id,parking_lot_id,slot_number',
            'parkingLog:id,reservation_id,check_in_time',
        ])
            ->whereIn('parking_lot_id', $ownedLotIds)
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('license_plate', 'like', "%{$q}%")
                    ->orWhereHas('vehicle', fn($x) => $x->where('license_plate', 'like', "%{$q}%"))
                    ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"));
            }))
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_start', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        $statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'];

        $checkableIds = Reservation::checkable()
            ->whereIn('id', $reservations->pluck('id'))
            ->pluck('id')
            ->all();

        return view('owner.reservations.index', compact(
            'reservations', 'ownedLots', 'q', 'status', 'lotId', 'from', 'to', 'statuses', 'checkableIds'
        ));
    }

    public function confirm(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::where('owner_id', Auth::id())->pluck('id');
        abort_unless($ownedLotIds->contains($reservation->parking_lot_id), 403);

        if ($reservation->status !== 'pending') {
            return back()->withErrors(['error' => "ไม่สามารถยืนยันได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $slotError = null;

        DB::transaction(function () use ($reservation, &$slotError) {
            if ($reservation->parking_slot_id) {
                $slot = ParkingSlot::where('id', $reservation->parking_slot_id)
                    ->lockForUpdate()
                    ->first();

                if (!$slot || $slot->status !== 'available') {
                    $slotError = "ช่องจอดที่จองไว้ไม่พร้อมใช้งาน (สถานะ: {$slot?->status})";
                    return;
                }

                $slot->update(['status' => 'reserved']);
            }

            $reservation->update(['status' => 'confirmed']);

            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'old_status'     => 'pending',
                'new_status'     => 'confirmed',
                'changed_by'     => Auth::id(),
                'note'           => 'เจ้าของลานจอดยืนยันการจอง',
            ]);
        });

        if ($slotError) {
            return back()->withErrors(['error' => $slotError]);
        }

        notify_user(
            $reservation->user_id,
            'การจองได้รับการยืนยัน',
            "การจอง #{$reservation->id} ของคุณได้รับการยืนยันแล้ว กรุณาเช็คอินภายในเวลาที่กำหนด"
        );

        return back()->with('success', "ยืนยันการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    /** Check-In รถของการจองนี้โดยตรง (แทนหน้า Manual Check-In แยก) */
    public function checkIn(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        abort_unless(in_array($reservation->parking_lot_id, $ownedLotIds, true), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');

        if ($reservation->status !== 'confirmed') {
            return back()->withErrors(['error' => "ไม่สามารถเช็คอินได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        if (!Reservation::checkable()->where('id', $reservation->id)->exists()) {
            return back()->withErrors(['error' => 'อยู่นอกช่วงเวลาเช็คอิน (เร็วเกินไปหรือเกินเวลากำหนด)']);
        }

        $result = $this->checkInService->checkIn(
            $reservation->license_plate,
            $reservation->brand,
            $reservation->color,
            $reservation->parking_lot_id,
            $ownedLotIds,
            $reservation->vehicle_id
        );

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        $slot = $result['slot'];

        notify_user(
            $reservation->user_id,
            'เช็คอินสำเร็จ',
            "รถทะเบียน {$reservation->license_plate} เข้าจอดที่ช่อง {$slot->slot_number} แล้ว (การจอง #{$reservation->id})"
        );

        return back()->with('success',
            "Check-In สำเร็จ! ทะเบียน {$reservation->license_plate} → ช่อง {$slot->slot_number}"
        );
    }

    /** Check-Out รถของการจองนี้โดยตรง (แทนหน้า Manual Check-Out แยก) */
    public function checkOut(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        abort_unless(in_array($reservation->parking_lot_id, $ownedLotIds, true), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');

        if ($reservation->status !== 'checked_in') {
            return back()->withErrors(['error' => "ไม่สามารถเช็คเอาท์ได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $log = $reservation->parkingLog;
        abort_if(!$log, 404, 'ไม่พบ Parking Log ของการจองนี้');

        $result = $this->checkOutService->checkOut($log, $ownedLotIds);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', sprintf(
            'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
            $reservation->license_plate,
            $result['totalHours'],
            $result['parkingFee'],
            $result['totalAmount'],
        ));
    }
}
