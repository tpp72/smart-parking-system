<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, UserAccountService $accounts): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'deletionBlocker' => $accounts->selfDeletionBlocker($request->user()),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $before = $request->user()->only(array_keys($request->validated()));

        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        audit_log('profile.update', $request->user(), ['changes' => audit_changes($before, $request->user())]);

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * ลบบัญชีตัวเอง — เฉพาะ User ที่ไม่มีรถจอดอยู่ ระบบยกเลิกการจองที่ยังไม่ Check-in ให้ก่อน (UserAccountService)
     */
    public function destroy(Request $request, UserAccountService $accounts): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $result = $accounts->deleteSelf($request->user(), beforeDelete: fn () => Auth::logout());

        if (! $result['success']) {
            return Redirect::route('profile.edit')->withErrors(['account' => $result['error']], 'userDeletion');
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
