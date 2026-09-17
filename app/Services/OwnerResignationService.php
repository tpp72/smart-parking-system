<?php

namespace App\Services;

use App\Models\OwnerResignation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * คำร้องลาออกของ Owner (project-plan.md §16, §16.1)
 *
 * Owner ยื่นคำร้อง (ยังเป็น Owner ระหว่างรอ) → Admin อนุมัติ
 * → ปิดลานทั้งหมดของ Owner (OwnerLotClosureService) → Owner กลับเป็น User
 */
class OwnerResignationService
{
    public function __construct(private OwnerLotClosureService $lotClosure) {}

    /** @return array{success: bool, error: ?string, resignation: ?OwnerResignation} */
    public function submit(User $owner, string $reason): array
    {
        if ($owner->role !== 'owner') {
            return ['success' => false, 'error' => 'เฉพาะเจ้าของลานจอดเท่านั้นที่ยื่นคำร้องลาออกได้', 'resignation' => null];
        }

        if ($owner->ownerResignations()->pending()->exists()) {
            return ['success' => false, 'error' => 'คุณมีคำร้องลาออกที่รอการพิจารณาอยู่แล้ว', 'resignation' => null];
        }

        try {
            $resignation = DB::transaction(fn () => OwnerResignation::create([
                'user_id' => $owner->id,
                'reason'  => $reason,
                'status'  => OwnerResignation::STATUS_PENDING,
            ]));
        } catch (UniqueConstraintViolationException) {
            return ['success' => false, 'error' => 'คุณมีคำร้องลาออกที่รอการพิจารณาอยู่แล้ว', 'resignation' => null];
        }

        audit_by($owner, 'owner_resignation.submit', $resignation, [
            'reason' => $reason,
            'lots'   => $owner->ownedParkingLots()->count(),
        ]);

        foreach (User::where('role', 'admin')->pluck('id') as $adminId) {
            notify_user($adminId, 'คำร้องลาออกของเจ้าของลาน',
                "{$owner->name} ({$owner->email}) ยื่นคำร้องลาออกจากการเป็นเจ้าของลานจอด เหตุผล: {$reason} — รอการพิจารณา");
        }

        return ['success' => true, 'error' => null, 'resignation' => $resignation];
    }

    /**
     * @return array{success: bool, error: ?string, summary: ?array{reservations_cancelled: int, cars_checked_out: int, lots_deleted: int}}
     */
    public function approve(OwnerResignation $resignation, User $admin): array
    {
        $result = DB::transaction(function () use ($resignation, $admin) {
            $locked = OwnerResignation::whereKey($resignation->id)->lockForUpdate()->first();

            if (!$locked->isPending()) {
                return ['success' => false, 'error' => 'คำร้องนี้ได้รับการพิจารณาแล้ว', 'summary' => null];
            }

            $owner = User::whereKey($locked->user_id)->lockForUpdate()->first();

            if ($owner->role !== 'owner') {
                return ['success' => false, 'error' => 'ผู้ใช้นี้ไม่ได้เป็นเจ้าของลานจอดแล้ว', 'summary' => null];
            }

            $summary = $this->lotClosure->closeAll($owner, $admin, 'เจ้าของลานลาออก — ยกเลิกการจองอัตโนมัติ', [
                'reason'               => 'owner_resignation',
                'owner_resignation_id' => $locked->id,
            ]);

            // Owner กลับเป็น User
            $owner->forceFill(['role' => 'user', 'owner_status' => null])->save();

            $locked->update([
                'status'      => OwnerResignation::STATUS_APPROVED,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'result'      => $summary,
            ]);

            audit_by($admin, 'owner_resignation.approve', $locked, $summary + ['user_id' => $owner->id]);

            return ['success' => true, 'error' => null, 'summary' => $summary, 'owner' => $owner];
        });

        if ($result['success']) {
            notify_user($result['owner']->id, 'คำร้องลาออกได้รับการอนุมัติ', sprintf(
                'คำร้องลาออกจากการเป็นเจ้าของลานจอดได้รับการอนุมัติแล้ว บัญชีของคุณกลับเป็นผู้ใช้ (ลบลานจอด %d แห่ง)',
                $result['summary']['lots_deleted']
            ));

            unset($result['owner']);
        }

        return $result;
    }

    /** @return array{success: bool, error: ?string} */
    public function reject(OwnerResignation $resignation, User $admin, string $reason): array
    {
        $result = DB::transaction(function () use ($resignation, $admin, $reason) {
            $locked = OwnerResignation::whereKey($resignation->id)->lockForUpdate()->first();

            if (!$locked->isPending()) {
                return ['success' => false, 'error' => 'คำร้องนี้ได้รับการพิจารณาแล้ว'];
            }

            $locked->update([
                'status'           => OwnerResignation::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by'      => $admin->id,
                'reviewed_at'      => now(),
            ]);

            audit_by($admin, 'owner_resignation.reject', $locked, ['user_id' => $locked->user_id, 'reason' => $reason]);

            return ['success' => true, 'error' => null];
        });

        if ($result['success']) {
            notify_user($resignation->user_id, 'คำร้องลาออกไม่ได้รับการอนุมัติ',
                "คำร้องลาออกจากการเป็นเจ้าของลานจอดไม่ได้รับการอนุมัติ เหตุผล: {$reason} — บัญชีของคุณยังคงเป็นเจ้าของลานจอด");
        }

        return $result;
    }
}
