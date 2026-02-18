<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminActionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $action = $request->query('action');
        $subjectType = $request->query('subject_type');
        $from = $request->query('from'); // YYYY-MM-DD
        $to = $request->query('to');     // YYYY-MM-DD

        $base = DB::table('admin_actions as aa')
            ->leftJoin('users as u', 'u.id', '=', 'aa.admin_id')
            ->select([
                'aa.id',
                'aa.action',
                'aa.subject_type',
                'aa.subject_id',
                'aa.meta',
                'aa.ip_address',
                'aa.created_at',
                'u.name as admin_name',
                'u.email as admin_email',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('aa.action', 'like', "%{$q}%")
                        ->orWhere('aa.subject_type', 'like', "%{$q}%")
                        ->orWhere('aa.subject_id', '::text', 'like', "%{$q}%"); // pgsql
                })
                    ->orWhere('u.name', 'like', "%{$q}%")
                    ->orWhere('u.email', 'like', "%{$q}%");
            })
            ->when($action, fn($query) => $query->where('aa.action', $action))
            ->when($subjectType, fn($query) => $query->where('aa.subject_type', $subjectType))
            ->when($from, fn($query) => $query->whereDate('aa.created_at', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('aa.created_at', '<=', $to))
            ->orderByDesc('aa.id');

        $rows = (clone $base)->paginate(20)->withQueryString();

        // dropdowns จากข้อมูลจริง (ไม่เดา)
        $actions = DB::table('admin_actions')->select('action')->distinct()->orderBy('action')->pluck('action');
        $subjectTypes = DB::table('admin_actions')->select('subject_type')->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type');

        return view('admin.admin-actions.index', compact('rows', 'q', 'action', 'subjectType', 'from', 'to', 'actions', 'subjectTypes'));
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q', ''));
        $action = $request->query('action');
        $subjectType = $request->query('subject_type');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = DB::table('admin_actions as aa')
            ->leftJoin('users as u', 'u.id', '=', 'aa.admin_id')
            ->select([
                'aa.id',
                'aa.action',
                'aa.subject_type',
                'aa.subject_id',
                'aa.meta',
                'aa.ip_address',
                'aa.user_agent',
                'aa.created_at',
                'u.name as admin_name',
                'u.email as admin_email',
            ])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where('aa.action', 'like', "%{$q}%")
                    ->orWhere('aa.subject_type', 'like', "%{$q}%")
                    ->orWhere('u.name', 'like', "%{$q}%")
                    ->orWhere('u.email', 'like', "%{$q}%");
            })
            ->when($action, fn($qq) => $qq->where('aa.action', $action))
            ->when($subjectType, fn($qq) => $qq->where('aa.subject_type', $subjectType))
            ->when($from, fn($qq) => $qq->whereDate('aa.created_at', '>=', $from))
            ->when($to, fn($qq) => $qq->whereDate('aa.created_at', '<=', $to))
            ->orderByDesc('aa.id');

        $filename = 'admin_actions_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM

            fputcsv($out, ['id', 'action', 'subject_type', 'subject_id', 'admin_name', 'admin_email', 'ip', 'created_at', 'meta']);

            $query->chunk(1000, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        $r->action,
                        $r->subject_type,
                        $r->subject_id,
                        $r->admin_name,
                        $r->admin_email,
                        $r->ip_address,
                        $r->created_at,
                        is_string($r->meta) ? $r->meta : json_encode($r->meta, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
