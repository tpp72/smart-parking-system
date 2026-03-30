<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** แสดง notifications ทั้งหมดของ user (unread ก่อน) */
    public function index()
    {
        $notifications = Notification::where('user_id', Auth::id())
            ->orderBy('is_read')          // unread ขึ้นก่อน
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    /** Mark เดี่ยว */
    public function markRead(Notification $notification)
    {
        abort_if($notification->user_id !== Auth::id(), 403);

        $notification->update(['is_read' => true]);

        return back()->with('success', 'อ่านแล้ว');
    }

    /** Mark ทั้งหมด */
    public function markAllRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'อ่านทั้งหมดแล้ว');
    }
}
