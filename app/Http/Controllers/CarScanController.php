<?php

namespace App\Http\Controllers;

use App\Models\LicensePlateScan;
use App\Models\ParkingLot;
use App\Services\AutoCheckInService;
use App\Services\CarScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CarScanController extends Controller
{
    public function __construct(
        private CarScanService     $scanService,
        private AutoCheckInService $autoCheckIn,
    ) {}

    /**
     * การอัปโหลดภาพเป็นการจำลองกล้อง Hardware ของลาน — ไม่ขึ้นกับผู้อัปโหลด
     * ทุก Role เลือกลาน (ตำแหน่งกล้อง) ได้ทุกลาน
     */
    private function scannableLots()
    {
        return ParkingLot::query();
    }

    /** ประวัติการสแกนแสดงตามขอบเขตสิทธิ์ (admin: ลานของ Admin, owner: ลานของตัวเอง) */
    private function historyLots()
    {
        return Auth::user()->role === 'owner'
            ? ParkingLot::ownedBy(Auth::id())
            : ParkingLot::unowned();
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan   OR   /owner/scan   OR   /user/scan
     ─────────────────────────────────────────────────────────────*/
    public function create()
    {
        $lots = $this->scannableLots()->orderBy('name')->get(['id', 'name']);

        return view('scan.index', compact('lots'));
    }

    /* ─────────────────────────────────────────────────────────────
     | POST /admin/scan   OR   /owner/scan   OR   /user/scan
     ─────────────────────────────────────────────────────────────*/
    public function store(Request $request)
    {
        $request->validate([
            'car_image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
            'parking_lot_id' => ['required', 'integer', 'exists:parking_lots,id'],
        ], [
            'car_image.required'      => 'กรุณาเลือกรูปภาพรถก่อน',
            'car_image.image'         => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'car_image.mimes'         => 'รองรับเฉพาะ JPG และ PNG',
            'car_image.max'           => 'ขนาดไฟล์ต้องไม่เกิน 5 MB',
            'parking_lot_id.required' => 'กรุณาเลือกลานจอด (จำลองตำแหน่งกล้อง)',
            'parking_lot_id.exists'   => 'ไม่พบลานจอดที่เลือก',
        ]);

        try {
            $scan = $this->scanService->scanAndSave(
                $request->file('car_image'),
                Auth::id(),
                (int) $request->input('parking_lot_id')
            );
        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['car_image' => 'AI ไม่สามารถวิเคราะห์รูปภาพได้: ' . $e->getMessage()])
                ->withInput();
        }

        // ─── AI ไม่ผ่านเกณฑ์ → บันทึกผล + แจ้ง Owner/Admin · ไม่ Matching / ไม่ Auto Check-in ─
        if (!$scan->passed()) {
            $this->scanService->alertStaff($scan);

            return redirect()->back()->with([
                'scan_result'   => $scan->id,
                'scan_check_in' => [
                    'success'        => false,
                    'outcome'        => 'rejected',
                    'message'        => $this->rejectionMessage($scan),
                    'slot'           => null,
                    'staff_notified' => true,
                ],
            ]);
        }

        // ─── Matching → Auto Check-in / Walk-in ─────────────────────
        $outcome = $this->autoCheckIn->handle($scan);

        // ─── ลานเต็ม → ไม่บันทึกผล Scan (ยกเว้นเหตุการณ์ Blacklist) ────
        if ($outcome['outcome'] === AutoCheckInService::OUTCOME_LOT_FULL) {
            $lotFull = [
                'message'        => $outcome['message'],
                'license_plate'  => $scan->license_plate,
                'plate_province' => $scan->plate_province,
                'is_suspicious'  => $scan->is_suspicious,
            ];

            $this->scanService->discardForFullLot($scan);

            return redirect()->back()->with('scan_lot_full', $lotFull);
        }

        $this->scanService->alertStaff($scan);

        return redirect()->back()->with([
            'scan_result'         => $scan->id,
            'scan_reservation_id' => $outcome['reservation']?->id,
            'scan_check_in'       => [
                'success'        => $outcome['success'],
                'outcome'        => $outcome['outcome'],
                'message'        => $outcome['message'],
                'slot'           => $outcome['slot'],
                'staff_notified' => $outcome['staff_notified'],
            ],
        ]);
    }

    /** ข้อความเมื่อผล AI ไม่ผ่านเกณฑ์ */
    private function rejectionMessage(LicensePlateScan $scan): string
    {
        if ($scan->result === LicensePlateScan::RESULT_UNREADABLE) {
            return 'AI อ่านทะเบียนหรือจังหวัดไม่ได้ — ไม่สามารถเช็คอินอัตโนมัติจากผลนี้ได้ (แจ้ง Owner และ Admin แล้ว)';
        }

        return sprintf(
            'ความแม่นยำของ AI %s ไม่เกินเกณฑ์ %s%% — ไม่สามารถเช็คอินอัตโนมัติจากผลนี้ได้ (แจ้ง Owner และ Admin แล้ว)',
            $scan->confidence !== null ? number_format($scan->confidence, 1) . '%' : 'ไม่ทราบค่า',
            config('carscan.accuracy_threshold', 85)
        );
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan/history   OR   /owner/scan/history
     ─────────────────────────────────────────────────────────────*/
    public function history(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $result = $request->query('result');
        $lotIds = $this->historyLots()->pluck('id');

        $scans = LicensePlateScan::with(['user:id,name', 'parkingLot:id,name'])
            ->where('source', 'manual_upload')
            ->whereIn('parking_lot_id', $lotIds)
            ->when($q !== '', fn($query) =>
                $query->where('license_plate', 'like', "%{$q}%")
            )
            ->when(in_array($result, [LicensePlateScan::RESULT_PASSED, LicensePlateScan::RESULT_LOW_ACCURACY, LicensePlateScan::RESULT_UNREADABLE], true),
                fn($query) => $query->where('result', $result)
            )
            ->orderByDesc('scan_time')
            ->paginate(20)
            ->withQueryString();

        $view = Auth::user()->role === 'owner' ? 'owner.scan.history' : 'admin.scan.history';

        return view($view, compact('scans', 'q'));
    }
}
