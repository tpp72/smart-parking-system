<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
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
            'reserve_start'   => ['required', 'date', 'after:now', 'before:' . now()->addDay()->toDateTimeString()],
        ], [
            'vehicle_id.required'      => 'กรุณาเลือกรถ',
            'vehicle_id.exists'        => 'ไม่พบรถที่เลือกในระบบ',
            'parking_lot_id.required'  => 'กรุณาเลือกลานจอด',
            'parking_lot_id.exists'    => 'ไม่พบลานจอดที่เลือกในระบบ',
            'parking_slot_id.exists'   => 'ไม่พบช่องจอดที่เลือกในระบบ',
            'reserve_start.required'   => 'กรุณาระบุวันและเวลาที่ต้องการจอง',
            'reserve_start.date'       => 'รูปแบบวันที่/เวลาไม่ถูกต้อง',
            'reserve_start.after'      => 'เวลาจองต้องเป็นเวลาในอนาคต',
            'reserve_start.before'     => 'จองล่วงหน้าได้ไม่เกิน 1 วัน (24 ชั่วโมง)',
        ]);

        /** @var User $authUser */
        $authUser = Auth::user();
        if (!$authUser->vehicles()->where('id', $data['vehicle_id'])->exists()) {
            abort(403, 'ไม่มีสิทธิ์จองด้วยรถนี้');
        }

        // ป้องกัน: รถคันนี้มีการจองที่ยัง active อยู่แล้ว
        $hasActiveReservation = Reservation::where('vehicle_id', $data['vehicle_id'])
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->exists();

        if ($hasActiveReservation) {
            return back()
                ->withErrors(['vehicle_id' => 'รถคันนี้มีการจองที่ยังดำเนินการอยู่ กรุณารอให้เสร็จสิ้นก่อน'])
                ->withInput();
        }

        // ป้องกัน: user มีการจอง active อยู่แล้วในช่วงเวลาเดียวกัน (ต่างรถ)
        $end = \Carbon\Carbon::parse($data['reserve_start'])->addHour()->toDateTimeString();
        $hasUserConflict = Reservation::where('user_id', Auth::id())
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->where('reserve_start', '<', $end)
            ->whereRaw("reserve_start + INTERVAL '1 hour' > ?", [$data['reserve_start']])
            ->exists();

        if ($hasUserConflict) {
            return back()
                ->withErrors(['reserve_start' => 'คุณมีการจองอื่นในช่วงเวลานี้อยู่แล้ว กรุณาเลือกเวลาอื่น'])
                ->withInput();
        }

        // ป้องกัน: รถกำลังจอดอยู่ในระบบ
        $isParked = ParkingLog::where('vehicle_id', $data['vehicle_id'])
            ->whereNull('check_out_time')
            ->exists();

        if ($isParked) {
            return back()
                ->withErrors(['vehicle_id' => 'รถคันนี้กำลังจอดอยู่แล้ว ไม่สามารถจองได้ในขณะนี้'])
                ->withInput();
        }

        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])
                    ->withInput();
            }

            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'])) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว กรุณาเลือกช่องอื่นหรือเปลี่ยนเวลา'])
                    ->withInput();
            }
        }

        $reservation = Reservation::create([
            'user_id'         => Auth::id(),
            'vehicle_id'      => $data['vehicle_id'],
            'parking_lot_id'  => $data['parking_lot_id'],
            'parking_slot_id' => $data['parking_slot_id'] ?? null,
            'reserve_start'   => $data['reserve_start'],
            'reservation_fee' => 0,
            'status'          => 'pending',
        ]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'old_status'     => null,
            'new_status'     => 'pending',
            'changed_by'     => Auth::id(),
            'note'           => 'User สร้างการจอง',
        ]);

        return redirect()->route('user.reservations.index')
            ->with('success', 'ส่งคำขอจองสำเร็จ! รอ Admin ยืนยันการจอง');
    }

    /**
     * ตรวจสอบว่า slot นี้มีการจองที่ทับซ้อนกันอยู่หรือไม่
     * หน้าต่างการจอง: [reserve_start, reserve_start + 1 ชั่วโมง]
     */
    private function hasSlotConflict(int $slotId, string $start): bool
    {
        $end = \Carbon\Carbon::parse($start)->addHour()->toDateTimeString();

        return Reservation::where('parking_slot_id', $slotId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '<', $end)
            ->whereRaw("reserve_start + INTERVAL '1 hour' > ?", [$start])
            ->exists();
    }
}
