<?php

namespace App\Services;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reservation Flow (project-plan.md §7, §8, §13.2)
 *
 * สร้าง Reservation → Deposit = hourly_rate × 1 → Deposit Payment (unpaid)
 * → Admin/Owner Mark as Paid → confirmed + ระบบจัดสรรและ Lock Slot
 *   (ลานเต็มขณะยืนยันรับเงิน → cancelled + Deposit void + แจ้ง User)
 */
class ReservationService
{
    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_LOT_FULL  = 'lot_full';

    public function __construct(private SlotAllocator $slots) {}

    /**
     * สร้าง Reservation (pending — ยังไม่ถือครอง Slot) พร้อม Deposit Payment ที่รอการยืนยันรับเงิน
     *
     * @param array{license_plate: string, plate_province: string, brand: string, color: string, reserve_start: mixed} $attributes
     *
     * @throws \Illuminate\Database\UniqueConstraintViolationException ทะเบียน + จังหวัดนี้มี Reservation active อยู่แล้ว
     */
    public function create(User $user, ParkingLot $lot, array $attributes): Reservation
    {
        return DB::transaction(function () use ($user, $lot, $attributes) {
            $deposit = Reservation::depositFor($lot);

            $reservation = Reservation::create([
                'user_id'         => $user->id,
                'parking_lot_id'  => $lot->id,
                'parking_slot_id' => null,
                'license_plate'   => $attributes['license_plate'],
                'plate_province'  => $attributes['plate_province'],
                'brand'           => $attributes['brand'],
                'color'           => $attributes['color'],
                'reserve_start'   => $attributes['reserve_start'],
                'deposit_amount'  => $deposit,
                'reservation_fee' => $lot->hourly_rate, // ส่วนลด = hourly_rate
                'status'          => 'pending',
            ]);

            Payment::create([
                'type'           => Payment::TYPE_DEPOSIT,
                'reservation_id' => $reservation->id,
                'hourly_rate'    => $lot->hourly_rate,
                'total_amount'   => $deposit,
                'payment_status' => Payment::STATUS_UNPAID,
            ]);

            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'old_status'     => null,
                'new_status'     => 'pending',
                'changed_by'     => $user->id,
                'note'           => sprintf('User สร้างการจอง — มัดจำ ฿%s รอยืนยันรับเงิน', number_format($deposit, 2)),
            ]);

            return $reservation;
        });
    }

    /**
     * Admin/Owner ยืนยันรับเงินมัดจำ (Mark as Paid) → ยืนยันการจองและ Lock Slot ที่ระบบจัดสรรให้
     *
     * @return array{success: bool, outcome: ?string, error: ?string, reservation: ?Reservation, slot: ?ParkingSlot}
     */
    public function markDepositPaid(Payment $payment, User $actor): array
    {
        $result = null;

        DB::transaction(function () use ($payment, $actor, &$result) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->type !== Payment::TYPE_DEPOSIT) {
                $result = $this->fail('รายการนี้ไม่ใช่เงินมัดจำ');
                return;
            }
            if ($payment->payment_status === Payment::STATUS_PAID) {
                $result = $this->fail('รายการนี้ยืนยันรับเงินแล้ว');
                return;
            }
            if ($payment->payment_status === Payment::STATUS_VOID) {
                $result = $this->fail('รายการนี้ถูกยกเลิกแล้ว (void) — ไม่สามารถยืนยันรับเงินได้');
                return;
            }

            $reservation = Reservation::whereKey($payment->reservation_id)->lockForUpdate()->first();

            if (!$reservation->canTransitionTo('confirmed')) {
                $result = $this->fail("ไม่สามารถยืนยันได้ สถานะการจองปัจจุบันคือ '{$reservation->status}'");
                return;
            }

            $slot = $this->slots->lockAvailableSlot($reservation->parking_lot_id);

            if (!$slot) {
                // ลานเต็มขณะยืนยันรับเงิน → ยกเลิกการจอง + Deposit void
                $payment->update(['payment_status' => Payment::STATUS_VOID]);
                $reservation->update(['status' => 'cancelled', 'parking_slot_id' => null]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => 'pending',
                    'new_status'     => 'cancelled',
                    'changed_by'     => $actor->id,
                    'note'           => 'ลานเต็มขณะยืนยันรับเงินมัดจำ — ยกเลิกการจองอัตโนมัติ (มัดจำ void)',
                ]);

                $result = $this->done(self::OUTCOME_LOT_FULL, $reservation, null);
                return;
            }

            $payment->update([
                'payment_status' => Payment::STATUS_PAID,
                'paid_by'        => $actor->id,
                'paid_at'        => now(),
            ]);

            $slot->update(['status' => 'reserved']);
            $reservation->update(['status' => 'confirmed', 'parking_slot_id' => $slot->id]);

            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'old_status'     => 'pending',
                'new_status'     => 'confirmed',
                'changed_by'     => $actor->id,
                'note'           => sprintf('ยืนยันรับเงินมัดจำ ฿%s — ระบบจัดสรรและ Lock ช่อง %s', number_format((float) $payment->total_amount, 2), $slot->slot_number),
            ]);

            $result = $this->done(self::OUTCOME_CONFIRMED, $reservation, $slot);
        });

        if ($result['success']) {
            $reservation = $result['reservation'];

            if ($result['outcome'] === self::OUTCOME_CONFIRMED) {
                notify_user(
                    $reservation->user_id,
                    'การจองได้รับการยืนยัน',
                    "ยืนยันรับเงินมัดจำแล้ว การจอง #{$reservation->id} ได้รับการยืนยัน (ช่องจอด {$result['slot']->slot_number}) กรุณาเช็คอินภายในเวลาที่กำหนด"
                );
            } else {
                notify_user(
                    $reservation->user_id,
                    'การจองถูกยกเลิก',
                    "การจอง #{$reservation->id} ถูกยกเลิก เนื่องจากลานจอดเต็มขณะยืนยันรับเงินมัดจำ"
                );
            }
        }

        return $result;
    }

    /**
     * ยกเลิก Reservation ก่อน Check-in — คืน Slot ที่ Lock ไว้ · Deposit ที่ยังไม่ชำระ → void · ชำระแล้วไม่คืนเงิน
     *
     * @return array{success: bool, error: ?string, deposit_forfeited: bool}
     */
    public function cancel(Reservation $reservation, User $actor, string $note): array
    {
        $result = null;

        DB::transaction(function () use ($reservation, $actor, $note, &$result) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

            if (!$locked->canTransitionTo('cancelled')) {
                $result = ['success' => false, 'error' => "ไม่สามารถยกเลิกการจองที่มีสถานะ \"{$locked->status}\" ได้", 'deposit_forfeited' => false];
                return;
            }

            $oldStatus = $locked->status;
            $locked->update(['status' => 'cancelled']);

            $this->slots->releaseReserved($locked->parking_slot_id);

            $deposit = Payment::where('reservation_id', $locked->id)
                ->where('type', Payment::TYPE_DEPOSIT)
                ->lockForUpdate()
                ->first();

            if ($deposit?->payment_status === Payment::STATUS_UNPAID) {
                $deposit->update(['payment_status' => Payment::STATUS_VOID]);
            }

            ReservationLog::create([
                'reservation_id' => $locked->id,
                'old_status'     => $oldStatus,
                'new_status'     => 'cancelled',
                'changed_by'     => $actor->id,
                'note'           => $note,
            ]);

            $result = [
                'success'           => true,
                'error'             => null,
                'deposit_forfeited' => $deposit?->payment_status === Payment::STATUS_PAID,
            ];
        });

        $reservation->refresh();

        return $result;
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'outcome' => null, 'error' => $message, 'reservation' => null, 'slot' => null];
    }

    private function done(string $outcome, Reservation $reservation, ?ParkingSlot $slot): array
    {
        return ['success' => true, 'outcome' => $outcome, 'error' => null, 'reservation' => $reservation, 'slot' => $slot];
    }
}
