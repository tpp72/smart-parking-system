<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Rules\PlausibleLicensePlate;
use App\Services\CheckOutService;
use App\Support\LicensePlateNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * หน้าเช็คสถานะรถสำหรับคนขับ Walk-in ที่ไม่ได้ล็อกอิน (project-plan.md §4.0.2)
 *
 * ใช้ ทะเบียน + จังหวัด + รหัสอ้างอิงที่ได้จากจอทางเข้าลาน
 * ใช้ได้เฉพาะรถที่เข้าแบบ Walk-in — การจองล่วงหน้าไม่ออกรหัส (§4.0.1)
 * รหัสเป็นหลักฐานว่า "คนที่ถืออยู่กับรถจริง" — ถ้าใช้แค่ทะเบียน ใครที่เห็นป้ายก็ตามดูได้ว่ารถจอดที่ไหน
 *
 * แสดงเฉพาะข้อมูลของรถคันนั้น ณ ตอนนี้ · ไม่แสดงชื่อเจ้าของ อีเมล หรือประวัติครั้งก่อน
 */
class PublicTrackController extends Controller
{
    public function show()
    {
        return view('track.index', ['result' => null]);
    }

    public function find(Request $request, CheckOutService $checkOut)
    {
        $reservation = $this->locate($request);

        return $reservation instanceof Reservation ? $this->render($reservation, $request, $checkOut) : $reservation;
    }

    /** กด Check-out — ล็อกยอด ณ ตอนนี้ ให้ชำระภายใน N นาที (§12.6) */
    public function checkout(Request $request, CheckOutService $checkOut)
    {
        $reservation = $this->locate($request);

        if (! $reservation instanceof Reservation) {
            return $reservation;
        }

        $result = $checkOut->requestCheckout($reservation);

        return $this->render($reservation, $request, $checkOut, $result['success'] ? null : $result['error']);
    }

    /** กดชำระ (จำลอง) — ชำระยอดที่ล็อกไว้ แล้วสแกนออกได้ภายใน N นาที (§12.6) */
    public function pay(Request $request, CheckOutService $checkOut)
    {
        $reservation = $this->locate($request);

        if (! $reservation instanceof Reservation) {
            return $reservation;
        }

        $result = $checkOut->payCheckout($reservation);

        return $this->render($reservation, $request, $checkOut, $result['success'] ? null : $result['error']);
    }

    /**
     * ยืนยันตัวด้วย ทะเบียน + จังหวัด + รหัสอ้างอิง — ทุกการกระทำในหน้านี้ตรวจซ้ำทุกครั้ง ไม่จำอะไรไว้ใน session
     *
     * @return Reservation|\Illuminate\Http\RedirectResponse
     */
    private function locate(Request $request)
    {
        $data = $request->validate([
            'plate_number'   => ['required', 'string', 'max:20', new PlausibleLicensePlate],
            'plate_province' => ['required', 'string', Rule::in(config('thai_provinces'))],
            'reference_code' => ['required', 'string', 'size:6'],
        ], [
            'plate_number.required'   => 'กรุณากรอกเลขทะเบียนรถ',
            'plate_province.required' => 'กรุณาเลือกจังหวัด',
            'plate_province.in'       => 'กรุณาเลือกจังหวัดจากรายการ',
            'reference_code.required' => 'กรุณากรอกรหัสอ้างอิง',
            'reference_code.size'     => 'รหัสอ้างอิงมี 6 ตัวอักษร',
        ]);

        $reservation = Reservation::with(['parkingLot:id,name,hourly_rate,address,district,province', 'parkingSlot:id,slot_number', 'parkingLog'])
            ->where('reference_code', strtoupper(trim($data['reference_code'])))
            ->wherePlateMatches($data['plate_number'])
            ->where('plate_province', $data['plate_province'])
            ->where('status', 'checked_in')
            // เฉพาะ Walk-in — การจองล่วงหน้าไม่มีรหัส และเจ้าของบัญชีดูรายการของตัวเองในหน้าหลักได้อยู่แล้ว
            // เงื่อนไขนี้บังคับกฎไว้ในโค้ด ไม่ให้รายการเก่าที่เคยมีรหัสติดมาเปิดดูผ่านหน้านี้ได้
            ->where('is_walk_in', true)
            ->first();

        // ข้อความเดียวกันทุกกรณีที่ไม่พบ — ไม่บอกว่าผิดที่ทะเบียนหรือรหัส เพื่อไม่ให้ใช้หน้านี้ไล่เดา
        if (! $reservation || ! $reservation->parkingLog || $reservation->parkingLog->check_out_time) {
            return redirect()->route('track.show')
                ->withErrors(['reference_code' => 'ไม่พบรถที่กำลังจอดอยู่ตามข้อมูลนี้ — ตรวจเลขทะเบียน จังหวัด และรหัสอ้างอิงอีกครั้ง'])
                ->withInput($request->only('plate_number', 'plate_province'));
        }

        return $reservation;
    }

    private function render(Reservation $reservation, Request $request, CheckOutService $checkOut, ?string $error = null)
    {
        $log = $reservation->parkingLog->fresh();

        return view('track.index', [
            'result' => [
                'reservation' => $reservation,
                'log'         => $log,
                'exit'        => $checkOut->exitState($reservation, $log),
                'plate'       => LicensePlateNormalizer::normalize($reservation->license_plate),
                // ส่งกลับในฟอร์มของปุ่ม Check-out / ชำระ เพื่อยืนยันตัวซ้ำ (ไม่เก็บใน session)
                'credentials' => [
                    'plate_number'   => $request->input('plate_number'),
                    'plate_province' => $request->input('plate_province'),
                    'reference_code' => strtoupper(trim((string) $request->input('reference_code'))),
                ],
                'error'       => $error,
            ],
        ]);
    }
}
