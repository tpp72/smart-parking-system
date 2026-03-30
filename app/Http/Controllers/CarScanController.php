<?php

namespace App\Http\Controllers;

use App\Models\LicensePlateScan;
use App\Services\CarScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CarScanController extends Controller
{
    public function __construct(private CarScanService $scanService) {}

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan   OR   /user/scan
     | Show upload form (+ latest scan result if flashed in session)
     ─────────────────────────────────────────────────────────────*/
    public function create()
    {
        return view('scan.index');
    }

    /* ─────────────────────────────────────────────────────────────
     | POST /admin/scan   OR   /user/scan
     | Validate, call service, redirect with result
     ─────────────────────────────────────────────────────────────*/
    public function store(Request $request)
    {
        $request->validate([
            'car_image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',   // 5 MB
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

            return redirect()->back()->with('scan_result', $scan->id);

        } catch (\RuntimeException $e) {
            return redirect()->back()
                ->withErrors(['car_image' => 'AI ไม่สามารถวิเคราะห์รูปภาพได้: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /* ─────────────────────────────────────────────────────────────
     | GET  /admin/scan/history
     | Admin-only: paginated history of all manual scans
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
