<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    /** รายการการจองของ user ที่ login อยู่ */
    public function index()
    {
        $reservations = Reservation::with(['parkingLot:id,name', 'parkingSlot:id,slot_number'])
            ->where('user_id', Auth::id())
            ->orderByDesc('reserve_start')
            ->paginate(10);

        return view('user.reservations.index', compact('reservations'));
    }

    /** ฟอร์มสร้างการจอง — กรอกป้ายทะเบียนโดยตรง */
    public function create()
    {
        $lots  = ParkingLot::reservable()->orderBy('name')->get(['id', 'name', 'hourly_rate']);
        $slots = ParkingSlot::where('status', 'available')
            ->orderBy('parking_lot_id')->orderBy('slot_number')
            ->get(['id', 'parking_lot_id', 'slot_number']);

        return view('user.reservations.create', compact('lots', 'slots'));
    }

    /** บันทึกการจอง */
    public function store(Request $request)
    {
        $data = $request->validate([
            'plate_number'    => ['required', 'string', 'max:15'],
            'plate_province'  => ['required', 'string', Rule::in(config('thai_provinces'))],
            'parking_lot_id'  => ['required', 'exists:parking_lots,id'],
            'parking_slot_id' => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'   => ['required', 'date', 'after:now', 'before:' . now()->addDay()->toDateTimeString()],
        ], [
            'plate_number.required'    => 'กรุณากรอกเลขทะเบียนรถ',
            'plate_number.max'         => 'เลขทะเบียนต้องไม่เกิน 15 ตัวอักษร',
            'plate_province.required'  => 'กรุณาเลือกจังหวัด',
            'plate_province.in'        => 'กรุณาเลือกจังหวัดจากรายการ',
            'parking_lot_id.required'  => 'กรุณาเลือกลานจอด',
            'parking_lot_id.exists'    => 'ไม่พบลานจอดที่เลือกในระบบ',
            'parking_slot_id.exists'   => 'ไม่พบช่องจอดที่เลือกในระบบ',
            'reserve_start.required'   => 'กรุณาระบุวันและเวลาที่ต้องการจอง',
            'reserve_start.date'       => 'รูปแบบวันที่/เวลาไม่ถูกต้อง',
            'reserve_start.after'      => 'เวลาจองต้องเป็นเวลาในอนาคต',
            'reserve_start.before'     => 'จองล่วงหน้าได้ไม่เกิน 1 วัน (24 ชั่วโมง)',
        ]);

        $plate = strtoupper(trim($data['plate_number'])) . ' ' . $data['plate_province'];

        // ป้องกัน: ป้ายทะเบียนนี้มีการจองที่ยัง active อยู่แล้ว
        if (Reservation::where('license_plate', $plate)
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->exists()
        ) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่ กรุณารอให้เสร็จสิ้นก่อน'])
                ->withInput();
        }

        // ป้องกัน: user มีการจอง active อยู่แล้วในช่วงเวลาเดียวกัน
        $end = \Carbon\Carbon::parse($data['reserve_start'])->addHour()->toDateTimeString();
        if (Reservation::where('user_id', Auth::id())
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->where('reserve_start', '<', $end)
            ->whereRaw("reserve_start + INTERVAL '1 hour' > ?", [$data['reserve_start']])
            ->exists()
        ) {
            return back()
                ->withErrors(['reserve_start' => 'คุณมีการจองอื่นในช่วงเวลานี้อยู่แล้ว กรุณาเลือกเวลาอื่น'])
                ->withInput();
        }

        // ป้องกัน: รถกำลังจอดอยู่ในระบบ (ตรวจจาก vehicle.license_plate)
        $isParked = ParkingLog::whereNull('check_out_time')
            ->whereHas('vehicle', fn ($q) => $q->where('license_plate', $plate))
            ->exists();

        if ($isParked) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้กำลังจอดอยู่แล้ว ไม่สามารถจองได้ในขณะนี้'])
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

        $lot = ParkingLot::findOrFail($data['parking_lot_id']);

        if (!$lot->reservations_enabled) {
            return back()
                ->withErrors(['parking_lot_id' => 'ลานจอดนี้ไม่รับจองล่วงหน้าในขณะนี้'])
                ->withInput();
        }

        $reservation = Reservation::create([
            'user_id'         => Auth::id(),
            'license_plate'   => $plate,
            'vehicle_id'      => null,
            'parking_lot_id'  => $data['parking_lot_id'],
            'parking_slot_id' => $data['parking_slot_id'] ?? null,
            'reserve_start'   => $data['reserve_start'],
            'reservation_fee' => $lot->hourly_rate,
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

    /** ฟอร์มแก้ไขป้ายทะเบียน (ได้เฉพาะก่อน Check-In) */
    public function edit(Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => 'ไม่สามารถแก้ไขการจองที่มีสถานะ "' . $reservation->status . '" ได้']);
        }

        [$plateNumber, $plateProvince] = $this->splitPlate($reservation->license_plate);

        return view('user.reservations.edit', compact('reservation', 'plateNumber', 'plateProvince'));
    }

    /** บันทึกการแก้ไขป้ายทะเบียน */
    public function update(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => 'ไม่สามารถแก้ไขการจองที่มีสถานะ "' . $reservation->status . '" ได้']);
        }

        $data = $request->validate([
            'plate_number'   => ['required', 'string', 'max:15'],
            'plate_province' => ['required', 'string', Rule::in(config('thai_provinces'))],
        ], [
            'plate_number.required'   => 'กรุณากรอกเลขทะเบียนรถ',
            'plate_number.max'        => 'เลขทะเบียนต้องไม่เกิน 15 ตัวอักษร',
            'plate_province.required' => 'กรุณาเลือกจังหวัด',
            'plate_province.in'       => 'กรุณาเลือกจังหวัดจากรายการ',
        ]);

        $plate = strtoupper(trim($data['plate_number'])) . ' ' . $data['plate_province'];

        // ถ้าไม่มีการเปลี่ยนแปลง ข้ามไปเลย
        if ($plate === $reservation->license_plate) {
            return redirect()->route('user.reservations.index')
                ->with('success', 'ป้ายทะเบียนไม่มีการเปลี่ยนแปลง');
        }

        // ตรวจสอบว่าป้ายทะเบียนใหม่ไม่มีการจอง active อื่น
        if (Reservation::where('license_plate', $plate)
            ->where('id', '!=', $reservation->id)
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->exists()
        ) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่'])
                ->withInput();
        }

        $reservation->update(['license_plate' => $plate]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'old_status'     => $reservation->status,
            'new_status'     => $reservation->status,
            'changed_by'     => Auth::id(),
            'note'           => "User แก้ไขป้ายทะเบียนเป็น {$plate}",
        ]);

        return redirect()->route('user.reservations.index')
            ->with('success', "อัปเดตป้ายทะเบียนเป็น {$plate} เรียบร้อยแล้ว");
    }

    /** ยกเลิกการจองที่เป็นของตัวเอง (เฉพาะ pending / confirmed) */
    public function cancel(Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => "ไม่สามารถยกเลิกการจองที่มีสถานะ \"{$reservation->status}\" ได้"]);
        }

        $oldStatus = $reservation->status;

        DB::transaction(function () use ($reservation, $oldStatus) {
            $reservation->update(['status' => 'cancelled']);

            if ($reservation->parking_slot_id) {
                ParkingSlot::where('id', $reservation->parking_slot_id)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available']);
            }

            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'old_status'     => $oldStatus,
                'new_status'     => 'cancelled',
                'changed_by'     => Auth::id(),
                'note'           => 'User ยกเลิกการจอง',
            ]);
        });

        notify_user(
            Auth::id(),
            'ยกเลิกการจองเรียบร้อยแล้ว',
            "การจอง #{$reservation->id} ถูกยกเลิกเรียบร้อยแล้ว"
        );

        return redirect()->route('user.reservations.index')
            ->with('success', "ยกเลิกการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    /**
     * แยกป้ายทะเบียนที่เก็บเป็นสตริงเดียว (เช่น "กข 1234 กรุงเทพมหานคร") ออกเป็น [เลขทะเบียน, จังหวัด]
     * สำหรับ pre-fill ฟอร์มแก้ไข — ถ้าคำสุดท้ายไม่ตรงกับจังหวัดใดเลย (ข้อมูลเก่าที่ไม่มีจังหวัด) จะคืนจังหวัดว่าง
     *
     * @return array{0: string, 1: string}
     */
    private function splitPlate(?string $plate): array
    {
        $plate = trim((string) $plate);
        $provinces = config('thai_provinces');

        $lastSpace = strrpos($plate, ' ');
        if ($lastSpace !== false) {
            $possibleProvince = substr($plate, $lastSpace + 1);
            if (in_array($possibleProvince, $provinces, true)) {
                return [trim(substr($plate, 0, $lastSpace)), $possibleProvince];
            }
        }

        return [$plate, ''];
    }

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
