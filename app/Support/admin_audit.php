<?php

use App\Models\AdminAction;

if (! function_exists('admin_audit')) {
    /**
     * @param string $action ex: 'user.force_reset'
     * @param mixed  $subject Eloquent model หรือ null
     * @param array  $meta associative array
     */
    function admin_audit(string $action, $subject = null, array $meta = []): void
    {
        try {
            $request = request();

            AdminAction::create([
                'admin_id'     => $user->id ?? null,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(), // ✅ ดีกว่า ->id
                'meta'         => $meta ?: null,
                'ip_address'   => $request?->ip(),
                'user_agent'   => substr((string) ($request?->userAgent()), 0, 2000),
            ]);
        } catch (\Throwable $e) {
            // ไม่ให้ logging ทำเว็บล่ม
        }
    }
}
