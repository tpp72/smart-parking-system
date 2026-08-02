<?php

namespace App\Http\Controllers;

use App\Models\LicensePlateScan;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Models\User;
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

    /**
     * ลานที่ role ปัจจุบันมีสิทธิ์ "เป็นกล้อง" ได้ — ระบบจริงกล้องติดอยู่ที่ลานใดลานหนึ่งเสมอ
     * (admin: ลานที่ไม่มีเจ้าของ, owner: ลานของตัวเอง, user: ลานสาธารณะที่เปิดให้จอง)
     */
    private function authorizedLots()
    {
        return match (Auth::user()->role) {
            'admin' => ParkingLot::unowned(),
            'owner' => ParkingLot::ownedBy(Auth::id()),
            default => ParkingLot::reservable(),
        };
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan   OR   /owner/scan   OR   /user/scan
     ─────────────────────────────────────────────────────────────*/
    public function create()
    {
        $lots = $this->authorizedLots()->orderBy('name')->get(['id', 'name']);

        return view('scan.index', compact('lots'));
    }

    /* ─────────────────────────────────────────────────────────────
     | POST /admin/scan   OR   /owner/scan   OR   /user/scan
     ─────────────────────────────────────────────────────────────*/
    public function store(Request $request)
    {
        $allowedLotIds = $this->authorizedLots()->pluck('id')->all();

        $request->validate([
            'car_image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
            ],
            'parking_lot_id' => ['required', 'integer', function ($attr, $value, $fail) use ($allowedLotIds) {
                if (!in_array((int) $value, $allowedLotIds, true)) {
                    $fail('ไม่มีสิทธิ์สแกนให้ลานจอดนี้');
                }
            }],
        ], [
            'car_image.required'    => 'กรุณาเลือกรูปภาพรถก่อน',
            'car_image.image'       => 'ไฟล์ต้องเป็นรูปภาพเท่านั้น',
            'car_image.mimes'       => 'รองรับเฉพาะ JPG และ PNG',
            'car_image.max'         => 'ขนาดไฟล์ต้องไม่เกิน 5 MB',
            'parking_lot_id.required' => 'กรุณาเลือกลานจอด (จำลองตำแหน่งกล้อง)',
        ]);

        $parkingLotId = (int) $request->input('parking_lot_id');

        try {
            $scan = $this->scanService->scanAndSave(
                $request->file('car_image'),
                Auth::id(),
                $parkingLotId
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
                            // ต้องตรงอย่างน้อย 2 ใน 4 เกณฑ์ (ทะเบียน + จังหวัด/ยี่ห้อ/สี) กันเช็คอินรถผิดคัน
                            // ที่บังเอิญทะเบียนคล้ายกัน ก่อนอนุญาตให้ auto check-in
                            $match = $this->scanService->matchScanAgainstReservation(
                                $reservation,
                                $scan->province,
                                $scan->brand,
                                $scan->color
                            );

                            if (!$match['passed']) {
                                $sessionData['scan_check_in'] = [
                                    'success' => false,
                                    'error'   => 'ข้อมูลรถไม่ตรงกับที่แจ้งไว้ตอนจอง: '
                                        . implode(', ', $match['mismatches'])
                                        . ' — กรุณาให้เจ้าหน้าที่ตรวจสอบก่อนเช็คอิน',
                                    'slot'    => null,
                                ];
                            } else {
                                // ตรวจ blacklist — ไม่บล็อกการเช็คอิน แค่แจ้งเตือน Owner ของลาน + Admin ทุกคน
                                if ($scan->is_suspicious) {
                                    $this->notifySuspiciousVehicle($scan, $reservation->parkingLot ?? ParkingLot::find($reservation->parking_lot_id));
                                }

                                // ใช้ทะเบียน/ยี่ห้อ/สีจาก reservation เป็นหลัก (มีครบเสมอ เพราะบังคับกรอกตอนจอง)
                                // vehicle_id เป็นแค่ลิงก์เสริมถ้ามี Vehicle record ตรงกันอยู่จริง ไม่บังคับ
                                $vehicleId = $reservation->vehicle_id ?? $scan->vehicle_id;

                                $result = $this->checkInService->checkIn(
                                    $reservation->license_plate,
                                    $reservation->brand,
                                    $reservation->color,
                                    $reservation->parking_lot_id,
                                    null,
                                    $vehicleId
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
                } else {
                    $sessionData['scan_check_in'] = $this->attemptWalkInCheckIn($scan, $parkingLotId, $allowedLotIds);
                }
            }

            return redirect()->back()->with($sessionData);

        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['car_image' => 'AI ไม่สามารถวิเคราะห์รูปภาพได้: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Auto check-in สำหรับรถที่ไม่ได้จองล่วงหน้า (walk-in) — สแกนแล้วหา reservation ไม่เจอ
     * เช็คอินเข้าลานที่เลือกไว้ตอนสแกน (จำลองตำแหน่งกล้อง) ใช้ได้ทุก role ตามลานที่มีสิทธิ์
     *
     * @param array<int> $allowedLotIds
     * @return array{success: bool, error: ?string, slot: ?string}
     */
    private function attemptWalkInCheckIn(LicensePlateScan $scan, int $parkingLotId, array $allowedLotIds): array
    {
        // ตรวจ blacklist — ไม่บล็อกการเช็คอิน แค่แจ้งเตือน Owner ของลาน + Admin ทุกคน
        if ($scan->is_suspicious) {
            $this->notifySuspiciousVehicle($scan, ParkingLot::find($parkingLotId));
        }

        $result = $this->checkInService->checkIn(
            $scan->license_plate,
            $scan->brand,
            $scan->color,
            $parkingLotId,
            $allowedLotIds,
            $scan->vehicle_id
        );

        if ($result['success']) {
            notify_user(
                Auth::id(),
                'เช็คอินอัตโนมัติสำเร็จ (Walk-in)',
                "ทะเบียน {$scan->license_plate} เช็คอินผ่านระบบสแกนรถ เข้าจอดที่ช่อง {$result['slot']->slot_number} แล้ว"
            );
        }

        return [
            'success' => $result['success'],
            'error'   => $result['error'],
            'slot'    => $result['slot']?->slot_number,
        ];
    }

    /**
     * แจ้งเตือน Owner ของลาน (ถ้ามี) + Admin ทุกคน เมื่อพบรถต้องสงสัยกำลังเช็คอิน — ไม่บล็อกการเช็คอิน
     * แค่แจ้งรายละเอียดรถ/ลาน/เวลาให้ตรวจสอบภายหลัง
     */
    private function notifySuspiciousVehicle(LicensePlateScan $scan, ?ParkingLot $lot): void
    {
        $lotName = $lot?->name ?? 'ไม่ทราบลาน';
        $detail  = "ทะเบียน {$scan->license_plate}"
            . ($scan->brand ? " ยี่ห้อ {$scan->brand}" : '')
            . ($scan->color ? " สี {$scan->color}" : '')
            . " เข้าจอดที่ลาน {$lotName} เวลา " . now()->format('d/m/Y H:i');

        $recipientIds = collect();
        if ($lot?->owner_id) {
            $recipientIds->push($lot->owner_id);
        }
        $recipientIds = $recipientIds->merge(User::where('role', 'admin')->pluck('id'))->unique();

        foreach ($recipientIds as $userId) {
            notify_user($userId, '⚠ พบรถต้องสงสัย (Blacklist)', $detail);
        }
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan/history   OR   /owner/scan/history
     ─────────────────────────────────────────────────────────────*/
    public function history(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $lotIds = $this->authorizedLots()->pluck('id');

        $scans = LicensePlateScan::with(['user:id,name', 'vehicle:id,license_plate,brand,color', 'parkingLot:id,name'])
            ->where('source', 'manual_upload')
            ->whereIn('parking_lot_id', $lotIds)
            ->when($q !== '', fn($query) =>
                $query->where('license_plate', 'like', "%{$q}%")
            )
            ->orderByDesc('scan_time')
            ->paginate(20)
            ->withQueryString();

        $view = Auth::user()->role === 'owner' ? 'owner.scan.history' : 'admin.scan.history';

        return view($view, compact('scans', 'q'));
    }
}
