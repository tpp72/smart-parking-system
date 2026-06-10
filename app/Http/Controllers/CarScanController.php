<?php

namespace App\Http\Controllers;

use App\Models\LicensePlateScan;
use App\Models\Reservation;
use App\Services\CarScanService;
use App\Services\CheckInService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CarScanController extends Controller
{
    public function __construct(
        private CarScanService  $scanService,
        private CheckInService  $checkInService,
    ) {}

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan   OR   /user/scan
     ─────────────────────────────────────────────────────────────*/
    public function create()
    {
        return view('scan.index');
    }

    /* ─────────────────────────────────────────────────────────────
     | POST /admin/scan   OR   /user/scan
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
        ], [
            'car_image.required' => 'กรุณาเลือกรูปภาพรถก่อน',
            'car_image.image'    => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'car_image.mimes'    => 'รองรับเฉพาะ JPG และ PNG',
            'car_image.max'      => 'ขนาดไฟล์ต้องไม่เกิน 5 MB',
        ]);

        try {
            $scan = $this->scanService->scanAndSave(
                $request->file('car_image'),
                Auth::id()
            );

            $sessionData = ['scan_result' => $scan->id];

            // ─── Reservation matching ───────────────────────────────────
            if ($scan->license_plate) {
                $reservation = $this->scanService->findMatchingReservation($scan->license_plate);

                if ($reservation) {
                    $sessionData['scan_reservation_id'] = $reservation->id;

                    // ─── Auto check-in (only for confirmed + within window) ──
                    if ($reservation->status === 'confirmed') {
                        $isCheckable = Reservation::checkable()
                            ->where('id', $reservation->id)
                            ->exists();

                        if ($isCheckable) {
                            $result = $this->checkInService->checkIn(
                                $reservation->vehicle_id,
                                $reservation->parking_lot_id
                            );

                            $sessionData['scan_check_in'] = [
                                'success' => $result['success'],
                                'error'   => $result['error'],
                                'slot'    => $result['slot']?->slot_number,
                            ];

                            if ($result['success']) {
                                notify_user(
                                    $reservation->user_id,
                                    'เช็คอินอัตโนมัติสำเร็จ',
                                    "ทะเบียน {$scan->license_plate} เช็คอินผ่านระบบสแกนรถ เข้าจอดที่ช่อง {$result['slot']->slot_number} แล้ว"
                                );
                            }
                        } else {
                            // Reservation found but outside check-in window
                            $sessionData['scan_check_in'] = [
                                'success' => false,
                                'error'   => 'อยู่นอกช่วงเวลาเช็คอิน (เร็วเกินไปหรือเกินเวลากำหนด)',
                                'slot'    => null,
                            ];
                        }
                    }
                    // status === 'checked_in': show info only, no action needed
                }
            }

            return redirect()->back()->with($sessionData);

        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['car_image' => 'AI ไม่สามารถวิเคราะห์รูปภาพได้: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan/history
     ─────────────────────────────────────────────────────────────*/
    public function history(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $scans = LicensePlateScan::with(['user:id,name', 'vehicle:id,license_plate,brand,color'])
            ->where('source', 'manual_upload')
            ->when($q !== '', fn($query) =>
                $query->where('license_plate', 'like', "%{$q}%")
            )
            ->orderByDesc('scan_time')
            ->paginate(20)
            ->withQueryString();

        return view('admin.scan.history', compact('scans', 'q'));
    }
}
