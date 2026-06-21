<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OwnerApplicationController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status', '');

        $applications = OwnerApplication::with(['user:id,name,email', 'reviewer:id,name'])
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('business_name', 'like', "%{$q}%")
                    ->orWhere('contact_name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%"));
            }))
            ->when($status !== '', fn($query) => $query->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending'  => OwnerApplication::where('status', 'pending')->count(),
            'approved' => OwnerApplication::where('status', 'approved')->count(),
            'rejected' => OwnerApplication::where('status', 'rejected')->count(),
        ];

        return view('admin.owner-applications.index', compact('applications', 'q', 'status', 'counts'));
    }

    public function show(OwnerApplication $ownerApplication)
    {
        $ownerApplication->load(['user:id,name,email,role,owner_status', 'reviewer:id,name']);
        return view('admin.owner-applications.show', compact('ownerApplication'));
    }

    public function approve(OwnerApplication $ownerApplication)
    {
        if (!$ownerApplication->isPending()) {
            return back()->withErrors(['error' => 'คำขอนี้ไม่ได้อยู่ในสถานะรอพิจารณา']);
        }

        DB::transaction(function () use ($ownerApplication) {
            $ownerApplication->update([
                'status'      => 'approved',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $ownerApplication->user->forceFill([
                'role'         => 'owner',
                'owner_status' => 'approved',
            ])->save();
        });

        admin_audit('owner_application.approve', $ownerApplication, []);

        notify_user($ownerApplication->user_id, 'คำขอเจ้าของลานจอดได้รับการอนุมัติ! 🎉',
            'ยินดีด้วย! คำขอของคุณได้รับการอนุมัติแล้ว คุณสามารถเริ่มเพิ่มลานจอดได้ทันที');

        return redirect()->route('admin.owner-applications.index')
            ->with('success', "อนุมัติคำขอของ {$ownerApplication->user->name} เรียบร้อยแล้ว");
    }

    public function reject(Request $request, OwnerApplication $ownerApplication)
    {
        if (!$ownerApplication->isPending()) {
            return back()->withErrors(['error' => 'คำขอนี้ไม่ได้อยู่ในสถานะรอพิจารณา']);
        }

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        DB::transaction(function () use ($ownerApplication, $data) {
            $ownerApplication->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'],
                'reviewed_by'      => Auth::id(),
                'reviewed_at'      => now(),
            ]);

            $ownerApplication->user->forceFill([
                'owner_status' => 'rejected',
                // role stays 'user' — only becomes 'owner' upon approval
            ])->save();
        });

        admin_audit('owner_application.reject', $ownerApplication, [
            'reason' => $data['rejection_reason'],
        ]);

        notify_user($ownerApplication->user_id, 'คำขอเจ้าของลานจอดไม่ได้รับการอนุมัติ',
            'คำขอของคุณไม่ได้รับการอนุมัติ เหตุผล: ' . $data['rejection_reason'] . ' คุณสามารถแก้ไขและส่งคำขอใหม่ได้');

        return redirect()->route('admin.owner-applications.index')
            ->with('success', "ปฏิเสธคำขอของ {$ownerApplication->user->name} เรียบร้อยแล้ว");
    }
}
