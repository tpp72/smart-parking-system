<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $vehicles = Vehicle::query()
            ->with('user:id,name,email')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('license_plate', 'ilike', "%{$q}%")
                        ->orWhere('brand', 'ilike', "%{$q}%")
                        ->orWhere('color', 'ilike', "%{$q}%")
                        ->orWhereHas('user', fn($u) => $u->where('name', 'ilike', "%{$q}%"));
                });
            })
            ->orderBy('license_plate')
            ->paginate(20)
            ->withQueryString();

        return view('admin.vehicles.index', compact('vehicles', 'q'));
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vehicles.create', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'       => ['required', 'exists:users,id'],
            'license_plate' => ['required', 'string', 'max:50', 'unique:vehicles,license_plate'],
            'brand'         => ['required', 'string', 'max:100'],
            'color'         => ['required', 'string', 'max:50'],
        ]);

        $vehicle = Vehicle::create($data);

        admin_audit('vehicle.create', $vehicle, []);

        return redirect()->route('admin.vehicles.index')
            ->with('success', "เพิ่มรถ {$vehicle->license_plate} เรียบร้อยแล้ว");
    }

    public function edit(Vehicle $vehicle)
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.vehicles.edit', compact('vehicle', 'users'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $data = $request->validate([
            'user_id'       => ['required', 'exists:users,id'],
            'license_plate' => [
                'required', 'string', 'max:50',
                Rule::unique('vehicles', 'license_plate')->ignore($vehicle->id),
            ],
            'brand'         => ['required', 'string', 'max:100'],
            'color'         => ['required', 'string', 'max:50'],
        ]);

        $vehicle->update($data);

        admin_audit('vehicle.update', $vehicle, ['changed' => array_keys($data)]);

        return redirect()->route('admin.vehicles.index')
            ->with('success', "อัปเดตรถ {$vehicle->license_plate} เรียบร้อยแล้ว");
    }

    public function destroy(Vehicle $vehicle)
    {
        $plate = $vehicle->license_plate;
        $vehicle->delete();

        admin_audit('vehicle.delete', $vehicle, ['license_plate' => $plate]);

        return redirect()->route('admin.vehicles.index')
            ->with('success', "ลบรถ {$plate} เรียบร้อยแล้ว");
    }
}
