<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CsvExport;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Audit Log ทั้งระบบ — การกระทำของทุก Role และเหตุการณ์ที่ระบบดำเนินการ (project-plan.md §18) */
class AdminActionController extends Controller
{
    private const ROLES = ['user', 'owner', 'admin', 'system'];

    public function index(Request $request)
    {
        return view('admin.admin-actions.index', [
            'rows'         => $this->query($request)->paginate(20)->withQueryString(),
            'actions'      => DB::table('admin_actions')->distinct()->orderBy('action')->pluck('action'),
            'subjectTypes' => DB::table('admin_actions')->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'roles'        => self::ROLES,
            'filters'      => $request->query(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('audit_log', [
            'id', 'created_at', 'actor_role', 'actor_name', 'actor_email', 'action', 'subject_type', 'subject_id', 'ip', 'user_agent', 'meta',
        ], $this->query($request), fn ($r) => [
            $r->id,
            $r->created_at,
            $r->actor_role,
            $r->actor_role === 'system' ? 'ระบบ' : ($r->actor_name ?? 'บัญชีถูกลบ'),
            $r->actor_email,
            $r->action,
            $r->subject_type,
            $r->subject_id,
            $r->ip_address,
            $r->user_agent,
            self::metaText($r->meta),
        ]);
    }

    /** Meta สำหรับแสดงผล (คงภาษาไทยไว้ ไม่ escape เป็น \uXXXX) */
    public static function metaText(?string $meta): string
    {
        return $meta ? json_encode(json_decode($meta, true), JSON_UNESCAPED_UNICODE) : '';
    }

    private function query(Request $request): Builder
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('actor_role');

        return DB::table('admin_actions as aa')
            ->leftJoin('users as u', 'u.id', '=', 'aa.actor_id')
            ->select([
                'aa.id',
                'aa.action',
                'aa.actor_id',
                'aa.actor_role',
                'aa.subject_type',
                'aa.subject_id',
                'aa.meta',
                'aa.ip_address',
                'aa.user_agent',
                'aa.created_at',
                'u.name as actor_name',
                'u.email as actor_email',
            ])
            ->when($q !== '', fn ($query) => $query->where(function ($qq) use ($q) {
                $qq->where('aa.action', 'ilike', "%{$q}%")
                    ->orWhere('aa.subject_type', 'ilike', "%{$q}%")
                    ->orWhere('u.name', 'ilike', "%{$q}%")
                    ->orWhere('u.email', 'ilike', "%{$q}%")
                    ->orWhereRaw('CAST(aa.subject_id AS TEXT) = ?', [ltrim($q, '#')])
                    // jsonb แปลง \uXXXX กลับเป็นตัวอักษร จึงค้นหาภาษาไทยใน Meta ได้
                    ->orWhereRaw('CAST(CAST(aa.meta AS JSONB) AS TEXT) ILIKE ?', ["%{$q}%"]);
            }))
            ->when(in_array($role, self::ROLES, true), fn ($query) => $query->where('aa.actor_role', $role))
            ->when($request->query('action'), fn ($query, $action) => $query->where('aa.action', $action))
            ->when($request->query('subject_type'), fn ($query, $type) => $query->where('aa.subject_type', $type))
            ->when($request->query('from'), fn ($query, $date) => $query->whereDate('aa.created_at', '>=', $date))
            ->when($request->query('to'), fn ($query, $date) => $query->whereDate('aa.created_at', '<=', $date))
            ->orderByDesc('aa.id');
    }
}
