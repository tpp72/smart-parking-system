<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** ประวัติการจอดของลาน Owner — Manual Check-out ใช้ปุ่มของการจอง (reservations.check-out) */
class ParkingLogController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to   = $request->query('to');

        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');

        $logs = ParkingLog::query()
            ->with([
                'parkingLot:id,name',
                'parkingSlot:id,slot_number',
                'reservation:id,user_id,is_walk_in',
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
}
