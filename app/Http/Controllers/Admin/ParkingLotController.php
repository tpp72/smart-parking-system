<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use Illuminate\Http\Request;

/**
 * Admin จัดการลานของ Admin (owner_id = NULL) เท่านั้น — สร้างลานให้ Owner หรือโอนลานไม่ได้ (project-plan.md §5.1.1)
 */
class ParkingLotController extends Controller
{
    private function assertAdminLot(ParkingLot $lot): void
    {
        abort_if($lot->owner_id !== null, 403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้');
    }

    private function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:255'],
            'location'             => ['nullable', 'string'],
            'address'              => ['nullable', 'string', 'max:500'],
            'district'             => ['nullable', 'string', 'max:255'],
            'province'             => ['nullable', 'string', 'max:255'],
            'landmark'             => ['nullable', 'string', 'max:500'],
            'total_slots'          => ['required', 'integer', 'min:0'],
            'hourly_rate'          => ['required', 'numeric', 'min:0'],
            'reservations_enabled' => ['boolean'],
        ];
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lots = ParkingLot::unowned()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('location', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('district', 'like', "%{$q}%")
                        ->orWhere('province', 'like', "%{$q}%")
                        ->orWhere('landmark', 'like', "%{$q}%");
                });
            })
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

        return view('parking.lots.index', compact('lots', 'q') + ['scope' => 'admin']);
    }

    public function create()
    {
        return view('parking.lots.form', ['lot' => null, 'scope' => 'admin']);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);
        $lot = ParkingLot::create($data + ['owner_id' => null]);

        audit_log('parking_lot.create', $lot, [
            'name'                 => $lot->name,
            'hourly_rate'          => $lot->hourly_rate,
            'reservations_enabled' => $lot->reservations_enabled,
        ]);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'เพิ่มลานจอดเรียบร้อยแล้ว');
    }

    public function edit(ParkingLot $parking_lot)
    {
        $this->assertAdminLot($parking_lot);

        return view('parking.lots.form', ['lot' => $parking_lot, 'scope' => 'admin']);
    }

    public function update(Request $request, ParkingLot $parking_lot)
    {
        $this->assertAdminLot($parking_lot);

        $data = $request->validate($this->rules());
        $data['reservations_enabled'] = $request->boolean('reservations_enabled', true);

        $before = $parking_lot->only(array_keys($data));
        $parking_lot->update($data);

        audit_log('parking_lot.update', $parking_lot, ['changes' => audit_changes($before, $parking_lot)]);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'อัปเดตลานจอดเรียบร้อยแล้ว');
    }

    public function destroy(ParkingLot $parking_lot)
    {
        $this->assertAdminLot($parking_lot);

        // ลบไม่ได้ถ้ายังมีการจองค้าง (รอยืนยันมัดจำ / ยืนยันแล้ว / กำลังจอด) — กฎเดียวกับฝั่ง Owner
        if ($parking_lot->reservations()->whereIn('status', Reservation::ACTIVE_STATUSES)->exists()) {
            return back()->withErrors(['error' => 'ไม่สามารถลบลานจอดที่ยังมีการจองค้างอยู่ (รอยืนยันมัดจำ / ยืนยันแล้ว / กำลังจอด) — ปิดรับการจองแล้วรอให้การจองเสร็จสิ้นก่อน']);
        }

        $parking_lot->delete();

        audit_log('parking_lot.delete', $parking_lot, ['name' => $parking_lot->name]);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'ลบลานจอดเรียบร้อยแล้ว');
    }
}
