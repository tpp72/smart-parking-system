<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SuspiciousVehicle;
use App\Rules\PlausibleLicensePlate;
use App\Support\LicensePlateNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SuspiciousVehicleController extends Controller
{
    /**
     * กันทะเบียนซ้ำในจังหวัดเดียวกัน โดยเทียบแบบถอดตัวคั่น
     * ใช้แทน Rule::unique เพราะกฎนั้นเทียบข้อความดิบ — "กข 1234" กับ "กข-1234" จะหลุดเป็นคนละรายการ
     */
    private function notAlreadyListed(Request $request, ?int $ignoreId = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $ignoreId) {
            $exists = SuspiciousVehicle::wherePlateMatches((string) $value)
                ->where('plate_province', (string) $request->input('plate_province'))
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                $fail('ทะเบียนนี้อยู่ในบัญชีดำของจังหวัดนี้อยู่แล้ว');
            }
        };
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $entries = SuspiciousVehicle::with('addedBy:id,name')
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->wherePlateLike($q)
                    ->orWhere('plate_province', 'ilike', "%{$q}%")
                    ->orWhere('reason', 'ilike', "%{$q}%");
            }))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.suspicious-vehicles.index', compact('entries', 'q'));
    }

    public function create()
    {
        return view('admin.suspicious-vehicles.create');
    }

    public function store(Request $request)
    {
        // จัดรูปแบบก่อน validate เพื่อให้กฎ unique เทียบกับค่าเดียวกับที่จะบันทึกจริง
        $request->merge(['license_plate' => LicensePlateNormalizer::normalize((string) $request->input('license_plate'))]);

        $data = $request->validate([
            // Blacklist ระบุรถด้วย ทะเบียน + จังหวัด
            'license_plate'  => ['required', 'string', 'max:20', new PlausibleLicensePlate, $this->notAlreadyListed($request)],
            'plate_province' => ['required', 'string', Rule::in(config('thai_provinces'))],
            'reason'         => ['nullable', 'string', 'max:500'],
            'level'          => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active'      => ['boolean'],
        ]);

        $data['added_by'] = Auth::id();
        $data['is_active'] = $request->boolean('is_active', true);

        $entry = SuspiciousVehicle::create($data);

        audit_log('suspicious_vehicle.create', $entry, [
            'license_plate'  => $entry->license_plate,
            'plate_province' => $entry->plate_province,
            'level'          => $entry->level,
        ]);

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "เพิ่มทะเบียน {$entry->license_plate} {$entry->plate_province} ในบัญชีดำเรียบร้อยแล้ว");
    }

    public function edit(SuspiciousVehicle $suspiciousVehicle)
    {
        return view('admin.suspicious-vehicles.edit', compact('suspiciousVehicle'));
    }

    public function update(Request $request, SuspiciousVehicle $suspiciousVehicle)
    {
        $request->merge(['license_plate' => LicensePlateNormalizer::normalize((string) $request->input('license_plate'))]);

        $data = $request->validate([
            'license_plate'  => ['required', 'string', 'max:20', new PlausibleLicensePlate, $this->notAlreadyListed($request, $suspiciousVehicle->id)],
            'plate_province' => ['required', 'string', Rule::in(config('thai_provinces'))],
            'reason'         => ['nullable', 'string', 'max:500'],
            'level'          => ['required', Rule::in(['low', 'medium', 'high'])],
            'is_active'      => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', false);

        $suspiciousVehicle->update($data);

        audit_log('suspicious_vehicle.update', $suspiciousVehicle, [
            'license_plate'  => $suspiciousVehicle->license_plate,
            'plate_province' => $suspiciousVehicle->plate_province,
            'level'          => $suspiciousVehicle->level,
        ]);

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "อัปเดตทะเบียน {$suspiciousVehicle->license_plate} เรียบร้อยแล้ว");
    }

    public function destroy(SuspiciousVehicle $suspiciousVehicle)
    {
        $plate = $suspiciousVehicle->license_plate;

        audit_log('suspicious_vehicle.delete', $suspiciousVehicle, [
            'license_plate'  => $plate,
            'plate_province' => $suspiciousVehicle->plate_province,
            'level'          => $suspiciousVehicle->level,
        ]);

        $suspiciousVehicle->delete();

        return redirect()->route('admin.suspicious-vehicles.index')
            ->with('success', "ลบทะเบียน {$plate} ออกจากบัญชีดำเรียบร้อยแล้ว");
    }

    public function toggle(SuspiciousVehicle $suspiciousVehicle)
    {
        $suspiciousVehicle->update(['is_active' => ! $suspiciousVehicle->is_active]);

        $state = $suspiciousVehicle->is_active ? 'เปิดใช้งาน' : 'ระงับ';

        audit_log('suspicious_vehicle.toggle', $suspiciousVehicle, [
            'license_plate' => $suspiciousVehicle->license_plate,
            'is_active'     => $suspiciousVehicle->is_active,
        ]);

        return back()->with('success', "{$state}ทะเบียน {$suspiciousVehicle->license_plate} เรียบร้อยแล้ว");
    }
}
