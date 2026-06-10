<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Http\Request;

class ParkingLotController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $lots = ParkingLot::with('owner:id,name')
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
        $owners = User::where('role', 'owner')->orderBy('name')->get(['id', 'name']);
        return view('admin.parking-lots.create', compact('owners'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string'],
            'total_slots' => ['required', 'integer', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'owner_id'    => ['nullable', 'exists:users,id'],
            'is_active'   => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        ParkingLot::create($data);

        return redirect()->route('admin.parking-lots.index')
            ->with('success', 'เพิ่มลานจอดเรียบร้อยแล้ว');
    }

    public function edit(ParkingLot $parking_lot)
    {
        $owners = User::where('role', 'owner')->orderBy('name')->get(['id', 'name']);
        return view('admin.parking-lots.edit', compact('parking_lot', 'owners'));
    }

    public function update(Request $request, ParkingLot $parking_lot)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'location'    => ['nullable', 'string'],
            'total_slots' => ['required', 'integer', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'owner_id'    => ['nullable', 'exists:users,id'],
            'is_active'   => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
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
