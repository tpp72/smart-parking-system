<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OwnerMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (!$user || $user->role !== 'owner') {
            if ($user?->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }
            abort(403, 'เฉพาะเจ้าของลานจอดเท่านั้น');
        }
        return $next($request);
    }
}
