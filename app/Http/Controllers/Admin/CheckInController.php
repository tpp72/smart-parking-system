<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Vehicle;
use App\Services\CheckInService;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function __construct(private CheckInService $checkInService) {}

    public function create()
    {
        $vehicles = Vehicle::with('user:id,name')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'color', 'user_id']);

        $lots = ParkingLot::orderBy('name')->get(['id', 'name']);

        return view('admin.check-in.create', compact('vehicles', 'lots'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id'     => ['required', 'exists:vehicles,id'],
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
        ]);

        $result = $this->checkInService->checkIn($data['vehicle_id'], $data['parking_lot_id']);

        if (!$result['success']) {
            $field = str_contains($result['error'], 'ช่องจอด') ? 'parking_lot_id' : 'vehicle_id';
            return back()->withErrors([$field => $result['error']])->withInput();
        }

        $vehicle     = Vehicle::find($data['vehicle_id']);
        $slot        = $result['slot'];
        $reservation = $result['reservation'];

        // Notify vehicle owner on successful check-in (when tied to a reservation)
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

        admin_audit('parking_log.check_in', $vehicle, [
            'parking_lot_id'  => $slot->parking_lot_id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => $reservation?->id,
        ]);

        return redirect()->route('admin.check-in.create')->with('success', $successMsg);
    }
}
