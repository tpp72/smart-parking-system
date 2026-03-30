<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    /** รายการการจองของ user ที่ login อยู่ */
    public function index()
    {
        $reservations = Reservation::with(['parkingLot:id,name', 'parkingSlot:id,slot_number', 'vehicle:id,license_plate'])
            ->where('user_id', Auth::id())
            ->orderByDesc('reserve_start')
            ->paginate(10);

        return view('user.reservations.index', compact('reservations'));
    }

    /** ฟอร์มสร้างการจอง (เฉพาะรถของตัวเอง) */
    public function create()
    {
        /** @var User $authUser */
        $authUser = Auth::user();
        $vehicles = $authUser->vehicles()->orderBy('license_plate')->get(['id', 'license_plate', 'brand']);
        $lots     = ParkingLot::orderBy('name')->get(['id', 'name', 'hourly_rate']);
        $slots    = ParkingSlot::where('status', 'available')
            ->orderBy('parking_lot_id')->orderBy('slot_number')
            ->get(['id', 'parking_lot_id', 'slot_number']);

        return view('user.reservations.create', compact('vehicles', 'lots', 'slots'));
    }

    /** บันทึกการจอง */
    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id'      => ['required', 'exists:vehicles,id'],
            'parking_lot_id'  => ['required', 'exists:parking_lots,id'],
            'parking_slot_id' => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'   => ['required', 'date', 'after:now'],
            'reserve_end'     => ['required', 'date', 'after:reserve_start'],
        ]);

        // [1] กันจอง vehicle ที่ไม่ใช่ของตัวเอง
        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->vehicles()->where('id', $data['vehicle_id'])->exists()) {
            abort(403, 'ไม่มีสิทธิ์จองด้วยรถนี้');
        }

        // [2] กัน slot ข้ามลาน
        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])
                    ->withInput();
            }

            // [3] ห้ามจอง slot ซ้ำเวลาเดียวกัน (time overlap)
            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'], $data['reserve_end'])) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว กรุณาเลือกช่องอื่นหรือเปลี่ยนเวลา'])
                    ->withInput();
            }
        }

        Reservation::create([
            'user_id'         => Auth::id(),
            'vehicle_id'      => $data['vehicle_id'],
            'parking_lot_id'  => $data['parking_lot_id'],
            'parking_slot_id' => $data['parking_slot_id'] ?? null,
            'reserve_start'   => $data['reserve_start'],
            'reserve_end'     => $data['reserve_end'],
            'reservation_fee' => 0,
            'status'          => 'pending',
        ]);

        return redirect()->route('user.reservations.index')
            ->with('success', 'ส่งคำขอจองสำเร็จ! รอ Admin ยืนยันการจอง');
    }

    /**
     * ตรวจสอบว่า slot นี้มีการจองที่ทับซ้อนกันอยู่หรือไม่
     * นับเฉพาะ status pending/confirmed เท่านั้น
     */
    private function hasSlotConflict(int $slotId, string $start, string $end): bool
    {
        return Reservation::where('parking_slot_id', $slotId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where(function ($q) use ($start, $end) {
                // overlap: start_a < end_b AND end_a > start_b
                $q->where('reserve_start', '<', $end)
                  ->where('reserve_end', '>', $start);
            })
            ->exists();
    }
}
