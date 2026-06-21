<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuspiciousVehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SuspiciousVehicleController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $entries = SuspiciousVehicle::with('addedBy:id,name')
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('license_plate', 'ilike', "%{$q}%")
                    ->orWhere('reason', 'ilike', "%{$q}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        admin_audit('suspicious_vehicle.index', null, ['q' => $q]);

        return view('admin.suspicious-vehicles.index', compact('entries', 'q'));
    }

    public function create()
    {
        return view('admin.suspicious-vehicles.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'license_plate' => ['required', 'string', 'max:20', Rule::unique('suspicious_vehicles', 'license_plate')],
            'reason'        => ['nullable', 'string', 'max:500'],
            'level'         => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active'     => ['boolean'],
        ]);

        $data['added_by'] = Auth::id();
        $data['is_active'] = $request->boolean('is_active', true);

        $entry = SuspiciousVehicle::create($data);

        admin_audit('suspicious_vehicle.create', $entry, [
            'license_plate' => $entry->license_plate,
            'level'         => $entry->level,
        ]);

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "เพิ่มทะเบียน {$entry->license_plate} ในบัญชีดำเรียบร้อยแล้ว");
    }

    public function edit(SuspiciousVehicle $suspiciousVehicle)
    {
        return view('admin.suspicious-vehicles.edit', compact('suspiciousVehicle'));
    }

    public function update(Request $request, SuspiciousVehicle $suspiciousVehicle)
    {
        $data = $request->validate([
            'license_plate' => [
                'required', 'string', 'max:20',
                Rule::unique('suspicious_vehicles', 'license_plate')->ignore($suspiciousVehicle->id),
            ],
            'reason'    => ['nullable', 'string', 'max:500'],
            'level'     => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', false);

        $suspiciousVehicle->update($data);

        admin_audit('suspicious_vehicle.update', $suspiciousVehicle, [
            'license_plate' => $suspiciousVehicle->license_plate,
            'level'         => $suspiciousVehicle->level,
        ]);

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "อัปเดตทะเบียน {$suspiciousVehicle->license_plate} เรียบร้อยแล้ว");
    }

    public function destroy(SuspiciousVehicle $suspiciousVehicle)
    {
        $plate = $suspiciousVehicle->license_plate;

        admin_audit('suspicious_vehicle.delete', $suspiciousVehicle, [
            'license_plate' => $plate,
            'level'         => $suspiciousVehicle->level,
        ]);

        $suspiciousVehicle->delete();

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "ลบทะเบียน {$plate} ออกจากบัญชีดำเรียบร้อยแล้ว");
    }

    public function toggle(SuspiciousVehicle $suspiciousVehicle)
    {
        $suspiciousVehicle->update(['is_active' => ! $suspiciousVehicle->is_active]);

        $state = $suspiciousVehicle->is_active ? 'เปิดใช้งาน' : 'ระงับ';

        admin_audit('suspicious_vehicle.toggle', $suspiciousVehicle, [
            'license_plate' => $suspiciousVehicle->license_plate,
            'is_active'     => $suspiciousVehicle->is_active,
        ]);

        return back()->with('success', "{$state}ทะเบียน {$suspiciousVehicle->license_plate} เรียบร้อยแล้ว");
    }
}
