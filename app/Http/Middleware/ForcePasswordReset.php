<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordReset
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->force_password_reset) {
            // อนุญาตเฉพาะ route ที่จำเป็น
            $allowed = [
                'profile.edit',
                'password.update',
                'logout',
            ];

            $routeName = $request->route()?->getName();

            if (!$routeName || !in_array($routeName, $allowed, true)) {
                return redirect()->route('profile.edit')
                    ->with('warning', 'กรุณาเปลี่ยนรหัสผ่านก่อนใช้งานต่อ');
            }
        }

        return $next($request);
    }
}
