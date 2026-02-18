<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReservationLogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));          // ค้นหา: ชื่อ/อีเมล/ทะเบียน/old/new
        $from = $request->query('from');                      // YYYY-MM-DD
        $to = $request->query('to');                          // YYYY-MM-DD
        $old = $request->query('old_status');
        $new = $request->query('new_status');

        $base = DB::table('reservation_logs as rl')
            ->join('reservations as r', 'r.id', '=', 'rl.reservation_id')
            ->join('users as u', 'u.id', '=', 'rl.changed_by')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->select([
                'rl.id',
                'rl.reservation_id',
                'rl.old_status',
                'rl.new_status',
                'rl.changed_by',
                'rl.created_at',
                'u.name as changed_by_name',
                'u.email as changed_by_email',
                'v.license_plate',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('u.name', 'like', "%{$q}%")
                        ->orWhere('u.email', 'like', "%{$q}%")
                        ->orWhere('v.license_plate', 'like', "%{$q}%")
                        ->orWhere('rl.old_status', 'like', "%{$q}%")
                        ->orWhere('rl.new_status', 'like', "%{$q}%");
                });
            })
            ->when($old, fn($query) => $query->where('rl.old_status', $old))
            ->when($new, fn($query) => $query->where('rl.new_status', $new))
            ->when($from, fn($query) => $query->whereDate('rl.created_at', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('rl.created_at', '<=', $to))
            ->orderByDesc('rl.id');

        $logs = (clone $base)->paginate(20)->withQueryString();

        // ทำ dropdown status จากข้อมูลจริงใน table (ไม่เดา)
        $statuses = DB::table('reservation_logs')
            ->select('old_status as s')
            ->union(DB::table('reservation_logs')->select('new_status as s'))
            ->distinct()
            ->orderBy('s')
            ->pluck('s');

        return view('admin.reservation-logs.index', compact('logs', 'q', 'from', 'to', 'old', 'new', 'statuses'));
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $from = $request->query('from');
        $to = $request->query('to');
        $old = $request->query('old_status');
        $new = $request->query('new_status');

        $query = DB::table('reservation_logs as rl')
            ->join('reservations as r', 'r.id', '=', 'rl.reservation_id')
            ->join('users as u', 'u.id', '=', 'rl.changed_by')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->select([
                'rl.id',
                'rl.reservation_id',
                'v.license_plate',
                'rl.old_status',
                'rl.new_status',
                'u.name as changed_by_name',
                'u.email as changed_by_email',
                'rl.created_at',
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($x) use ($q) {
                    $x->where('u.name', 'like', "%{$q}%")
                        ->orWhere('u.email', 'like', "%{$q}%")
                        ->orWhere('v.license_plate', 'like', "%{$q}%")
                        ->orWhere('rl.old_status', 'like', "%{$q}%")
                        ->orWhere('rl.new_status', 'like', "%{$q}%");
                });
            })
            ->when($old, fn($qq) => $qq->where('rl.old_status', $old))
            ->when($new, fn($qq) => $qq->where('rl.new_status', $new))
            ->when($from, fn($qq) => $qq->whereDate('rl.created_at', '>=', $from))
            ->when($to, fn($qq) => $qq->whereDate('rl.created_at', '<=', $to))
            ->orderByDesc('rl.id');

        $filename = 'reservation_logs_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM กัน Excel อ่านภาษาไทยเพี้ยน
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($out, ['id', 'reservation_id', 'license_plate', 'old_status', 'new_status', 'changed_by_name', 'changed_by_email', 'created_at']);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->reservation_id,
                        $r->license_plate,
                        $r->old_status,
                        $r->new_status,
                        $r->changed_by_name,
                        $r->changed_by_email,
                        $r->created_at,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
