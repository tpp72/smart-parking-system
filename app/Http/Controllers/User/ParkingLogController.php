<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParkingLogController extends Controller
{
    /** ประวัติการจอดทั้งหมดของ user ที่ login อยู่ */
    public function index()
    {
        $logs = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->leftJoin('payments as p', 'p.parking_log_id', '=', 'pl.id')
            ->where('v.user_id', Auth::id())
            ->orderByDesc('pl.check_in_time')
            ->select([
                'pl.id as log_id',
                'v.license_plate',
                'lot.name as lot_name',
                's.slot_number',
                'pl.check_in_time',
                'pl.check_out_time',
                'p.total_hours',
                'p.total_amount',
                'p.payment_status',
            ])
            ->paginate(15);

        return view('user.parking-logs.index', compact('logs'));
    }
}
