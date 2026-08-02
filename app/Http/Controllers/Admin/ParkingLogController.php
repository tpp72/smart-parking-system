<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Services\CheckOutService;
use Illuminate\Http\Request;

class ParkingLogController extends Controller
{
    public function __construct(private CheckOutService $checkOutService) {}

    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to   = $request->query('to');

        $logs = ParkingLog::query()
            ->with([
                'parkingLot:id,name,hourly_rate',
                'parkingSlot:id,slot_number',
                'reservation:id,user_id',
                'reservation.user:id,name',
            ])
            ->whereHas('parkingLot', fn($q) => $q->whereNull('owner_id'))
            ->when($q !== '', fn($query) =>
                $query->where('license_plate', 'ilike', "%{$q}%")
            )
            ->when($from, fn($query) =>
                $query->whereDate('check_in_time', '>=', $from)
            )
            ->when($to, fn($query) =>
                $query->whereDate('check_in_time', '<=', $to)
            )
            ->orderByDesc('check_in_time')
            ->paginate(20)
            ->withQueryString();

        return view('admin.parking-logs.index', compact('logs', 'q', 'from', 'to'));
    }

    /** Check-Out สำหรับรถ walk-in (ไม่มี reservation ผูกอยู่ — เช็คอินผ่าน AI scan หรือกรณีไม่ได้จองล่วงหน้า) */
    public function checkOut(ParkingLog $log)
    {
        $allowedLotIds = ParkingLot::unowned()->pluck('id')->all();
        $result = $this->checkOutService->checkOut($log, $allowedLotIds);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        admin_audit('parking_log.check_out', $log, [
            'total_hours'          => $result['totalHours'],
            'parking_fee'          => $result['parkingFee'],
            'reservation_discount' => $result['deposit'],
            'total_amount'         => $result['totalAmount'],
        ]);

        return back()->with('success', sprintf(
            'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
            $log->license_plate,
            $result['totalHours'],
            $result['parkingFee'],
            $result['totalAmount'],
        ));
    }
}
