<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Queries\ReservationLogQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** ประวัติการจอง (Reservation Log) ของลานตัวเอง — Owner ไม่เห็น Audit Log และไม่มี CSV Export */
class ReservationLogController extends Controller
{
    public function index(Request $request)
    {
        $lots = ParkingLot::ownedBy(Auth::id())->orderBy('name')->get(['id', 'name']);

        return view('staff.reservation-logs', [
            'scope'    => 'owner',
            'logs'     => ReservationLogQuery::build($request, $lots->pluck('id'))->paginate(20)->withQueryString(),
            'lots'     => $lots,
            'statuses' => Reservation::STATUSES,
            'filters'  => $request->query(),
        ]);
    }
}
