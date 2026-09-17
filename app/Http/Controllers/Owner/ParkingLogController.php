<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Services\CheckOutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** ประวัติการจอดของลาน Owner — Manual Check-out ใช้ปุ่มของการจอง (reservations.check-out) */
class ParkingLogController extends Controller
{
    public function index(Request $request, CheckOutService $checkOut)
    {
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to   = $request->query('to');

        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');

        $logs = ParkingLog::query()
            ->with([
                'parkingLot:id,name',
                'parkingSlot:id,slot_number',
                'reservation:id,user_id,is_walk_in,reservation_fee,status',
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

        // รถที่ยังจอดอยู่: ยอดประมาณถ้า Check-out ตอนนี้ (หักมัดจำและส่วนลดแล้ว)
        $estimates = $logs->getCollection()
            ->filter(fn (ParkingLog $log) => ! $log->check_out_time && $log->reservation)
            ->mapWithKeys(fn (ParkingLog $log) => [$log->id => $checkOut->calculate($log->reservation, $log, now())]);

        return view('staff.parking-logs', compact('logs', 'q', 'from', 'to', 'estimates') + ['scope' => 'owner']);
    }
}
