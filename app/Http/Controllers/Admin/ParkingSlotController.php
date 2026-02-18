<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParkingSlotController extends Controller
{
    private array $statuses = ['available', 'reserved', 'occupied'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $lotId = $request->query('lot_id');
        $status = $request->query('status');

        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);

        $slots = ParkingSlot::query()
            ->with(['parkingLot:id,name'])
            ->when($q !== '', fn($query) => $query->where('slot_number', 'like', "%{$q}%"))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($status, fn($query) => $query->where('status', $status))
            ->orderBy('parking_lot_id')
            ->orderBy('slot_number')
            ->paginate(15)
            ->withQueryString();

        return view('admin.parking-slots.index', compact('slots', 'lots', 'q', 'lotId', 'status'));
    }

    public function create()
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $statuses = $this->statuses;

        return view('admin.parking-slots.create', compact('lots', 'statuses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
            'slot_number'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('parking_slots', 'slot_number')
                    ->where(fn($q) => $q->where('parking_lot_id', $request->parking_lot_id)),
            ],
            'status'         => ['required', Rule::in($this->statuses)],
        ]);

        ParkingSlot::create($data);

        return redirect()->route('admin.parking-slots.index')
            ->with('success', 'เพิ่มช่องจอดเรียบร้อยแล้ว');
    }

    public function edit(ParkingSlot $parking_slot)
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $statuses = $this->statuses;

        return view('admin.parking-slots.edit', [
            'slot' => $parking_slot,
            'lots' => $lots,
            'statuses' => $statuses,
        ]);
    }

    public function update(Request $request, ParkingSlot $parking_slot)
    {
        $data = $request->validate([
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
            'slot_number'    => [
                'required',
                'string',
                'max:255',
                Rule::unique('parking_slots', 'slot_number')
                    ->where(fn($q) => $q->where('parking_lot_id', $request->parking_lot_id))
                    ->ignore($parking_slot->id),
            ],
            'status'         => ['required', Rule::in($this->statuses)],
        ]);

        $parking_slot->update($data);

        return redirect()->route('admin.parking-slots.index')
            ->with('success', 'อัปเดตช่องจอดเรียบร้อยแล้ว');
    }

    public function destroy(ParkingSlot $parking_slot)
    {
        $parking_slot->delete();

        return redirect()->route('admin.parking-slots.index')
            ->with('success', 'ลบช่องจอดเรียบร้อยแล้ว');
    }

    // ===== Bulk =====

    public function bulkCreate()
    {
        $lots = ParkingLot::query()->orderBy('name')->get(['id', 'name']);
        $statuses = $this->statuses;

        return view('admin.parking-slots.bulk', compact('lots', 'statuses'));
    }

    public function bulkStore(Request $request)
    {
        $mode = $request->input('mode', 'range');

        $baseRules = [
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
            'status'         => ['required', Rule::in($this->statuses)],
            'mode'           => ['required', Rule::in(['range', 'list'])],
        ];

        if ($mode === 'range') {
            $data = $request->validate($baseRules + [
                'prefix' => ['nullable', 'string', 'max:50'],
                'start'  => ['required', 'integer', 'min:0'],
                'end'    => ['required', 'integer', 'gte:start'],
                'pad'    => ['nullable', 'integer', 'min:0', 'max:8'],
            ]);

            $prefix = (string) ($data['prefix'] ?? '');
            $start = (int) $data['start'];
            $end = (int) $data['end'];
            $pad = (int) ($data['pad'] ?? 0);

            $slotNumbers = [];
            for ($i = $start; $i <= $end; $i++) {
                $num = $pad > 0 ? str_pad((string)$i, $pad, '0', STR_PAD_LEFT) : (string)$i;
                $slotNumbers[] = $prefix . $num;
            }
        } else {
            $data = $request->validate($baseRules + [
                'slot_numbers' => ['required', 'string'],
            ]);

            $raw = preg_split("/\r\n|\n|\r|,/", $data['slot_numbers']);
            $slotNumbers = collect($raw)
                ->map(fn($x) => trim((string)$x))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (count($slotNumbers) === 0) {
            return back()->withErrors(['slot_numbers' => 'ไม่มีรายการช่องจอด'])->withInput();
        }

        // กันสร้างซ้ำใน lot เดียว
        $existing = ParkingSlot::query()
            ->where('parking_lot_id', $data['parking_lot_id'])
            ->whereIn('slot_number', $slotNumbers)
            ->pluck('slot_number')
            ->all();

        if (!empty($existing)) {
            return back()->withErrors([
                'slot_numbers' => 'มีช่องซ้ำอยู่แล้วในลานนี้: ' . implode(', ', array_slice($existing, 0, 15)) . (count($existing) > 15 ? ' ...' : ''),
            ])->withInput();
        }

        DB::transaction(function () use ($data, $slotNumbers) {
            $rows = [];
            $now = now()->startOfSecond(); // timestamps(0)

            foreach ($slotNumbers as $sn) {
                $rows[] = [
                    'parking_lot_id' => $data['parking_lot_id'],
                    'slot_number' => $sn,
                    'status' => $data['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ParkingSlot::insert($rows);
        });

        return redirect()->route('admin.parking-slots.index')
            ->with('success', 'เพิ่มช่องจอดแบบหลายรายการเรียบร้อยแล้ว (' . count($slotNumbers) . ' ช่อง)');
    }
}
