<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
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

        // ตรวจสอบว่ารถคันนี้ยังอยู่ในระบบ (ยังไม่ check-out)
        $alreadyIn = ParkingLog::where('vehicle_id', $data['vehicle_id'])
            ->whereNull('check_out_time')
            ->exists();

        if ($alreadyIn) {
            return back()
                ->withErrors(['vehicle_id' => 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out'])
                ->withInput();
        }

        $slot = null;
        $errorMsg = null;

        DB::transaction(function () use ($data, &$slot, &$errorMsg) {
            // หา slot ว่างแรกในลานที่เลือก (lock เพื่อกัน race condition)
            $slot = ParkingSlot::where('parking_lot_id', $data['parking_lot_id'])
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            if (!$slot) {
                $errorMsg = 'ไม่มีช่องจอดว่างในลานที่เลือก';
                return;
            }

            ParkingLog::create([
                'vehicle_id'      => $data['vehicle_id'],
                'parking_lot_id'  => $data['parking_lot_id'],
                'parking_slot_id' => $slot->id,
                'check_in_time'   => now(),
            ]);

            $slot->update(['status' => 'occupied']);
        });

        if ($errorMsg) {
            return back()
                ->withErrors(['parking_lot_id' => $errorMsg])
                ->withInput();
        }

        $vehicle = Vehicle::find($data['vehicle_id']);

        admin_audit('parking_log.check_in', $vehicle, [
            'parking_lot_id'  => $data['parking_lot_id'],
            'parking_slot_id' => $slot->id,
        ]);

        return redirect()->route('admin.check-in.create')
            ->with('success', "Check-In สำเร็จ! ทะเบียน {$vehicle->license_plate} → ช่อง {$slot->slot_number}");
    }
}
