<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Support\ThaiGeography;
use App\Rules\ExistingThaiAddress;
use App\Models\ParkingLot;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
                    ->orWhere('subdistrict', 'like', "%{$q}%")
                    ->orWhere('postal_code', 'like', "%{$q}%")
                    ->orWhere('province', 'like', "%{$q}%")
                    ->orWhere('landmark', 'like', "%{$q}%");
            }))
            ->withCount([
                'slots',
                'slots as available_count' => fn ($s) => $s->where('status', 'available'),
                'slots as reserved_count' => fn ($s) => $s->where('status', 'reserved'),
                'slots as occupied_count' => fn ($s) => $s->where('status', 'occupied'),
                'reservations as active_reservations_count' => fn ($r) => $r->whereIn('status', Reservation::ACTIVE_STATUSES),
            ])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('parking.lots.index', compact('lots', 'q') + ['scope' => 'owner']);
    }

    public function create()
    {
        return view('parking.lots.form', ['lot' => null, 'scope' => 'owner']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'location'             => ['nullable', 'string'],
            'address'              => ['nullable', 'string', 'max:500'],
            'province'             => ['nullable', 'string', Rule::in(ThaiGeography::provinces())],
            'district'             => ['nullable', 'string', 'max:100', 'required_with:province'],
            'subdistrict'          => ['nullable', 'string', 'max:100', 'required_with:province', new ExistingThaiAddress($request->input('province'), $request->input('district'))],
            'landmark'             => ['nullable', 'string', 'max:500'],
            'total_slots'          => ['required', 'integer', 'min:0'],
            'hourly_rate'          => ['required', 'numeric', 'min:0'],
            'reservations_enabled' => ['boolean'],
        ]);

        $data['owner_id']             = Auth::id();
        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);


        // รหัสไปรษณีย์มาจากชุดข้อมูลเขตการปกครอง ไม่รับค่าที่ส่งมาจากเบราว์เซอร์
        $data['postal_code'] = ThaiGeography::postalCode($data['province'] ?? null, $data['district'] ?? null, $data['subdistrict'] ?? null);
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
        return view('parking.lots.form', ['lot' => $lot, 'scope' => 'owner']);
    }

    public function update(Request $request, int $parking_lot)
    {
        $lot = $this->ownedLot($parking_lot);

        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:255'],
            'location'             => ['nullable', 'string'],
            'address'              => ['nullable', 'string', 'max:500'],
            'province'             => ['nullable', 'string', Rule::in(ThaiGeography::provinces())],
            'district'             => ['nullable', 'string', 'max:100', 'required_with:province'],
            'subdistrict'          => ['nullable', 'string', 'max:100', 'required_with:province', new ExistingThaiAddress($request->input('province'), $request->input('district'))],
            'landmark'             => ['nullable', 'string', 'max:500'],
            'total_slots'          => ['required', 'integer', 'min:0'],
            'hourly_rate'          => ['required', 'numeric', 'min:0'],
            'reservations_enabled' => ['boolean'],
        ]);

        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);

        $before = $lot->only(array_keys($data));

        // รหัสไปรษณีย์มาจากชุดข้อมูลเขตการปกครอง ไม่รับค่าที่ส่งมาจากเบราว์เซอร์
        $data['postal_code'] = ThaiGeography::postalCode($data['province'] ?? null, $data['district'] ?? null, $data['subdistrict'] ?? null);
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
