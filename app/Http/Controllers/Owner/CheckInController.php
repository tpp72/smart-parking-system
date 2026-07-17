<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkInService) {}

    public function create()
    {
        $vehicles = Vehicle::with('user:id,name')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'color', 'user_id']);

        $lots = ParkingLot::ownedBy(Auth::id())->orderBy('name')->get(['id', 'name']);

        return view('owner.check-in.create', compact('vehicles', 'lots'));
    }

    public function store(Request $request)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();

        $data = $request->validate([
            'vehicle_id'     => ['required', 'exists:vehicles,id'],
            'parking_lot_id' => ['required', 'exists:parking_lots,id', function ($attr, $value, $fail) use ($ownedLotIds) {
                if (!in_array((int) $value, $ownedLotIds, true)) {
                    $fail('ไม่มีสิทธิ์ทำ Check-In ให้ลานจอดนี้');
                }
            }],
        ]);

        $result = $this->checkInService->checkIn($data['vehicle_id'], $data['parking_lot_id'], $ownedLotIds);

        if (!$result['success']) {
            $field = str_contains($result['error'], 'ช่องจอด') ? 'parking_lot_id' : 'vehicle_id';
            return back()->withErrors([$field => $result['error']])->withInput();
        }

        $vehicle     = Vehicle::find($data['vehicle_id']);
        $slot        = $result['slot'];
        $reservation = $result['reservation'];

        if ($reservation) {
            notify_user(
                $reservation->user_id,
                'เช็คอินสำเร็จ',
                "รถทะเบียน {$vehicle->license_plate} เข้าจอดที่ช่อง {$slot->slot_number} แล้ว (การจอง #{$reservation->id})"
            );
        }

        $successMsg = "Check-In สำเร็จ! ทะเบียน {$vehicle->license_plate} → ช่อง {$slot->slot_number}";
        if ($reservation) {
            $successMsg .= " (การจอง #{$reservation->id})";
        }

        return redirect()->route('owner.check-in.create')->with('success', $successMsg);
    }
}
