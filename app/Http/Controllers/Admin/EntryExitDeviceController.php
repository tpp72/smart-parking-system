<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntryExitDevice;
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntryExitDeviceController extends Controller
{
    private array $types = ['gate', 'camera', 'scanner'];
    private array $statuses = ['online', 'offline'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $lotId = $request->query('lot_id');
        $type = $request->query('device_type');
        $status = $request->query('status');

        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);

        $devices = EntryExitDevice::query()
            ->with(['parkingLot:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where('location', 'like', "%{$q}%");
            })
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($type, fn($query) => $query->where('device_type', $type))
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.devices.index', compact('devices', 'lots', 'q', 'lotId', 'type', 'status'));
    }

    public function create()
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $types = $this->types;
        $statuses = $this->statuses;

        return view('admin.devices.create', compact('lots', 'types', 'statuses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
            'device_type'    => ['required', Rule::in($this->types)],
            'location'       => ['required', 'string', 'max:255'],
            'status'         => ['required', Rule::in($this->statuses)],
        ]);

        EntryExitDevice::create($data);

        return redirect()->route('admin.devices.index')
            ->with('success', 'เพิ่มอุปกรณ์เรียบร้อยแล้ว');
    }

    public function edit(EntryExitDevice $device)
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $types = $this->types;
        $statuses = $this->statuses;

        return view('admin.devices.edit', compact('device', 'lots', 'types', 'statuses'));
    }

    public function update(Request $request, EntryExitDevice $device)
    {
        $data = $request->validate([
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
            'device_type'    => ['required', Rule::in($this->types)],
            'location'       => ['required', 'string', 'max:255'],
            'status'         => ['required', Rule::in($this->statuses)],
        ]);

        $device->update($data);

        return redirect()->route('admin.devices.index')
            ->with('success', 'อัปเดตอุปกรณ์เรียบร้อยแล้ว');
    }

    public function destroy(EntryExitDevice $device)
    {
        $device->delete(); // มี cascadeOnDelete กับ parking_lots อยู่แล้วในฝั่ง lot
        return redirect()->route('admin.devices.index')
            ->with('success', 'ลบอุปกรณ์เรียบร้อยแล้ว');
    }
}
