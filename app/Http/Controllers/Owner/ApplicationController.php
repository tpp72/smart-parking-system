<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    public function create()
    {
        $user = Auth::user();

        // Already an approved owner → go to dashboard
        if ($user->role === 'owner' && $user->owner_status === 'approved') {
            return redirect()->route('owner.dashboard');
        }

        // Already has an application — show status page instead
        $application = OwnerApplication::where('user_id', $user->id)->latest()->first();
        if ($application && $application->isPending()) {
            return redirect()->route('owner.application.show');
        }

        // Admin cannot apply
        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('owner.application.create', compact('user'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }
        if ($user->role === 'owner' && $user->owner_status === 'approved') {
            return redirect()->route('owner.dashboard');
        }

        // Check for existing pending application
        $existing = OwnerApplication::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return redirect()->route('owner.application.show');
        }

        $data = $request->validate([
            'business_name'    => ['required', 'string', 'max:255'],
            'contact_name'     => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:20'],
            'email'            => ['required', 'email', 'max:255'],
            'parking_lot_name' => ['required', 'string', 'max:255'],
            'address'          => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'estimated_slots'  => ['required', 'integer', 'min:1', 'max:10000'],
            'document'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')
                ->store('owner-applications', 'public');
        }

        DB::transaction(function () use ($user, $data, $documentPath) {
            OwnerApplication::create([
                'user_id'          => $user->id,
                'business_name'    => $data['business_name'],
                'contact_name'     => $data['contact_name'],
                'phone'            => $data['phone'],
                'email'            => $data['email'],
                'parking_lot_name' => $data['parking_lot_name'],
                'address'          => $data['address'],
                'description'      => $data['description'] ?? null,
                'estimated_slots'  => $data['estimated_slots'],
                'document_path'    => $documentPath,
                'status'           => 'pending',
            ]);

            // Promote to owner role with pending status
            $user->forceFill([
                'role'         => 'owner',
                'owner_status' => 'pending',
            ])->save();
        });

        // Notify the user
        notify_user($user->id, 'ส่งคำขอเป็นเจ้าของลานจอดแล้ว',
            'คำขอของคุณอยู่ระหว่างการพิจารณาจาก Admin กรุณารอการยืนยัน');

        // Notify all admins
        $adminIds = User::where('role', 'admin')->pluck('id');
        foreach ($adminIds as $adminId) {
            notify_user($adminId, 'คำขอเจ้าของลานจอดใหม่',
                "ผู้ใช้ {$user->name} ส่งคำขอเป็นเจ้าของลานจอด รอการอนุมัติ");
        }

        return redirect()->route('owner.dashboard')
            ->with('success', 'ส่งคำขอเรียบร้อยแล้ว! Admin จะพิจารณาและแจ้งผลให้คุณทราบ');
    }

    public function show()
    {
        $user = Auth::user();
        $application = OwnerApplication::where('user_id', $user->id)->latest()->first();

        if (!$application && $user->role !== 'owner') {
            return redirect()->route('owner.application.create');
        }

        return view('owner.application.show', compact('application', 'user'));
    }

    public function edit()
    {
        $user = Auth::user();
        $application = OwnerApplication::where('user_id', $user->id)->latest()->first();

        if (!$application || !$application->isRejected()) {
            return redirect()->route('owner.application.show');
        }

        return view('owner.application.edit', compact('application', 'user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        $application = OwnerApplication::where('user_id', $user->id)->latest()->first();

        if (!$application || !$application->isRejected()) {
            return redirect()->route('owner.application.show');
        }

        $data = $request->validate([
            'business_name'    => ['required', 'string', 'max:255'],
            'contact_name'     => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:20'],
            'email'            => ['required', 'email', 'max:255'],
            'parking_lot_name' => ['required', 'string', 'max:255'],
            'address'          => ['required', 'string'],
            'description'      => ['nullable', 'string'],
            'estimated_slots'  => ['required', 'integer', 'min:1', 'max:10000'],
            'document'         => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $documentPath = $application->document_path;
        if ($request->hasFile('document')) {
            // Remove old file
            if ($documentPath) {
                Storage::disk('public')->delete($documentPath);
            }
            $documentPath = $request->file('document')->store('owner-applications', 'public');
        }

        DB::transaction(function () use ($user, $application, $data, $documentPath) {
            $application->update([
                'business_name'    => $data['business_name'],
                'contact_name'     => $data['contact_name'],
                'phone'            => $data['phone'],
                'email'            => $data['email'],
                'parking_lot_name' => $data['parking_lot_name'],
                'address'          => $data['address'],
                'description'      => $data['description'] ?? null,
                'estimated_slots'  => $data['estimated_slots'],
                'document_path'    => $documentPath,
                'status'           => 'pending',
                'rejection_reason' => null,
                'reviewed_by'      => null,
                'reviewed_at'      => null,
            ]);

            $user->forceFill(['owner_status' => 'pending'])->save();
        });

        // Notify user
        notify_user($user->id, 'ส่งคำขอใหม่แล้ว', 'คำขอที่แก้ไขของคุณอยู่ระหว่างการพิจารณาอีกครั้ง');

        // Notify admins
        $adminIds = User::where('role', 'admin')->pluck('id');
        foreach ($adminIds as $adminId) {
            notify_user($adminId, 'คำขอเจ้าของลานจอด (ส่งใหม่)',
                "ผู้ใช้ {$user->name} ส่งคำขอเป็นเจ้าของลานจอดใหม่อีกครั้ง");
        }

        return redirect()->route('owner.dashboard')
            ->with('success', 'ส่งคำขอใหม่เรียบร้อยแล้ว! กรุณารอการพิจารณา');
    }
}
