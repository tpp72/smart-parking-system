<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use Illuminate\Http\Request;

class ParkingLogController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to   = $request->query('to');

        $logs = ParkingLog::query()
            ->with([
                'vehicle:id,license_plate,brand,color',
                'parkingLot:id,name',
                'parkingSlot:id,slot_number',
            ])
            ->when($q !== '', fn($query) =>
                $query->whereHas('vehicle', fn($v) =>
                    $v->where('license_plate', 'ilike', "%{$q}%")
                )
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
}
