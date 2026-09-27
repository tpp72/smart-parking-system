<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\UserVehicle;
use App\Rules\PlausibleLicensePlate;
use App\Services\CheckOutService;
use App\Support\LicensePlateNormalizer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * หน้าเช็คสถานะรถสำหรับคนขับที่ไม่ได้ล็อกอิน (project-plan.md §4.0.2)
 *
 * ใช้ ทะเบียน + จังหวัด + รหัสอ้างอิงที่ได้จากจอทางเข้าลาน
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
            ->first();

        // ข้อความเดียวกันทุกกรณีที่ไม่พบ — ไม่บอกว่าผิดที่ทะเบียนหรือรหัส เพื่อไม่ให้ใช้หน้านี้ไล่เดา
        if (! $reservation || ! $reservation->parkingLog || $reservation->parkingLog->check_out_time) {
            return back()
                ->withErrors(['reference_code' => 'ไม่พบรถที่กำลังจอดอยู่ตามข้อมูลนี้ — ตรวจเลขทะเบียน จังหวัด และรหัสอ้างอิงอีกครั้ง'])
                ->withInput($request->only('plate_number', 'plate_province'));
        }

        // จำไว้ว่าผู้ใช้คนนี้พิสูจน์แล้วว่าอยู่กับรถคันนี้ — ใช้เป็นหลักฐานตอนผูกทะเบียนกับบัญชี
        $request->session()->put('track_verified', [
            'license_plate'  => $reservation->license_plate,
            'plate_province' => $reservation->plate_province,
        ]);

        return view('track.index', [
            'result' => [
                'reservation' => $reservation,
                'log'         => $reservation->parkingLog,
                'estimate'    => $checkOut->calculate($reservation, $reservation->parkingLog, now()),
                'plate'       => LicensePlateNormalizer::normalize($reservation->license_plate),
                'claimedBy'   => UserVehicle::ownerOf($reservation->license_plate, $reservation->plate_province),
            ],
        ]);
    }

    /** หน้ายืนยันก่อนผูกทะเบียนกับบัญชี (ต้องล็อกอิน — ยังไม่ล็อกอินจะถูกพาไปหน้าเข้าสู่ระบบแล้วกลับมาที่นี่) */
    public function claimForm(Request $request)
    {
        $verified = $request->session()->get('track_verified');

        if (! $verified) {
            return redirect()->route('track.show')
                ->withErrors(['reference_code' => 'กรุณาเช็คสถานะรถด้วยรหัสอ้างอิงก่อน จึงจะผูกรถกับบัญชีได้']);
        }

        return view('track.claim', [
            'plate'    => $verified['license_plate'],
            'province' => $verified['plate_province'],
            'owner'    => UserVehicle::ownerOf($verified['license_plate'], $verified['plate_province']),
        ]);
    }

    /** ผูกทะเบียนกับบัญชี — ทำได้เฉพาะทะเบียนที่เพิ่งพิสูจน์ด้วยรหัสอ้างอิงในรอบนี้ */
    public function claim(Request $request)
    {
        $verified = $request->session()->get('track_verified');

        if (! $verified) {
            return redirect()->route('track.show')
                ->withErrors(['reference_code' => 'กรุณาเช็คสถานะรถด้วยรหัสอ้างอิงก่อน จึงจะผูกรถกับบัญชีได้']);
        }

        $user = $request->user();
        $owner = UserVehicle::ownerOf($verified['license_plate'], $verified['plate_province']);

        if ($owner && $owner->id !== $user->id) {
            return back()->withErrors(['plate' => 'ทะเบียนนี้ถูกผูกกับบัญชีอื่นแล้ว หากเป็นรถของคุณ กรุณาติดต่อผู้ดูแลระบบ']);
        }

        if (! $owner) {
            $vehicle = UserVehicle::create([
                'user_id'        => $user->id,
                'license_plate'  => $verified['license_plate'],
                'plate_province' => $verified['plate_province'],
            ]);

            audit_log('user_vehicle.link', $vehicle, [
                'license_plate'  => $vehicle->license_plate,
                'plate_province' => $vehicle->plate_province,
            ]);
        }

        // หลักฐานใช้ได้ครั้งเดียว — ต้องเช็คสถานะด้วยรหัสใหม่ถ้าจะผูกคันอื่น
        $request->session()->forget('track_verified');

        return redirect()->route('profile.edit')
            ->with('success', "ผูกทะเบียน {$verified['license_plate']} {$verified['plate_province']} กับบัญชีของคุณแล้ว — ครั้งต่อไปที่รถเข้าลานโดยไม่ได้จอง ระบบจะแจ้งเตือนคุณและแสดงรถในหน้าหลัก");
    }

    /** ยกเลิกการผูกทะเบียน */
    public function unclaim(Request $request, UserVehicle $vehicle)
    {
        abort_unless($vehicle->user_id === $request->user()->id, 403);

        audit_log('user_vehicle.unlink', $vehicle, [
            'license_plate'  => $vehicle->license_plate,
            'plate_province' => $vehicle->plate_province,
        ]);

        $vehicle->delete();

        return back()->with('success', 'นำรถออกจากบัญชีแล้ว');
    }
}
