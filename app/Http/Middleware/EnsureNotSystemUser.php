<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * บัญชีระบบ (เช่น Walkin User) ใช้งานระบบในฐานะผู้ใช้จริงไม่ได้ (project-plan.md §3, §19.4)
 * ถ้ามี Session ของบัญชีระบบค้างอยู่ ให้ออกจากระบบทันที
 */
class EnsureNotSystemUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_system) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'บัญชีนี้เป็นบัญชีของระบบ ไม่สามารถเข้าสู่ระบบได้']);
        }

        return $next($request);
    }
}
