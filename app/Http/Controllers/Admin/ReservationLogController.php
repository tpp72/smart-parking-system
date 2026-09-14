<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Queries\ReservationLogQuery;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reservation Log ทั้งระบบ — ทุกลาน ทุก Role รวมรายการที่ระบบดำเนินการ (project-plan.md §18.1) */
class ReservationLogController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.reservation-logs.index', [
            'logs'     => ReservationLogQuery::build($request)->paginate(20)->withQueryString(),
            'lots'     => ParkingLot::orderBy('name')->get(['id', 'name']),
            'statuses' => Reservation::STATUSES,
            'filters'  => $request->query(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = ReservationLogQuery::build($request);
        $filename = 'reservation_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM กัน Excel อ่านภาษาไทยเพี้ยน
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, [
                'id', 'created_at', 'reservation_id', 'walk_in', 'parking_lot', 'license_plate', 'plate_province',
                'old_status', 'new_status', 'changed_by', 'changed_by_role', 'changed_by_email', 'note',
            ]);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
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
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
