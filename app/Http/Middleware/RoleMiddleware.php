<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    /**
     * ตรวจสอบ role ของ user ที่ล็อกอิน
     *
     * ใช้งาน: middleware('role:admin') หรือ middleware('role:admin,user')
     *
     * - role:admin       → ผ่านเฉพาะ admin
     * - role:user        → ผ่านเฉพาะ user ทั่วไป
     * - role:admin,user  → ผ่านทั้งคู่ (= แค่ต้อง login)
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            // admin พยายามเข้าหน้า user → ส่งไป admin dashboard
            if ($user?->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }

            // user พยายามเข้าหน้า admin → 403
            abort(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        return $next($request);
    }
}
