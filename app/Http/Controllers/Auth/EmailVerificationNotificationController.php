<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        // ส่งได้ครั้งละ 1 ครั้งต่อ 60 วินาที (นับจากครั้งล่าสุด รวมตอนสมัคร) — ยังไม่ครบไม่ส่ง
        if ($remaining = $request->user()->verificationCooldownRemaining()) {
            return back()->with('verification-cooldown', $remaining);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
