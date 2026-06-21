<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
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
        $data['is_active']            = true;
        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);

        ParkingLot::create($data);

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
        $lot->update($data);

        return redirect()->route('owner.parking-lots.index')
            ->with('success', 'อัปเดตลานจอดเรียบร้อยแล้ว');
    }

    public function toggle(int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);
        $lot->update(['is_active' => !$lot->is_active]);

        $status = $lot->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        return back()->with('success', "ลานจอด \"{$lot->name}\" {$status}แล้ว");
    }

    public function destroy(int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);

        if ($lot->slots()->whereIn('status', ['occupied', 'reserved'])->exists()) {
            return back()->withErrors(['error' => 'ไม่สามารถลบลานจอดที่มีรถจอดหรือมีการจองอยู่']);
        }

        $lot->delete();

        return redirect()->route('owner.parking-lots.index')
            ->with('success', 'ลบลานจอดเรียบร้อยแล้ว');
    }
}
