<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
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

        // ค้นหาการจองที่ confirmed และอยู่ในช่วง grace period
        $reservation = Reservation::checkable()
            ->where('vehicle_id', $data['vehicle_id'])
            ->orderBy('reserve_start')
            ->first();

        // ถ้ามีการจองแบบ specific lot ให้ใช้ lot นั้น ไม่งั้นใช้ที่ admin เลือก
        $lotId = $reservation ? $reservation->parking_lot_id : $data['parking_lot_id'];

        $slot     = null;
        $errorMsg = null;
        $log      = null;
        $now      = now();

        DB::transaction(function () use ($data, $reservation, $lotId, $now, &$slot, &$errorMsg, &$log) {
            // ถ้ามีการจองที่ reserve specific slot ไว้ ลอง slot นั้นก่อน
            if ($reservation && $reservation->parking_slot_id) {
                $slot = ParkingSlot::where('id', $reservation->parking_slot_id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
            }

            // ถ้าไม่มี reserved slot หรือถูกใช้ไปแล้ว หา slot ว่างใน lot
            if (!$slot) {
                $slot = ParkingSlot::where('parking_lot_id', $lotId)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$slot) {
                $errorMsg = 'ไม่มีช่องจอดว่างในลานที่เลือก';
                return;
            }

            $log = ParkingLog::create([
                'vehicle_id'      => $data['vehicle_id'],
                'parking_lot_id'  => $slot->parking_lot_id,
                'parking_slot_id' => $slot->id,
                'check_in_time'   => $now,
                'reservation_id'  => $reservation?->id,
            ]);

            $slot->update(['status' => 'occupied']);

            if ($reservation) {
                $reservation->update([
                    'status'        => 'checked_in',
                    'checked_in_at' => $now,
                ]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => 'confirmed',
                    'new_status'     => 'checked_in',
                    'changed_by'     => null,
                    'note'           => "Auto check-in: รถเข้าจอดที่ช่อง {$slot->slot_number}",
                ]);
            }
        });

        if ($errorMsg) {
            return back()
                ->withErrors(['parking_lot_id' => $errorMsg])
                ->withInput();
        }

        $vehicle = Vehicle::find($data['vehicle_id']);

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
