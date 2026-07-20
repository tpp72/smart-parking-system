<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkInService) {}

    public function create()
    {
        $lots   = ParkingLot::ownedBy(Auth::id())->orderBy('name')->get(['id', 'name']);
        $lotIds = $lots->pluck('id');

        $reservations = Reservation::checkable()
            ->whereIn('parking_lot_id', $lotIds)
            ->with(['user:id,name', 'parkingLot:id,name', 'parkingSlot:id,slot_number'])
            ->orderBy('reserve_start')
            ->get(['id', 'user_id', 'license_plate', 'brand', 'color', 'parking_lot_id', 'parking_slot_id', 'reserve_start']);

        return view('owner.check-in.create', compact('reservations', 'lots'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'reservation_id' => ['required', 'exists:reservations,id'],
        ], [
            'reservation_id.required' => 'กรุณาเลือกการจอง',
            'reservation_id.exists'   => 'ไม่พบการจองที่เลือก',
        ]);

        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        $reservation = Reservation::findOrFail($data['reservation_id']);

        if (!in_array($reservation->parking_lot_id, $ownedLotIds, true)) {
            return back()->withErrors(['reservation_id' => 'ไม่มีสิทธิ์ทำ Check-In ให้การจองนี้'])->withInput();
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
            $field = str_contains($result['error'], 'ช่องจอด') ? 'parking_lot_id' : 'reservation_id';
            return back()->withErrors([$field => $result['error']])->withInput();
        }

        $slot = $result['slot'];

        notify_user(
            $reservation->user_id,
            'เช็คอินสำเร็จ',
            "รถทะเบียน {$reservation->license_plate} เข้าจอดที่ช่อง {$slot->slot_number} แล้ว (การจอง #{$reservation->id})"
        );

        return redirect()->route('owner.check-in.create')->with('success',
            "Check-In สำเร็จ! ทะเบียน {$reservation->license_plate} → ช่อง {$slot->slot_number} (การจอง #{$reservation->id})"
        );
    }
}
