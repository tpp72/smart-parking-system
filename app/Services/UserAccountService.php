<?php

namespace App\Services;

use App\Models\OwnerResignation;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin จัดการบัญชีผู้ใช้ที่มีผลต่อข้อมูลการจอง/ลานจอด (project-plan.md §5.1.1)
 * - ปลด Owner → User: ปิดลานทั้งหมดของ Owner แบบเดียวกับอนุมัติคำร้องลาออก
 * - ลบบัญชี: เคลียร์การจองค้าง/รถที่จอดอยู่ของผู้ใช้ (และปิดลานถ้าเป็น Owner) ก่อนลบ เพื่อไม่ให้ช่องจอดค้างสถานะ
 * - ผู้ใช้ลบบัญชีตัวเอง: เฉพาะ Role User · ยกเลิกการจองที่ยังไม่ Check-in ทั้งหมด · มีรถจอดอยู่ลบไม่ได้
 */
class UserAccountService
{
    public function __construct(
        private OwnerLotClosureService $lotClosure,
        private ReservationService $reservations,
        private CheckOutService $checkOut,
    ) {}

    /**
     * @return array{success: bool, error: ?string, summary: ?array{reservations_cancelled: int, cars_checked_out: int, lots_deleted: int}}
     */
    public function demoteOwner(User $owner, User $admin, string $reason): array
    {
        $result = DB::transaction(function () use ($owner, $admin, $reason) {
            $locked = User::whereKey($owner->id)->lockForUpdate()->first();

            if ($locked->role !== 'owner') {
                return ['success' => false, 'error' => 'ผู้ใช้นี้ไม่ได้เป็นเจ้าของลานจอด', 'summary' => null];
            }

            $summary = $this->lotClosure->closeAll($locked, $admin, 'ผู้ดูแลระบบปลดเจ้าของลาน — ยกเลิกการจองอัตโนมัติ', [
                'reason' => 'owner_demoted',
            ]);

            $locked->forceFill(['role' => 'user', 'owner_status' => null])->save();

            // คำร้องลาออกที่ค้างอยู่ถือว่าดำเนินการแล้ว
            OwnerResignation::where('user_id', $locked->id)->pending()->get()->each->update([
                'status'      => OwnerResignation::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'result'      => $summary,
            ]);

            audit_by($admin, 'user.demote_owner', $locked, $summary + ['reason' => $reason]);

            return ['success' => true, 'error' => null, 'summary' => $summary];
        });

        if ($result['success']) {
            notify_user($owner->id, 'บัญชีของคุณถูกปลดจากการเป็นเจ้าของลานจอด', sprintf(
                'ผู้ดูแลระบบปลดบัญชีของคุณจากการเป็นเจ้าของลานจอด เหตุผล: %s — ลานจอด %d แห่งถูกลบ และบัญชีกลับเป็นผู้ใช้',
                $reason,
                $result['summary']['lots_deleted']
            ));
        }

        return $result;
    }

    /**
     * @return array{success: bool, error: ?string, summary: ?array{reservations_cancelled: int, cars_checked_out: int, lots_deleted: int}}
     */
    public function delete(User $user, User $admin): array
    {
        if ($user->is_system) {
            return ['success' => false, 'error' => 'ไม่สามารถลบบัญชีระบบได้', 'summary' => null];
        }

        if ($user->id === $admin->id) {
            return ['success' => false, 'error' => 'ไม่สามารถลบบัญชีของตัวเองได้', 'summary' => null];
        }

        $summary = DB::transaction(function () use ($user, $admin) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            $summary = $locked->role === 'owner'
                ? $this->lotClosure->closeAll($locked, $admin, 'ผู้ดูแลระบบลบบัญชีเจ้าของลาน — ยกเลิกการจองอัตโนมัติ', ['reason' => 'user_deleted'])
                : ['reservations_cancelled' => 0, 'cars_checked_out' => 0, 'lots_deleted' => 0];

            // การจองของผู้ใช้เอง (ในฐานะผู้จอง) — คืนช่องจอดก่อนการจองถูกลบตามบัญชี
            $bookings = Reservation::where('user_id', $locked->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->get();
            foreach ($bookings as $booking) {
                $summary['reservations_cancelled'] += (int) $this->reservations->cancel($booking, $admin, 'ผู้ดูแลระบบลบบัญชีผู้ใช้')['success'];
            }

            $parked = Reservation::where('user_id', $locked->id)->where('status', 'checked_in')->orderBy('id')->get();
            foreach ($parked as $reservation) {
                $summary['cars_checked_out'] += (int) $this->checkOut->checkOut($reservation, $admin, notifyUser: false)['success'];
            }

            audit_by($admin, 'user.delete', $locked, ['email' => $locked->email, 'role' => $locked->role] + $summary);

            $locked->delete();

            return $summary;
        });

        return ['success' => true, 'error' => null, 'summary' => $summary];
    }

    /** เหตุที่บัญชีนี้ลบตัวเองจากหน้าโปรไฟล์ไม่ได้ (null = ลบได้) */
    public function selfDeletionBlocker(User $user): ?string
    {
        return match (true) {
            (bool) $user->is_system => 'ไม่สามารถลบบัญชีระบบได้',
            $user->role === 'admin' => 'บัญชีผู้ดูแลระบบลบตัวเองไม่ได้ ให้ผู้ดูแลระบบคนอื่นเป็นผู้ลบ',
            $user->role === 'owner' => 'เจ้าของลานต้องยื่นคำร้องลาออกและได้รับอนุมัติก่อน จึงจะลบบัญชีได้',
            Reservation::where('user_id', $user->id)->where('status', 'checked_in')->exists()
                => 'ยังมีรถของคุณจอดอยู่ในลาน ลบบัญชีได้หลัง Check-out แล้ว',
            default => null,
        };
    }

    /**
     * ผู้ใช้ลบบัญชีตัวเอง (หน้าโปรไฟล์) — เฉพาะ Role User ที่ไม่มีรถจอดอยู่
     * ยกเลิกการจอง pending / confirmed ทั้งหมดก่อน (คืนช่องจอด · มัดจำที่ยังไม่ชำระ → void)
     * $beforeDelete ทำงานก่อนลบแถวผู้ใช้ — เช่น Logout ซึ่ง Audit ต้องอ้างถึงบัญชีที่ยังอยู่
     *
     * @return array{success: bool, error: ?string, summary: ?array{reservations_cancelled: int}}
     */
    public function deleteSelf(User $user, ?callable $beforeDelete = null): array
    {
        return DB::transaction(function () use ($user, $beforeDelete) {
            $locked = User::whereKey($user->id)->lockForUpdate()->first();

            if ($error = $this->selfDeletionBlocker($locked)) {
                return ['success' => false, 'error' => $error, 'summary' => null];
            }

            $cancelled = 0;
            $bookings = Reservation::where('user_id', $locked->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->get();
            foreach ($bookings as $booking) {
                $cancelled += (int) $this->reservations->cancel($booking, $locked, 'ผู้ใช้ลบบัญชี')['success'];
            }

            audit_by($locked, 'profile.delete', $locked, ['email' => $locked->email, 'role' => $locked->role, 'reservations_cancelled' => $cancelled]);

            if ($beforeDelete) {
                $beforeDelete();
            }

            $locked->delete();

            return ['success' => true, 'error' => null, 'summary' => ['reservations_cancelled' => $cancelled]];
        });
    }
}
