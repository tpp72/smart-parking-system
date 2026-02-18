<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
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

        // กันเลือก slot ที่ไม่ได้อยู่ใน lot เดียวกัน (ถ้าเลือก)
        if (!empty($data['parking_slot_id'])) {
            $slotLotId = ParkingSlot::where('id', $data['parking_slot_id'])->value('parking_lot_id');
            if ((string)$slotLotId !== (string)$data['parking_lot_id']) {
                return back()->withErrors(['parking_slot_id' => 'ช่องจอดต้องอยู่ในลานเดียวกับที่เลือก'])->withInput();
            }
        }

        $reservation->update($data);

        admin_audit('reservation.update', $reservation, [
            'changed' => array_keys($data),
        ]);

        return redirect()->route('admin.reservations.edit', $reservation)
            ->with('success', 'อัปเดต Reservation เรียบร้อยแล้ว');
    }

    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        admin_audit('reservation.delete', $reservation, []);
        return redirect()->route('admin.reservations.index')->with('success', 'ลบ Reservation แล้ว');
    }
}
