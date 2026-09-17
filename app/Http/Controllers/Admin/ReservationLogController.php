<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Queries\ReservationLogQuery;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reservation Log ทั้งระบบ — ทุกลาน ทุก Role รวมรายการที่ระบบดำเนินการ (project-plan.md §18.1) */
class ReservationLogController extends Controller
{
    public function index(Request $request)
    {
        return view('staff.reservation-logs', [
            'scope'    => 'admin',
            'logs'     => ReservationLogQuery::build($request)->paginate(20)->withQueryString(),
            'lots'     => ParkingLot::orderBy('name')->get(['id', 'name']),
            'statuses' => Reservation::STATUSES,
            'filters'  => $request->query(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('reservation_logs', [
            'id', 'created_at', 'reservation_id', 'walk_in', 'parking_lot', 'license_plate', 'plate_province',
            'old_status', 'new_status', 'changed_by', 'changed_by_role', 'changed_by_email', 'note',
        ], ReservationLogQuery::build($request), fn ($r) => [
            $r->id,
            $r->created_at,
            $r->reservation_id,
            $r->is_walk_in ? 'yes' : 'no',
            $r->lot_name,
            $r->license_plate,
            $r->plate_province,
            $r->old_status,
            $r->new_status,
            $r->changed_by ? ($r->changed_by_name ?? 'บัญชีถูกลบ') : 'ระบบ',
            $r->changed_by ? $r->changed_by_role : 'system',
            $r->changed_by_email,
            $r->note,
        ]);
    }
}
