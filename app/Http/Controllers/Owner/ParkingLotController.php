<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParkingLotController extends Controller
{
    private function ownedLot(int $id): ParkingLot
    {
        $lot = ParkingLot::findOrFail($id);
        abort_if($lot->owner_id !== Auth::id(), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');
        return $lot;
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lots = ParkingLot::with('owner:id,name')
            ->where('owner_id', Auth::id())
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('address', 'like', "%{$q}%")
                    ->orWhere('district', 'like', "%{$q}%")
                    ->orWhere('province', 'like', "%{$q}%")
                    ->orWhere('landmark', 'like', "%{$q}%");
            }))
            ->withCount(['slots', 'reservations'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('owner.parking-lots.index', compact('lots', 'q'));
    }

    public function create()
    {
        return view('owner.parking-lots.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'location'             => ['nullable', 'string'],
            'address'              => ['nullable', 'string', 'max:500'],
            'district'             => ['nullable', 'string', 'max:255'],
            'province'             => ['nullable', 'string', 'max:255'],
            'landmark'             => ['nullable', 'string', 'max:500'],
            'total_slots'          => ['required', 'integer', 'min:0'],
            'hourly_rate'          => ['required', 'numeric', 'min:0'],
            'reservations_enabled' => ['boolean'],
        ]);

        $data['owner_id']             = Auth::id();
        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);

        $lot = ParkingLot::create($data);

        audit_log('parking_lot.create', $lot, [
            'name'                 => $lot->name,
            'hourly_rate'          => $lot->hourly_rate,
            'reservations_enabled' => $lot->reservations_enabled,
        ]);

        return redirect()->route('owner.parking-lots.index')
            ->with('success', 'เพิ่มลานจอดเรียบร้อยแล้ว');
    }

    public function edit(int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);
        return view('owner.parking-lots.edit', compact('lot'));
    }

    public function update(Request $request, int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'location'             => ['nullable', 'string'],
            'address'              => ['nullable', 'string', 'max:500'],
            'district'             => ['nullable', 'string', 'max:255'],
            'province'             => ['nullable', 'string', 'max:255'],
            'landmark'             => ['nullable', 'string', 'max:500'],
            'total_slots'          => ['required', 'integer', 'min:0'],
            'hourly_rate'          => ['required', 'numeric', 'min:0'],
            'reservations_enabled' => ['boolean'],
        ]);

        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);

        $before = $lot->only(array_keys($data));
        $lot->update($data);

        audit_log('parking_lot.update', $lot, ['changes' => audit_changes($before, $lot)]);

        return redirect()->route('owner.parking-lots.index')
            ->with('success', 'อัปเดตลานจอดเรียบร้อยแล้ว');
    }

    public function destroy(int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);

        // ลบไม่ได้ถ้ายังมีการจองค้าง (รอยืนยันมัดจำ / ยืนยันแล้ว / กำลังจอด) — กันการจองหายโดยผู้จองไม่รู้ (project-plan.md §16.1)
        if ($lot->reservations()->whereIn('status', Reservation::ACTIVE_STATUSES)->exists()) {
            return back()->withErrors(['error' => 'ไม่สามารถลบลานจอดที่ยังมีการจองค้างอยู่ (รอยืนยันมัดจำ / ยืนยันแล้ว / กำลังจอด) — ปิดรับการจองแล้วรอให้การจองเสร็จสิ้นก่อน']);
        }

        $lot->delete();

        audit_log('parking_lot.delete', $lot, ['name' => $lot->name]);

        return redirect()->route('owner.parking-lots.index')
            ->with('success', 'ลบลานจอดเรียบร้อยแล้ว');
    }
}
