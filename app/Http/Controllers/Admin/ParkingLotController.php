<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use Illuminate\Http\Request;

class ParkingLotController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lots = ParkingLot::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            })
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.parking-lots.index', compact('lots', 'q'));
    }

    public function create()
    {
        return view('admin.parking-lots.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string'],
            'total_slots' => ['required', 'integer', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
        ]);

        ParkingLot::create($data);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'เพิ่มลานจอดเรียบร้อยแล้ว');
    }

    public function edit(ParkingLot $parking_lot)
    {
        return view('admin.parking-lots.edit', compact('parking_lot'));
    }

    public function update(Request $request, ParkingLot $parking_lot)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string'],
            'total_slots' => ['required', 'integer', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
        ]);

        $parking_lot->update($data);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'อัปเดตลานจอดเรียบร้อยแล้ว');
    }

    public function destroy(ParkingLot $parking_lot)
    {
        $parking_lot->delete();

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'ลบลานจอดเรียบร้อยแล้ว');
    }
}
