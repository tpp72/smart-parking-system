<?php

namespace App\Services;

use App\Models\ParkingSlot;
use App\Models\Reservation;

/**
 * จัดสรรช่องจอดอัตโนมัติ (project-plan.md §6.3, §24) — User เลือกได้เฉพาะลาน ระบบเป็นผู้เลือกช่อง
 *
 * ทุก method ที่ lock แถวต้องถูกเรียกภายใน DB::transaction
 */
class SlotAllocator
{
    /**
     * Lock ช่องว่าง (available) ของลาน 1 ช่อง
     *
     * ใช้ SKIP LOCKED: transaction ที่มาพร้อมกันจะข้ามช่องที่อีกรายการกำลังถือ lock อยู่
     * แล้วได้ช่องถัดไป — ไม่มีสองรายการได้ช่องเดียวกัน และไม่เข้าใจผิดว่าลานเต็มระหว่างรอ lock
     */
    public function lockAvailableSlot(int $lotId): ?ParkingSlot
    {
        return ParkingSlot::where('parking_lot_id', $lotId)
            ->where('status', 'available')
            ->orderBy('slot_number')
            ->orderBy('id')
            ->lock('for update skip locked')
            ->first();
    }

    /** Lock ช่องที่ถูกจองไว้ให้ Reservation นี้ (status = reserved) */
    public function lockReservedSlotFor(Reservation $reservation): ?ParkingSlot
    {
        if (!$reservation->parking_slot_id) {
            return null;
        }

        return ParkingSlot::whereKey($reservation->parking_slot_id)
            ->where('parking_lot_id', $reservation->parking_lot_id)
            ->where('status', 'reserved')
            ->lockForUpdate()
            ->first();
    }

    /** คืนช่องที่ Lock ไว้ (reserved → available) — ไม่แตะช่องที่มีรถจอดอยู่ */
    public function releaseReserved(?int $slotId): void
    {
        if (!$slotId) {
            return;
        }

        ParkingSlot::whereKey($slotId)
            ->where('status', 'reserved')
            ->update(['status' => 'available']);
    }
}
