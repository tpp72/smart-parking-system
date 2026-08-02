<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Vehicle;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    private array $statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'];

    public function __construct(
        private CheckInService  $checkInService,
        private CheckOutService $checkOutService,
    ) {}

    /** Admin จัดการการจองได้เฉพาะของลานที่ยังไม่มีเจ้าของ */
    private function assertLotUnowned(int $lotId): void
    {
        abort_unless(
            ParkingLot::where('id', $lotId)->whereNull('owner_id')->exists(),
            403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้'
        );
    }

    private function assertReservationLotUnowned(Reservation $reservation): void
    {
        $this->assertLotUnowned($reservation->parking_lot_id);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $lotId = $request->query('lot_id');

        $from = $request->query('from'); // YYYY-MM-DD
        $to   = $request->query('to');   // YYYY-MM-DD

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);
        $lotIds = $lots->pluck('id');

        $reservations = Reservation::query()
            ->with([
                'user:id,name,email',
                'vehicle:id,user_id,license_plate',
                'parkingLot:id,name,hourly_rate',
                'parkingSlot:id,parking_lot_id,slot_number',
                'parkingLog:id,reservation_id,check_in_time',
            ])
            ->whereIn('parking_lot_id', $lotIds)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('license_plate', 'like', "%{$q}%")
                        ->orWhereHas('vehicle', fn($x) => $x->where('license_plate', 'like', "%{$q}%"))
                        ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('user', fn($x) => $x->where('email', 'like', "%{$q}%"));
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_start', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        $checkableIds = Reservation::checkable()
            ->whereIn('id', $reservations->pluck('id'))
            ->pluck('id')
            ->all();

        return view('admin.reservations.index', compact(
            'reservations',
            'lots',
            'q',
            'status',
            'lotId',
            'from',
            'to',
            'checkableIds'
        ));
    }

    public function create()
    {
        $vehicles = Vehicle::with('user:id,name')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'user_id']);

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name', 'hourly_rate']);

        $slots = ParkingSlot::where('status', 'available')
            ->whereIn('parking_lot_id', $lots->pluck('id'))
            ->orderBy('parking_lot_id')
            ->orderBy('slot_number')
            ->get(['id', 'parking_lot_id', 'slot_number']);

        return view('admin.reservations.create', compact('vehicles', 'lots', 'slots'));
    }

    public function store(Request $request)
    {
        // Normalize reserve_start: accept UTC offset strings (e.g. from API clients) and
        // convert to Asia/Bangkok local time so that after:now validation is consistent.
        if ($request->has('reserve_start') && $request->input('reserve_start') !== '') {
            try {
                $normalized = \Carbon\Carbon::parse($request->input('reserve_start'), 'Asia/Bangkok')
                    ->setTimezone('Asia/Bangkok')
                    ->format('Y-m-d H:i:s');
                $request->merge(['reserve_start' => $normalized]);
            } catch (\Exception) {
                // leave as-is — validation will catch the invalid format
            }
        }

        $data = $request->validate([
            'vehicle_id'      => ['required', 'exists:vehicles,id'],
            'parking_lot_id'  => ['required', 'exists:parking_lots,id'],
            'parking_slot_id' => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'   => ['required', 'date', 'after:now'],
            'reservation_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->assertLotUnowned((int) $data['parking_lot_id']);

        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])->withInput();
            }

            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'])) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว กรุณาเลือกช่องอื่นหรือเปลี่ยนเวลา'])
                    ->withInput();
            }
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);

        $reservation = Reservation::create([
            'user_id'         => $vehicle->user_id,
            'vehicle_id'      => $data['vehicle_id'],
            'parking_lot_id'  => $data['parking_lot_id'],
            'parking_slot_id' => $data['parking_slot_id'] ?? null,
            'reserve_start'   => $data['reserve_start'],
            'reservation_fee' => $data['reservation_fee'] ?? 0,
            'status'          => 'pending',
        ]);

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'old_status'     => null,
            'new_status'     => 'pending',
            'changed_by'     => Auth::id(),
            'note'           => 'Admin สร้างการจอง',
        ]);

        admin_audit('reservation.create', $reservation, []);

        return redirect()->route('admin.reservations.index')
            ->with('success', "สร้างการจองสำเร็จ #{$reservation->id} — สถานะ: pending");
    }

    public function edit(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);
        $statuses = $this->statuses;

        $slots = ParkingSlot::query()
            ->where('parking_lot_id', $reservation->parking_lot_id)
            ->orderBy('slot_number')
            ->get(['id', 'slot_number', 'parking_lot_id']);

        $reservation->load(['user', 'vehicle', 'parkingLot', 'parkingSlot', 'parkingLog.parkingSlot']);

        return view('admin.reservations.edit', compact('reservation', 'lots', 'slots', 'statuses'));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $data = $request->validate([
            'parking_lot_id'   => ['required', 'exists:parking_lots,id'],
            'parking_slot_id'  => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'    => ['required', 'date'],
            'reservation_fee'  => ['required', 'numeric', 'min:0'],
            'status'           => ['required', Rule::in($this->statuses)],
        ]);

        $this->assertLotUnowned((int) $data['parking_lot_id']);

        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])->withInput();
            }

            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'], $reservation->id)) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว'])
                    ->withInput();
            }
        }

        $oldStatus = $reservation->status;
        $oldSlotId = $reservation->parking_slot_id;

        DB::transaction(function () use ($reservation, $data, $oldStatus, $oldSlotId) {
            $reservation->update($data);

            if ($data['status'] === 'cancelled'
                && in_array($oldStatus, ['pending', 'confirmed'], true)
                && $oldSlotId
            ) {
                ParkingSlot::where('id', $oldSlotId)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available']);
            }

            if ($oldStatus !== $data['status']) {
                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => $oldStatus,
                    'new_status'     => $data['status'],
                    'changed_by'     => Auth::id(),
                    'note'           => 'Admin แก้ไขสถานะ',
                ]);
            }
        });

        if ($oldStatus !== $data['status'] && $data['status'] === 'cancelled') {
            notify_user(
                $reservation->user_id,
                'การจองถูกยกเลิก',
                "การจอง #{$reservation->id} ถูกยกเลิกโดยผู้ดูแลระบบ"
            );
        }

        admin_audit('reservation.update', $reservation, [
            'changed' => array_keys($data),
        ]);

        return redirect()->route('admin.reservations.edit', $reservation)
            ->with('success', 'อัปเดต Reservation เรียบร้อยแล้ว');
    }

    public function confirm(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

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
                'note'           => 'Admin ยืนยันการจอง',
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

        admin_audit('reservation.confirm', $reservation, ['status' => 'confirmed']);

        return back()->with('success', "ยืนยันการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    /** Check-In รถของการจองนี้โดยตรง (แทนหน้า Manual Check-In แยก) */
    public function checkIn(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        if ($reservation->status !== 'confirmed') {
            return back()->withErrors(['error' => "ไม่สามารถเช็คอินได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        if (!Reservation::checkable()->where('id', $reservation->id)->exists()) {
            return back()->withErrors(['error' => 'อยู่นอกช่วงเวลาเช็คอิน (เร็วเกินไปหรือเกินเวลากำหนด)']);
        }

        $allowedLotIds = ParkingLot::unowned()->pluck('id')->all();

        $result = $this->checkInService->checkIn(
            $reservation->license_plate,
            $reservation->brand,
            $reservation->color,
            $reservation->parking_lot_id,
            $allowedLotIds,
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

        admin_audit('parking_log.check_in', $reservation, [
            'parking_lot_id'  => $slot->parking_lot_id,
            'parking_slot_id' => $slot->id,
        ]);

        return back()->with('success',
            "Check-In สำเร็จ! ทะเบียน {$reservation->license_plate} → ช่อง {$slot->slot_number}"
        );
    }

    /** Check-Out รถของการจองนี้โดยตรง (แทนหน้า Manual Check-Out แยก) */
    public function checkOut(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        if ($reservation->status !== 'checked_in') {
            return back()->withErrors(['error' => "ไม่สามารถเช็คเอาท์ได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $log = $reservation->parkingLog;
        abort_if(!$log, 404, 'ไม่พบ Parking Log ของการจองนี้');

        $allowedLotIds = ParkingLot::unowned()->pluck('id')->all();
        $result = $this->checkOutService->checkOut($log, $allowedLotIds);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        admin_audit('parking_log.check_out', $log, [
            'total_hours'          => $result['totalHours'],
            'parking_fee'          => $result['parkingFee'],
            'reservation_discount' => $result['deposit'],
            'total_amount'         => $result['totalAmount'],
        ]);

        return back()->with('success', sprintf(
            'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
            $reservation->license_plate,
            $result['totalHours'],
            $result['parkingFee'],
            $result['totalAmount'],
        ));
    }

    public function destroy(Reservation $reservation)
    {
        $this->assertReservationLotUnowned($reservation);

        $reservation->delete();
        admin_audit('reservation.delete', $reservation, []);
        return redirect()->route('admin.reservations.index')->with('success', 'ลบ Reservation แล้ว');
    }

    /**
     * ตรวจสอบ time overlap สำหรับ slot ที่ระบุ
     * แต่ละการจองมีหน้าต่าง [reserve_start, reserve_start + 1 ชั่วโมง]
     *
     * @param int|null $excludeId  reservation id ที่ต้อง exclude (กรณี update)
     */
    private function hasSlotConflict(int $slotId, string $start, ?int $excludeId = null): bool
    {
        $end = \Carbon\Carbon::parse($start)->addHour()->toDateTimeString();

        return Reservation::where('parking_slot_id', $slotId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where('reserve_start', '<', $end)
            ->whereRaw("reserve_start + INTERVAL '1 hour' > ?", [$start])
            ->exists();
    }
}
