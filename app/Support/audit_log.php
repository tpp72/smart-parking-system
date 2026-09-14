<?php

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/*
 | Audit Log (project-plan.md §18) — ตาราง admin_actions เก็บการกระทำของทุก Role
 | (user / owner / admin) และเหตุการณ์ที่ระบบเป็นผู้ดำเนินการ (system)
 */

if (! function_exists('audit_log')) {
    /** บันทึกการกระทำของผู้ใช้ที่กำลังทำรายการ — ไม่มีผู้ Login ถือเป็นระบบ */
    function audit_log(string $action, ?Model $subject = null, array $meta = []): void
    {
        audit_by(Auth::user(), $action, $subject, $meta);
    }
}

if (! function_exists('audit_by')) {
    /** บันทึก Audit Log โดยระบุผู้กระทำ — $actor = null คือระบบ (เช่น Auto Check-in, Walk-in, Expire) */
    function audit_by(?User $actor, string $action, ?Model $subject = null, array $meta = []): void
    {
        try {
            // IP / User Agent มีความหมายเฉพาะการกระทำของผู้ใช้
            $request = $actor ? request() : null;

            // Model ที่เพิ่งสร้าง (เช่นตอนสมัครสมาชิก) อาจยังไม่มีค่า role ที่ Database กำหนดเป็นค่าเริ่มต้น
            $role = $actor ? ($actor->role ?? User::whereKey($actor->getKey())->value('role')) : 'system';

            AdminAction::create([
                'actor_id'     => $actor?->id,
                'actor_role'   => $role,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'meta'         => $meta ?: null,
                'ip_address'   => $request?->ip(),
                'user_agent'   => $request ? substr((string) $request->userAgent(), 0, 2000) : null,
            ]);
        } catch (\Throwable $e) {
            // การบันทึก Log ต้องไม่ทำให้รายการหลักล้มเหลว — เก็บข้อผิดพลาดไว้ใน Application Log
            Log::error("[Audit] {$action} was not recorded: {$e->getMessage()}");
        }
    }
}

if (! function_exists('audit_changes')) {
    /**
     * เทียบค่าก่อน/หลังแก้ไข เฉพาะฟิลด์ที่เปลี่ยน
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    function audit_changes(array $before, Model $after): array
    {
        $changes = [];

        foreach ($before as $field => $old) {
            $new = $after->getAttribute($field);

            $changed = is_numeric($old) && is_numeric($new)
                ? (float) $old !== (float) $new
                : (string) $old !== (string) $new;

            if ($changed) {
                $changes[$field] = ['from' => $old, 'to' => $new];
            }
        }

        return $changes;
    }
}
