<?php

namespace App\Services;

use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Models\User;

/**
 * ปิดลานทั้งหมดของ Owner (project-plan.md §16) — ใช้ร่วมกันเมื่ออนุมัติคำร้องลาออก, Admin ปลด Owner และ Admin ลบบัญชี Owner
 * ยกเลิกการจองที่ยังไม่ Check-in → ระบบ Check-out รถที่จอดอยู่ → แจ้งผู้จองให้ติดต่อ Admin → ลบลาน (ข้อมูลของลานถูกลบตาม)
 *
 * ต้องเรียกภายใน DB::transaction ของผู้เรียก
 */
class OwnerLotClosureService
{
    public function __construct(
        private ReservationService $reservations,
        private CheckOutService $checkOut,
    ) {}

    /**
     * @param string $cancelNote หมายเหตุใน Reservation Log ของการจองที่ถูกยกเลิก
     * @param array  $auditMeta  ข้อมูลเพิ่มใน Audit Log `parking_lot.delete` (เช่น reason)
     * @return array{reservations_cancelled: int, cars_checked_out: int, lots_deleted: int}
     */
    public function closeAll(User $owner, User $actor, string $cancelNote, array $auditMeta = []): array
    {
        $lots = ParkingLot::ownedBy($owner->id)->orderBy('id')->get();
        $cancelled = 0;
        $checkedOut = 0;

        foreach ($lots as $lot) {
            $closure = "ลาน {$lot->name} ยุติการให้บริการ กรุณาติดต่อ Admin";

            // 1. ยกเลิกการจองที่ยังไม่ Check-in (มัดจำที่ยังไม่ชำระ → void)
            $bookings = Reservation::where('parking_lot_id', $lot->id)->whereIn('status', ['pending', 'confirmed'])->orderBy('id')->get();
            foreach ($bookings as $booking) {
                $cancel = $this->reservations->cancel(
                    $booking,
                    $actor,
                    $cancelNote,
                    notification: "การจอง #{$booking->id} ถูกยกเลิก เนื่องจาก{$closure}"
                );
                $cancelled += (int) $cancel['success'];
            }

            // 2. ระบบ Check-out รถที่จอดอยู่ แล้วแจ้งให้ติดต่อ Admin แทนสรุปยอด (Payment ถูกลบพร้อมลาน)
            $parked = Reservation::where('parking_lot_id', $lot->id)->where('status', 'checked_in')->orderBy('id')->get();
            foreach ($parked as $reservation) {
                if ($this->checkOut->checkOut($reservation, null, notifyUser: false)['success']) {
                    $checkedOut++;
                    notify_user($reservation->user_id, 'รถของคุณถูกเช็คเอาท์โดยระบบ',
                        "ทะเบียน {$reservation->license_plate} (การจอง #{$reservation->id}) ถูกเช็คเอาท์โดยระบบ เนื่องจาก{$closure}");
                }
            }

            // 3. ลบลาน — ช่องจอด การจอง ประวัติการจอด และ Payment ของลานถูกลบตาม
            $lot->delete();

            audit_by($actor, 'parking_lot.delete', $lot, ['name' => $lot->name, 'owner_id' => $owner->id] + $auditMeta);
        }

        return [
            'reservations_cancelled' => $cancelled,
            'cars_checked_out'       => $checkedOut,
            'lots_deleted'           => $lots->count(),
        ];
    }
}
