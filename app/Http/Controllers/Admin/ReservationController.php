<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    private array $statuses = ['pending', 'confirmed', 'cancelled', 'expired'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $lotId = $request->query('lot_id');

        $from = $request->query('from'); // YYYY-MM-DD
        $to   = $request->query('to');   // YYYY-MM-DD

        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);

        $reservations = Reservation::query()
            ->with([
                'user:id,name,email',
                'vehicle:id,user_id,license_plate',
                'parkingLot:id,name',
                'parkingSlot:id,parking_lot_id,slot_number',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->whereHas('vehicle', fn($x) => $x->where('license_plate', 'like', "%{$q}%"))
                        ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"))
                        ->orWhereHas('user', fn($x) => $x->where('email', 'like', "%{$q}%"));
                });
            })
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_end', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', compact(
            'reservations',
            'lots',
            'q',
            'status',
            'lotId',
            'from',
            'to'
        ));
    }

    public function create()
    {
        $vehicles = Vehicle::with('user:id,name')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'user_id']);

        $lots = ParkingLot::orderBy('name')->get(['id', 'name', 'hourly_rate']);

        // ส่ง available slots ทั้งหมดให้ Alpine.js filter client-side ตาม lot ที่เลือก
        $slots = ParkingSlot::where('status', 'available')
            ->orderBy('parking_lot_id')
            ->orderBy('slot_number')
            ->get(['id', 'parking_lot_id', 'slot_number']);

        return view('admin.reservations.create', compact('vehicles', 'lots', 'slots'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_id'      => ['required', 'exists:vehicles,id'],
            'parking_lot_id'  => ['required', 'exists:parking_lots,id'],
            'parking_slot_id' => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'   => ['required', 'date', 'after:now'],
            'reserve_end'     => ['required', 'date', 'after:reserve_start'],
            'reservation_fee' => ['nullable', 'numeric', 'min:0'],
        ]);

        // [1] กัน slot ข้ามลาน
        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])->withInput();
            }

            // [2] ห้ามจอง slot ซ้ำเวลาทับซ้อน
            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'], $data['reserve_end'])) {
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
            'reserve_end'     => $data['reserve_end'],
            'reservation_fee' => $data['reservation_fee'] ?? 0,
            'status'          => 'pending',
        ]);

        admin_audit('reservation.create', $reservation, []);

        return redirect()->route('admin.reservations.index')
            ->with('success', "สร้างการจองสำเร็จ #{$reservation->id} — สถานะ: pending");
    }

    public function edit(Reservation $reservation)
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $statuses = $this->statuses;

        // slots เฉพาะ lot ปัจจุบัน เพื่อเลือกง่าย
        $slots = ParkingSlot::query()
            ->where('parking_lot_id', $reservation->parking_lot_id)
            ->orderBy('slot_number')
            ->get(['id', 'slot_number', 'parking_lot_id']);

        $reservation->load(['user', 'vehicle', 'parkingLot', 'parkingSlot']);

        return view('admin.reservations.edit', compact('reservation', 'lots', 'slots', 'statuses'));
    }

    public function update(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'parking_lot_id'   => ['required', 'exists:parking_lots,id'],
            'parking_slot_id'  => ['nullable', 'exists:parking_slots,id'],
            'reserve_start'    => ['required', 'date'],
            'reserve_end'      => ['required', 'date', 'after:reserve_start'],
            'reservation_fee'  => ['required', 'numeric', 'min:0'],
            'status'           => ['required', Rule::in($this->statuses)],
        ]);

        // [1] กัน slot ข้ามลาน
        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string) $slotLotId !== (string) $data['parking_lot_id']) {
                return back()->withErrors(['parking_slot_id' => 'ช่องจอดนี้ไม่ได้อยู่ในลานที่เลือก'])->withInput();
            }

            // [2] ห้ามจอง slot ซ้ำเวลาทับซ้อน (exclude ตัวเองเมื่อ update)
            if ($this->hasSlotConflict($data['parking_slot_id'], $data['reserve_start'], $data['reserve_end'], $reservation->id)) {
                return back()
                    ->withErrors(['parking_slot_id' => 'ช่องจอดนี้ถูกจองในช่วงเวลาดังกล่าวแล้ว'])
                    ->withInput();
            }
        }

        $reservation->update($data);

        admin_audit('reservation.update', $reservation, [
            'changed' => array_keys($data),
        ]);

        return redirect()->route('admin.reservations.edit', $reservation)
            ->with('success', 'อัปเดต Reservation เรียบร้อยแล้ว');
    }

    public function confirm(Reservation $reservation)
    {
        if ($reservation->status !== 'pending') {
            return back()->withErrors(['error' => "ไม่สามารถยืนยันได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $reservation->update(['status' => 'confirmed']);

        admin_audit('reservation.confirm', $reservation, ['status' => 'confirmed']);

        return back()->with('success', "ยืนยันการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        admin_audit('reservation.delete', $reservation, []);
        return redirect()->route('admin.reservations.index')->with('success', 'ลบ Reservation แล้ว');
    }

    /**
     * ตรวจสอบ time overlap สำหรับ slot ที่ระบุ
     * overlap เมื่อ: start_a < end_b AND end_a > start_b
     *
     * @param int|null $excludeId  reservation id ที่ต้อง exclude (กรณี update)
     */
    private function hasSlotConflict(int $slotId, string $start, string $end, ?int $excludeId = null): bool
    {
        return Reservation::where('parking_slot_id', $slotId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($start, $end) {
                $q->where('reserve_start', '<', $end)
                  ->where('reserve_end', '>', $start);
            })
            ->exists();
    }
}
