<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    private array $roles = ['user', 'owner', 'admin'];

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

    public function create()
    {
        $roles = $this->roles;
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role'     => ['required', Rule::in($this->roles)],
        ]);

        $user = User::create([
            'name'                 => $data['name'],
            'email'                => $data['email'],
            'password'             => Hash::make($data['password']),
            'role'                 => $data['role'],
            'owner_status'         => $data['role'] === 'owner' ? 'approved' : null,
            'email_verified_at'    => now(),
            'force_password_reset' => true,
        ]);

        audit_log('user.create', $user, ['role' => $user->role]);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "สร้างผู้ใช้ \"{$user->name}\" (role: {$user->role}) เรียบร้อยแล้ว — บังคับให้เปลี่ยนรหัสผ่านเมื่อเข้าสู่ระบบครั้งแรก");
    }

    public function edit(User $user)
    {
        $roles = $this->roles;
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $isDemoting = $user->role === 'owner' && $request->input('role') === 'user';

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'             => ['required', Rule::in($this->roles)],
            'demotion_reason'  => $isDemoting ? ['required', 'string', 'max:1000'] : ['nullable'],
        ]);

        if ($request->user()->id === $user->id && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'ไม่สามารถเปลี่ยน role ของตัวเองออกจาก admin ได้'])->withInput();
        }

        $before = $user->only(['name', 'email', 'role']);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ]);

        // การเปลี่ยน Role อยู่ใน changes.role
        $auditExtra = ['changes' => audit_changes($before, $user)];
        if ($isDemoting) {
            $auditExtra['demotion_reason'] = $data['demotion_reason'];
        }

        audit_log('user.update', $user, $auditExtra);

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

        audit_log('user.force_reset', $user, [
            'force_password_reset' => true,
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'ตั้งรหัสชั่วคราวและบังคับให้เปลี่ยนรหัสผ่านแล้ว');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return back()->withErrors(['error' => 'ไม่สามารถลบบัญชีของตัวเองได้']);
        }

        $ownedLots = $user->ownedParkingLots;

        // กันลบถ้ามีรถกำลังจอดอยู่จริงในลานของ owner คนนี้ (ต้อง check-out ก่อน)
        $lotWithActiveCar = $ownedLots->first(
            fn($lot) => ParkingLog::where('parking_lot_id', $lot->id)->whereNull('check_out_time')->exists()
        );

        if ($lotWithActiveCar) {
            return back()->withErrors([
                'error' => "ไม่สามารถลบผู้ใช้นี้ได้ เนื่องจากลานจอด \"{$lotWithActiveCar->name}\" มีรถกำลังจอดอยู่ กรุณา Check-Out ให้เรียบร้อยก่อน",
            ]);
        }

        $deletedLotsCount = 0;
        $cancelledReservationsCount = 0;

        DB::transaction(function () use ($user, $ownedLots, &$deletedLotsCount, &$cancelledReservationsCount) {
            foreach ($ownedLots as $lot) {
                // แจ้งเตือนผู้จองที่ยัง pending/confirmed ก่อนลบลาน — ตัว reservation เองจะถูก
                // cascade delete ไปพร้อม parking_lot จึงไม่ต้องอัปเดตสถานะ/คืน slot/บันทึก log
                $affectedReservations = Reservation::where('parking_lot_id', $lot->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->get(['id', 'user_id']);

                foreach ($affectedReservations as $reservation) {
                    notify_user(
                        $reservation->user_id,
                        'การจองถูกยกเลิก',
                        "การจอง #{$reservation->id} ที่ลานจอด \"{$lot->name}\" ถูกยกเลิก เนื่องจากลานจอดปิดให้บริการ"
                    );
                    $cancelledReservationsCount++;
                }

                // ลบลานจอด — cascade ลบช่องจอด, reservation, parking log และ payment ของลานนี้
                $lot->delete();

                $deletedLotsCount++;
            }

            audit_log('user.delete', $user, [
                'email'                   => $user->email,
                'role'                    => $user->role,
                'lots_deleted'            => $deletedLotsCount,
                'reservations_cancelled'  => $cancelledReservationsCount,
            ]);

            $user->delete();
        });

        $extra = $deletedLotsCount > 0
            ? " (ลบลานจอด {$deletedLotsCount} แห่ง, ยกเลิกการจอง {$cancelledReservationsCount} รายการ)"
            : '';

        return redirect()->route('admin.users.index')
            ->with('success', "ลบผู้ใช้ \"{$user->name}\" เรียบร้อยแล้ว{$extra}");
    }
}
