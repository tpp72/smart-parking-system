<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Services\CheckOutService;
use App\Services\ReservationService;
use App\Support\StatusCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    /** รายการการจองของ user — แท็บ "กำลังดำเนินการ" (รอยืนยัน/ยืนยันแล้ว/กำลังจอด) และ "จบแล้ว" (เสร็จสิ้น/ยกเลิก/หมดอายุ) */
    public function index(Request $request, CheckOutService $checkOut)
    {
        $tab = $request->query('tab') === 'done' ? 'done' : 'active';
        $mine = fn () => Reservation::where('user_id', Auth::id());

        $reservations = $mine()
            ->with(['parkingLot:id,name,hourly_rate', 'parkingSlot:id,slot_number', 'depositPayment', 'parkingLog.payment'])
            ->when($tab === 'active',
                fn ($q) => $q->whereIn('status', Reservation::ACTIVE_STATUSES)->orderBy('reserve_start'),
                fn ($q) => $q->whereNotIn('status', Reservation::ACTIVE_STATUSES)->orderByDesc('reserve_start'))
            ->paginate(10)
            ->withQueryString();

        $counts = [
            'active' => $mine()->whereIn('status', Reservation::ACTIVE_STATUSES)->count(),
            'done'   => $mine()->whereNotIn('status', Reservation::ACTIVE_STATUSES)->count(),
        ];

        // รถที่จอดอยู่: ประมาณค่าจอด ณ ตอนนี้ (ยอดจริงคิดตอน Check-out)
        $estimates = $reservations->getCollection()
            ->filter(fn (Reservation $r) => $r->status === 'checked_in' && $r->parkingLog && ! $r->parkingLog->check_out_time)
            ->mapWithKeys(fn (Reservation $r) => [$r->id => $checkOut->calculate($r, $r->parkingLog, now())]);

        return view('user.reservations.index', compact('reservations', 'tab', 'counts', 'estimates'));
    }

    /** ฟอร์มสร้างการจอง — User เลือกได้เฉพาะลาน (ลานที่เปิดรับจองและยังมีช่องว่าง) */
    public function create(Request $request)
    {
        $lots = ParkingLot::reservable()->withAvailableSlot()->orderBy('name')->get(['id', 'name', 'hourly_rate']);

        // เลือกลานมาจากการ์ด "ลานที่ว่างแนะนำ" (?lot_id=) — ใช้ได้เฉพาะลานที่อยู่ในรายการที่จองได้
        $selectedLotId = $lots->firstWhere('id', (int) $request->query('lot_id'))?->id;

        return view('user.reservations.create', compact('lots', 'selectedLotId'));
    }

    /** บันทึกการจอง + สร้าง Deposit Payment — ช่องจอดถูกจัดสรรโดยระบบเมื่อยืนยันรับเงินมัดจำ */
    public function store(Request $request)
    {
        $data = $request->validate([
            'plate_number'    => ['required', 'string', 'max:15'],
            'plate_province'  => ['required', 'string', Rule::in(config('thai_provinces'))],
            'brand'           => ['required', 'string', 'max:60'],
            'color'           => ['required', 'string', Rule::in(config('car_colors'))],
            'parking_lot_id'  => ['required', 'exists:parking_lots,id'],
            'reserve_start'   => ['required', 'date', 'after:now', 'before:' . now()->addDay()->toDateTimeString()],
        ], [
            'plate_number.required'    => 'กรุณากรอกเลขทะเบียนรถ',
            'plate_number.max'         => 'เลขทะเบียนต้องไม่เกิน 15 ตัวอักษร',
            'plate_province.required'  => 'กรุณาเลือกจังหวัด',
            'plate_province.in'        => 'กรุณาเลือกจังหวัดจากรายการ',
            'brand.required'           => 'กรุณากรอกยี่ห้อรถ',
            'brand.max'                => 'ยี่ห้อรถต้องไม่เกิน 60 ตัวอักษร',
            'color.required'           => 'กรุณาเลือกสีรถ',
            'color.in'                 => 'กรุณาเลือกสีจากรายการ',
            'parking_lot_id.required'  => 'กรุณาเลือกลานจอด',
            'parking_lot_id.exists'    => 'ไม่พบลานจอดที่เลือกในระบบ',
            'reserve_start.required'   => 'กรุณาระบุวันและเวลาที่ต้องการจอง',
            'reserve_start.date'       => 'รูปแบบวันที่/เวลาไม่ถูกต้อง',
            'reserve_start.after'      => 'เวลาจองต้องเป็นเวลาในอนาคต',
            'reserve_start.before'     => 'จองล่วงหน้าได้ไม่เกิน 1 วัน (24 ชั่วโมง)',
        ]);

        $plate    = strtoupper(trim($data['plate_number']));
        $province = $data['plate_province'];

        // ป้องกัน: ทะเบียน + จังหวัดนี้มีการจองที่ยัง active อยู่แล้ว
        if (Reservation::where('license_plate', $plate)
            ->where('plate_province', $province)
            ->whereIn('status', Reservation::ACTIVE_STATUSES)
            ->exists()
        ) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่ กรุณารอให้เสร็จสิ้นก่อน'])
                ->withInput();
        }

        // ป้องกัน: รถคันนี้กำลังจอดอยู่ในระบบ
        $isParked = ParkingLog::whereNull('check_out_time')
            ->where('license_plate', $plate)
            ->where('plate_province', $province)
            ->exists();

        if ($isParked) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้กำลังจอดอยู่แล้ว ไม่สามารถจองได้ในขณะนี้'])
                ->withInput();
        }

        $lot = ParkingLot::findOrFail($data['parking_lot_id']);

        if (!$lot->reservations_enabled) {
            return back()
                ->withErrors(['parking_lot_id' => 'ลานจอดนี้ไม่รับจองล่วงหน้าในขณะนี้'])
                ->withInput();
        }

        if (!$lot->slots()->where('status', 'available')->exists()) {
            return back()
                ->withErrors(['parking_lot_id' => 'ลานจอดนี้เต็ม ไม่สามารถจองได้ในขณะนี้'])
                ->withInput();
        }

        try {
            $reservation = $this->reservations->create(Auth::user(), $lot, [
                'license_plate'  => $plate,
                'plate_province' => $province,
                'brand'          => $data['brand'],
                'color'          => $data['color'],
                'reserve_start'  => $data['reserve_start'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()
                ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่ กรุณารอให้เสร็จสิ้นก่อน'])
                ->withInput();
        }

        return redirect()->route('user.reservations.index')
            ->with('success', sprintf(
                'ส่งคำขอจองสำเร็จ! กรุณาชำระเงินมัดจำ ฿%s และรอเจ้าหน้าที่ยืนยันรับเงิน การจองจึงจะได้รับการยืนยัน',
                number_format((float) $reservation->deposit_amount, 2)
            ));
    }

    /** ฟอร์มแก้ไขข้อมูลรถ (ได้เฉพาะก่อน Check-In) */
    public function edit(Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => 'แก้ไขข้อมูลรถได้เฉพาะก่อน Check-in — การจองนี้' . StatusCatalog::label('reservation', $reservation->status, 'user')]);
        }

        $plateNumber   = $reservation->license_plate;
        $plateProvince = $reservation->plate_province;

        return view('user.reservations.edit', compact('reservation', 'plateNumber', 'plateProvince'));
    }

    /** บันทึกการแก้ไขข้อมูลรถ — แก้ได้เฉพาะ ทะเบียน / จังหวัด / ยี่ห้อ / สี */
    public function update(Request $request, Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => 'แก้ไขข้อมูลรถได้เฉพาะก่อน Check-in — การจองนี้' . StatusCatalog::label('reservation', $reservation->status, 'user')]);
        }

        $data = $request->validate([
            'plate_number'   => ['required', 'string', 'max:15'],
            'plate_province' => ['required', 'string', Rule::in(config('thai_provinces'))],
            'brand'          => ['required', 'string', 'max:60'],
            'color'          => ['required', 'string', Rule::in(config('car_colors'))],
        ], [
            'plate_number.required'   => 'กรุณากรอกเลขทะเบียนรถ',
            'plate_number.max'        => 'เลขทะเบียนต้องไม่เกิน 15 ตัวอักษร',
            'plate_province.required' => 'กรุณาเลือกจังหวัด',
            'plate_province.in'       => 'กรุณาเลือกจังหวัดจากรายการ',
            'brand.required'          => 'กรุณากรอกยี่ห้อรถ',
            'brand.max'               => 'ยี่ห้อรถต้องไม่เกิน 60 ตัวอักษร',
            'color.required'          => 'กรุณาเลือกสีรถ',
            'color.in'                => 'กรุณาเลือกสีจากรายการ',
        ]);

        $plate    = strtoupper(trim($data['plate_number']));
        $province = $data['plate_province'];
        $brand    = $data['brand'];
        $color    = $data['color'];

        $plateChanged = $plate !== $reservation->license_plate || $province !== $reservation->plate_province;

        // ถ้าไม่มีการเปลี่ยนแปลงเลย ข้ามไปเลย
        if (!$plateChanged
            && $brand === $reservation->brand
            && $color === $reservation->color
        ) {
            return redirect()->route('user.reservations.index')
                ->with('success', 'ไม่มีการเปลี่ยนแปลงข้อมูล');
        }

        $duplicateError = back()
            ->withErrors(['license_plate' => 'ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่'])
            ->withInput();

        // ตรวจสอบว่าทะเบียน + จังหวัดใหม่ไม่มีการจอง active อื่น (เฉพาะกรณีเปลี่ยนทะเบียน)
        if ($plateChanged
            && Reservation::where('license_plate', $plate)
                ->where('plate_province', $province)
                ->where('id', '!=', $reservation->id)
                ->whereIn('status', Reservation::ACTIVE_STATUSES)
                ->exists()
        ) {
            return $duplicateError;
        }

        try {
            $reservation->update([
                'license_plate'  => $plate,
                'plate_province' => $province,
                'brand'          => $brand,
                'color'          => $color,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $duplicateError;
        }

        ReservationLog::create([
            'reservation_id' => $reservation->id,
            'old_status'     => $reservation->status,
            'new_status'     => $reservation->status,
            'changed_by'     => Auth::id(),
            'note'           => "ผู้ใช้แก้ไขข้อมูลรถเป็น {$plate} {$province}" . ($brand ? " ยี่ห้อ {$brand}" : '') . ($color ? " สี {$color}" : ''),
        ]);

        audit_log('reservation.update_vehicle', $reservation, [
            'license_plate'  => $plate,
            'plate_province' => $province,
            'brand'          => $brand,
            'color'          => $color,
        ]);

        return redirect()->route('user.reservations.index')
            ->with('success', "อัปเดตข้อมูลรถเรียบร้อยแล้ว");
    }

    /** ยกเลิกการจองที่เป็นของตัวเองก่อน Check-in — ไม่คืนเงินมัดจำ */
    public function cancel(Reservation $reservation)
    {
        abort_unless($reservation->user_id === Auth::id(), 403);

        $result = $this->reservations->cancel($reservation, Auth::user(), 'ผู้ใช้ยกเลิกการจอง');

        if (!$result['success']) {
            return redirect()->route('user.reservations.index')
                ->withErrors(['error' => $result['error']]);
        }

        return redirect()->route('user.reservations.index')
            ->with('success', "ยกเลิกการจอง #{$reservation->id} เรียบร้อยแล้ว"
                . ($result['deposit_forfeited'] ? ' — ไม่คืนเงินมัดจำ' : ''));
    }
}
