<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ParkingLogController extends Controller
{
    public function __construct(private CheckOutService $checkOutService) {}

    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to   = $request->query('to');

        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');

        $logs = ParkingLog::query()
            ->with([
                'parkingLot:id,name,hourly_rate',
                'parkingSlot:id,slot_number',
                'reservation:id,user_id',
                'reservation.user:id,name',
            ])
            ->whereIn('parking_lot_id', $ownedLotIds)
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

        return view('owner.parking-logs.index', compact('logs', 'q', 'from', 'to'));
    }

    /** Check-Out สำหรับรถ walk-in (ไม่มี reservation ผูกอยู่ — เช็คอินผ่าน AI scan หรือกรณีไม่ได้จองล่วงหน้า) */
    public function checkOut(ParkingLog $log)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id')->all();
        $result = $this->checkOutService->checkOut($log, $ownedLotIds);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', sprintf(
            'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
            $log->license_plate,
            $result['totalHours'],
            $result['parkingFee'],
            $result['totalAmount'],
        ));
    }
}
