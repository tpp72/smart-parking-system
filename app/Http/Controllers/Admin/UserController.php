<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private array $roles = ['user', 'admin'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $users = User::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($role, fn($query) => $query->where('role', $role))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'role'));
    }

    public function edit(User $user)
    {
        $roles = $this->roles;
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => ['required', Rule::in($this->roles)],
        ]);

        // กัน admin เผลอปรับตัวเองเป็น user แล้วล็อกตัวเองออกจาก admin
        if ($request->user()->id === $user->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'ไม่สามารถเปลี่ยน role ของตัวเองจาก admin เป็น user ได้'])->withInput();
        }

        $user->update($data);

        admin_audit('user.update', $user, [
            'changed' => array_keys($data),
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'อัปเดตผู้ใช้เรียบร้อยแล้ว');
    }

    public function forceReset(Request $request, User $user)
    {
        $data = $request->validate([
            'temporary_password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['temporary_password']),
            'force_password_reset' => true,
        ])->save();

        admin_audit('user.force_reset', $user, [
            'force_password_reset' => true,
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'ตั้งรหัสชั่วคราวและบังคับให้เปลี่ยนรหัสผ่านแล้ว');
    }
}
