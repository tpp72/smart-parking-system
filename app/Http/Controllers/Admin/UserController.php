<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserAccountService;
use App\Support\Navigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Admin จัดการผู้ใช้ (project-plan.md §5.1, §5.1.1)
 * - เป็น Owner ได้ผ่านคำขอสมัคร Owner เท่านั้น · ปลด Owner = ปิดลานทั้งหมดแบบเดียวกับอนุมัติคำร้องลาออก
 * - บัญชีระบบ (Walkin User) ไม่แสดงและจัดการไม่ได้
 */
class UserController extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    /** Role ที่ Admin เลือกได้ — Owner เลือกได้เฉพาะคงเป็น Owner หรือปลดเป็น User */
    private function rolesFor(?User $user): array
    {
        return $user?->role === 'owner' ? ['owner', 'user'] : ['user', 'admin'];
    }

    private function assertManageable(User $user): void
    {
        abort_if($user->is_system, 404);
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $users = User::query()
            ->where('is_system', false)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($role, fn($query) => $query->where('role', $role))
            ->withCount('ownedParkingLots')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'q', 'role'));
    }

    public function create()
    {
        $roles = $this->rolesFor(null);
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => ['required', Rule::in($this->rolesFor(null))],
        ], [
            'role.in' => 'สร้างได้เฉพาะผู้ใช้หรือผู้ดูแลระบบ — การเป็นเจ้าของลานต้องผ่านคำขอเป็นเจ้าของลาน',
        ]);

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'role'                 => $data['role'],
            'owner_status'         => null,
            'email_verified_at'    => now(),
            'force_password_reset' => true,
        ]);

        audit_log('user.create', $user, ['role' => $user->role]);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "สร้างบัญชี \"{$user->name}\" (" . (Navigation::ROLE_LABELS[$user->role] ?? $user->role) . ") เรียบร้อยแล้ว — บังคับให้เปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก");
    }

    public function edit(User $user)
    {
        $this->assertManageable($user);

        $roles = $this->rolesFor($user);
        $ownedLotsCount = $user->role === 'owner' ? $user->ownedParkingLots()->count() : 0;

        // ผลกระทบถ้าลบบัญชี: การจองของผู้ใช้ที่ยังไม่ Check-in จะถูกยกเลิก · รถที่จอดอยู่จะถูก Check-out
        $impact = [
            'bookings' => $user->reservations()->whereIn('status', ['pending', 'confirmed'])->count(),
            'parked'   => $user->reservations()->where('status', 'checked_in')->count(),
        ];

        return view('admin.users.edit', compact('user', 'roles', 'ownedLotsCount', 'impact'));
    }

    public function update(Request $request, User $user)
    {
        $this->assertManageable($user);

        $isDemoting = $user->role === 'owner' && $request->input('role') === 'user';

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'            => ['required', Rule::in($this->rolesFor($user))],
            'demotion_reason' => $isDemoting ? ['required', 'string', 'max:1000'] : ['nullable'],
        ], [
            'role.in'                  => $user->role === 'owner'
                ? 'เจ้าของลานเปลี่ยนได้เฉพาะปลดกลับเป็นผู้ใช้'
                : 'การเป็นเจ้าของลานต้องผ่านคำขอเป็นเจ้าของลาน',
            'demotion_reason.required' => 'กรุณาระบุเหตุผลในการปลดเจ้าของลาน',
        ]);

        if ($request->user()->id === $user->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'เปลี่ยนบทบาทของบัญชีตัวเองออกจากผู้ดูแลระบบไม่ได้'])->withInput();
        }

        $before = $user->only(['name', 'email', 'role']);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            // การปลด Owner เปลี่ยน Role ใน UserAccountService หลังปิดลาน
            'role'  => $isDemoting ? $user->role : $data['role'],
        ]);

        $changes = audit_changes($before, $user);
        if ($changes) {
            audit_log('user.update', $user, ['changes' => $changes]);
        }

        if (!$isDemoting) {
            return redirect()->route('admin.users.edit', $user)->with('success', 'อัปเดตผู้ใช้เรียบร้อยแล้ว');
        }

        $result = $this->accounts->demoteOwner($user, $request->user(), $data['demotion_reason']);

        if (!$result['success']) {
            return back()->withErrors(['role' => $result['error']])->withInput();
        }

        return redirect()->route('admin.users.edit', $user)->with('success', sprintf(
            'ปลดเจ้าของลานกลับเป็นผู้ใช้แล้ว — ยกเลิกการจอง %d รายการ · เช็คเอาท์รถ %d คัน · ลบลานจอด %d แห่ง',
            $result['summary']['reservations_cancelled'],
            $result['summary']['cars_checked_out'],
            $result['summary']['lots_deleted']
        ));
    }

    public function forceReset(Request $request, User $user)
    {
        $this->assertManageable($user);

        $data = $request->validate([
            'temporary_password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $user->forceFill([
            'password' => Hash::make($data['temporary_password']),
            'force_password_reset' => true,
        ])->save();

        audit_log('user.force_reset', $user, [
            'force_password_reset' => true,
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'ตั้งรหัสชั่วคราวและบังคับให้เปลี่ยนรหัสผ่านแล้ว');
    }

    public function destroy(Request $request, User $user)
    {
        $this->assertManageable($user);

        $result = $this->accounts->delete($user, $request->user());

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        $summary = $result['summary'];
        $extra = array_sum($summary) > 0
            ? sprintf(' (ยกเลิกการจอง %d รายการ · เช็คเอาท์รถ %d คัน · ลบลานจอด %d แห่ง)',
                $summary['reservations_cancelled'], $summary['cars_checked_out'], $summary['lots_deleted'])
            : '';

        return redirect()->route('admin.users.index')
            ->with('success', "ลบผู้ใช้ \"{$user->name}\" เรียบร้อยแล้ว{$extra}");
    }
}
