<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerApprovedMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user || $user->role !== 'owner') {
            abort(403);
        }

        if ($user->owner_status !== 'approved') {
            return redirect()->route('owner.dashboard')
                ->with('warning', 'ต้องได้รับการอนุมัติจาก Admin ก่อนจึงจะสามารถใช้งานฟีเจอร์นี้ได้');
        }

        return $next($request);
    }
}
