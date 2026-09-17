<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin จัดการช่องจอดของลาน Admin — สถานะช่อง (available / reserved / occupied) ระบบเป็นผู้จัดการเท่านั้น
 */
class ParkingSlotController extends Controller
{
    /** Admin จัดการช่องจอดได้เฉพาะของลานที่ยังไม่มีเจ้าของ */
    private function assertLotUnowned(int $lotId): void
    {
        abort_unless(
            ParkingLot::where('id', $lotId)->whereNull('owner_id')->exists(),
            403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้'
        );
    }

    /** แผนผังช่องจอดทีละลาน — ทุกช่องของลานที่เลือก + การจอง/รถที่ใช้ช่องนั้นอยู่ */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = in_array($request->query('status'), ['available', 'reserved', 'occupied'], true) ? $request->query('status') : null;

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);
        $lot = $lots->firstWhere('id', (int) $request->query('lot_id')) ?? $lots->first();

        $slots = $lot
            ? ParkingSlot::where('parking_lot_id', $lot->id)->get(['id', 'parking_lot_id', 'slot_number', 'status'])
                ->sortBy('slot_number', SORT_NATURAL)->values()
            : collect();

        // ช่องที่ถูก Lock (ยืนยันแล้ว) หรือมีรถจอด (Check-in แล้ว) → การจองที่ใช้ช่องนั้น
        $occupants = $lot
            ? Reservation::with(['user:id,name', 'parkingLog:id,reservation_id,check_in_time'])
                ->where('parking_lot_id', $lot->id)
                ->whereIn('status', ['confirmed', 'checked_in'])
                ->whereNotNull('parking_slot_id')
                ->get()
                ->keyBy('parking_slot_id')
            : collect();

        return view('parking.slots.index', compact('lots', 'lot', 'slots', 'occupants', 'q', 'status') + ['scope' => 'admin']);
    }

    public function create()
    {
        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);

        return view('parking.slots.form', ['slot' => null, 'lots' => $lots, 'scope' => 'admin', 'selectedLotId' => (int) request()->query('lot_id')]);
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
        ]);

        $this->assertLotUnowned((int) $data['parking_lot_id']);
        $slot = ParkingSlot::create($data + ['status' => 'available']);

        audit_log('parking_slot.create', $slot, ['parking_lot_id' => $slot->parking_lot_id, 'slot_number' => $slot->slot_number]);

        return redirect()->route('admin.parking-slots.index', ['lot_id' => $slot->parking_lot_id])
            ->with('success', "เพิ่มช่องจอด {$slot->slot_number} เรียบร้อยแล้ว");
    }

    public function edit(ParkingSlot $parking_slot)
    {
        $this->assertLotUnowned($parking_slot->parking_lot_id);

        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);

        return view('parking.slots.form', ['slot' => $parking_slot, 'lots' => $lots, 'scope' => 'admin', 'selectedLotId' => $parking_slot->parking_lot_id]);
    }

    /** แก้ไขได้เฉพาะเลขช่อง / ลาน — ช่องที่ถูกจองหรือมีรถจอดอยู่ย้ายลานไม่ได้ */
    public function update(Request $request, ParkingSlot $parking_slot)
    {
        $this->assertLotUnowned($parking_slot->parking_lot_id);

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
        ]);

        $this->assertLotUnowned((int) $data['parking_lot_id']);

        if ((int) $data['parking_lot_id'] !== $parking_slot->parking_lot_id && $parking_slot->status !== 'available') {
            return back()->withErrors(['parking_lot_id' => 'ย้ายช่องจอดที่ถูกจองหรือมีรถจอดอยู่ไปลานอื่นไม่ได้'])->withInput();
        }

        $before = $parking_slot->only(array_keys($data));
        $parking_slot->update($data);

        audit_log('parking_slot.update', $parking_slot, ['changes' => audit_changes($before, $parking_slot)]);

        return redirect()->route('admin.parking-slots.index', ['lot_id' => $parking_slot->parking_lot_id])
            ->with('success', "อัปเดตช่องจอด {$parking_slot->slot_number} เรียบร้อยแล้ว");
    }

    public function destroy(ParkingSlot $parking_slot)
    {
        $this->assertLotUnowned($parking_slot->parking_lot_id);

        $error = DB::transaction(function () use ($parking_slot) {
            $slot = ParkingSlot::whereKey($parking_slot->id)->lockForUpdate()->first();

            if ($slot->status === 'occupied') {
                return 'ไม่สามารถลบช่องจอดที่มีรถจอดอยู่';
            }
            if ($slot->status === 'reserved') {
                return 'ไม่สามารถลบช่องจอดที่ถูกล็อกให้การจองที่ยืนยันแล้ว';
            }

            $slot->delete();
            return null;
        });

        if ($error) {
            return back()->withErrors(['error' => $error]);
        }

        audit_log('parking_slot.delete', $parking_slot, ['parking_lot_id' => $parking_slot->parking_lot_id, 'slot_number' => $parking_slot->slot_number]);

        return redirect()->route('admin.parking-slots.index', ['lot_id' => $parking_slot->parking_lot_id])
            ->with('success', "ลบช่องจอด {$parking_slot->slot_number} เรียบร้อยแล้ว");
    }

    // ===== Bulk =====

    public function bulkCreate()
    {
        $lots = ParkingLot::unowned()->orderBy('name')->get(['id', 'name']);

        // เลขช่องที่มีอยู่แล้วของแต่ละลาน — ให้หน้าตัวอย่างเตือนเลขซ้ำก่อนกดบันทึก (bulkStore ยังตรวจซ้ำอีกชั้น)
        $existing = ParkingSlot::whereIn('parking_lot_id', $lots->pluck('id'))
            ->get(['parking_lot_id', 'slot_number'])
            ->groupBy('parking_lot_id')
            ->map(fn ($slots) => $slots->pluck('slot_number')->values());

        return view('parking.slots.bulk', ['lots' => $lots, 'existing' => $existing, 'scope' => 'admin', 'selectedLotId' => (int) request()->query('lot_id')]);
    }

    public function bulkStore(Request $request)
    {
        $mode = $request->input('mode', 'range');

        $baseRules = [
            'parking_lot_id' => ['required', 'exists:parking_lots,id'],
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

        $this->assertLotUnowned((int) $data['parking_lot_id']);

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
                    'status' => 'available',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            ParkingSlot::insert($rows);
        });

        audit_log('parking_slot.bulk_create', ParkingLot::find($data['parking_lot_id']), [
            'count'        => count($slotNumbers),
            'slot_numbers' => array_slice($slotNumbers, 0, 50),
        ]);

        return redirect()->route('admin.parking-slots.index', ['lot_id' => $data['parking_lot_id']])
            ->with('success', 'เพิ่มช่องจอดแบบหลายรายการเรียบร้อยแล้ว (' . count($slotNumbers) . ' ช่อง)');
    }
}
