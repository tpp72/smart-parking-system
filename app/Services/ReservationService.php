<?php

namespace App\Services;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use App\Support\StatusCatalog;
use Illuminate\Support\Facades\DB;

/**
 * Reservation Flow (project-plan.md §7, §8, §13.2, §23)
 *
 * สร้าง Reservation → Deposit = hourly_rate × 1 → Deposit Payment (unpaid)
 * → Admin/Owner Mark as Paid → confirmed + ระบบจัดสรรและ Lock Slot
 *   (ลานเต็มขณะยืนยันรับเงิน → cancelled + Deposit void + แจ้ง User)
 * → ไม่ Check-in ภายใน 1 ชั่วโมงหลัง reserve_start → expired + คืน Slot + Deposit ที่ยังไม่ชำระ void
 *
 * ทุกการเปลี่ยนแปลงบันทึกทั้ง Reservation Log และ Audit Log (รวม Payment Log: payment.*)
 */
class ReservationService
{
    public const OUTCOME_CONFIRMED = 'confirmed';
    public const OUTCOME_LOT_FULL  = 'lot_full';
    public const OUTCOME_EXPIRED   = 'expired';

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

            $payment = Payment::create([
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
                'note'           => sprintf('ผู้ใช้สร้างการจอง — มัดจำ ฿%s รอยืนยันรับเงิน', number_format($deposit, 2)),
            ]);

            audit_by($user, 'reservation.create', $reservation, [
                'parking_lot_id' => $lot->id,
                'license_plate'  => $reservation->license_plate,
                'plate_province' => $reservation->plate_province,
                'reserve_start'  => $reservation->reserve_start?->toDateTimeString(),
            ]);

            audit_by($user, 'payment.deposit_created', $payment, [
                'reservation_id' => $reservation->id,
                'total_amount'   => $deposit,
            ]);

            return $reservation;
        });
    }

    /**
     * Admin/Owner ยืนยันรับเงินมัดจำ (Mark as Paid) → ยืนยันการจองและ Lock Slot ที่ระบบจัดสรรให้
     * (การจองที่เลยช่วง Check-in แล้วแต่ Scheduler ยังไม่ทำงาน → Expire แทนการยืนยัน)
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
                $result = $this->fail('รายการนี้ถูกยกเลิกแล้ว — ยืนยันรับเงินไม่ได้');
                return;
            }

            $reservation = Reservation::whereKey($payment->reservation_id)->lockForUpdate()->first();

            if (!$reservation->canTransitionTo('confirmed')) {
                $result = $this->fail("ไม่สามารถยืนยันได้ สถานะการจองปัจจุบันคือ '{$reservation->status}'");
                return;
            }

            if ($reservation->isOverdue()) {
                $this->expireLocked($reservation, $payment);

                $result = [
                    'success'     => false,
                    'outcome'     => self::OUTCOME_EXPIRED,
                    'error'       => sprintf(
                        'การจอง #%d หมดอายุแล้ว (ไม่ Check-in ภายใน %d นาทีหลังเวลาจอง) — ยกเลิกรายการมัดจำแล้ว',
                        $reservation->id, Reservation::gracePeriodMinutes()
                    ),
                    'reservation' => $reservation,
                    'slot'        => null,
                ];
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
                    'note'           => 'ลานเต็มขณะยืนยันรับเงินมัดจำ — ยกเลิกการจองอัตโนมัติ (ยกเลิกรายการมัดจำ)',
                ]);

                audit_by($actor, 'payment.void', $payment, ['reservation_id' => $reservation->id, 'reason' => 'lot_full']);
                audit_by($actor, 'reservation.cancel', $reservation, ['old_status' => 'pending', 'reason' => 'lot_full']);

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
                'note'           => sprintf('ยืนยันรับเงินมัดจำ ฿%s — ระบบจัดสรรและล็อกช่อง %s', number_format((float) $payment->total_amount, 2), $slot->slot_number),
            ]);

            audit_by($actor, 'payment.mark_paid', $payment, [
                'type'           => Payment::TYPE_DEPOSIT,
                'reservation_id' => $reservation->id,
                'total_amount'   => (float) $payment->total_amount,
            ]);
            audit_by($actor, 'reservation.confirm', $reservation, [
                'parking_slot_id' => $slot->id,
                'slot_number'     => $slot->slot_number,
            ]);

            $result = $this->done(self::OUTCOME_CONFIRMED, $reservation, $slot);
        });

        if ($result['outcome'] === self::OUTCOME_EXPIRED) {
            $this->notifyExpired($result['reservation'], Payment::STATUS_VOID);
        } elseif ($result['success']) {
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
     * @param ?string $notification ข้อความแจ้งผู้จองแทนข้อความมาตรฐาน (เช่น กรณี Owner ลาออก)
     * @return array{success: bool, error: ?string, deposit_forfeited: bool}
     */
    public function cancel(Reservation $reservation, User $actor, string $note, ?string $notification = null): array
    {
        $result = null;

        DB::transaction(function () use ($reservation, $actor, $note, &$result) {
            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

            if (!$locked->canTransitionTo('cancelled')) {
                $result = ['success' => false, 'error' => 'ยกเลิกได้เฉพาะก่อน Check-in — การจองนี้' . StatusCatalog::label('reservation', $locked->status, 'user'), 'deposit_forfeited' => false];
                return;
            }

            $oldStatus = $locked->status;
            $locked->update(['status' => 'cancelled']);

            $this->slots->releaseReserved($locked->parking_slot_id);

            $deposit = Payment::where('reservation_id', $locked->id)
                ->where('type', Payment::TYPE_DEPOSIT)
                ->lockForUpdate()
                ->first();

            $depositVoided = $deposit?->payment_status === Payment::STATUS_UNPAID;

            if ($depositVoided) {
                $deposit->update(['payment_status' => Payment::STATUS_VOID]);
            }

            ReservationLog::create([
                'reservation_id' => $locked->id,
                'old_status'     => $oldStatus,
                'new_status'     => 'cancelled',
                'changed_by'     => $actor->id,
                'note'           => $note,
            ]);

            audit_by($actor, 'reservation.cancel', $locked, [
                'old_status'     => $oldStatus,
                'deposit_status' => $deposit?->payment_status,
                'note'           => $note,
            ]);

            if ($depositVoided) {
                audit_by($actor, 'payment.void', $deposit, ['reservation_id' => $locked->id, 'reason' => 'reservation_cancelled']);
            }

            $result = [
                'success'           => true,
                'error'             => null,
                'deposit_forfeited' => $deposit?->payment_status === Payment::STATUS_PAID,
            ];
        });

        if ($result['success']) {
            $cancelledBySelf = $actor->id === $reservation->user_id;

            notify_user(
                $reservation->user_id,
                $cancelledBySelf ? 'ยกเลิกการจองเรียบร้อยแล้ว' : 'การจองถูกยกเลิก',
                $notification ?? sprintf(
                    'การจอง #%d %s%s',
                    $reservation->id,
                    $cancelledBySelf ? 'ถูกยกเลิกเรียบร้อยแล้ว' : 'ถูกยกเลิกโดยเจ้าหน้าที่',
                    $result['deposit_forfeited'] ? ' (ไม่คืนเงินมัดจำที่ชำระแล้ว)' : ''
                )
            );
        }

        $reservation->refresh();

        return $result;
    }

    /**
     * Expire Reservation ที่ไม่ Check-in ภายใน 1 ชั่วโมงหลัง reserve_start (Scheduler เรียกทุก 1 นาที)
     * คืน Slot ที่ Lock ไว้ · Deposit ที่ยังไม่ชำระ → void · ชำระแล้วไม่คืนเงิน · แจ้ง User
     *
     * @return bool false เมื่อการจองไม่เข้าเงื่อนไข Expire แล้ว (เช่น Check-in หรือยกเลิกไปก่อนได้ Lock)
     */
    public function expire(Reservation $reservation): bool
    {
        $expired = false;
        $depositStatus = null;

        DB::transaction(function () use ($reservation, &$expired, &$depositStatus) {
            // ลำดับ Lock เดียวกับ markDepositPaid (Payment → Reservation) ป้องกัน Deadlock
            $deposit = Payment::where('reservation_id', $reservation->id)
                ->where('type', Payment::TYPE_DEPOSIT)
                ->lockForUpdate()
                ->first();

            $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

            if ($locked?->isOverdue()) {
                $depositStatus = $this->expireLocked($locked, $deposit);
                $expired = true;
            }
        });

        if ($expired) {
            $this->notifyExpired($reservation->refresh(), $depositStatus);
        }

        return $expired;
    }

    /**
     * เปลี่ยนเป็น expired ภายใน Transaction ที่ Lock การจองและ Deposit ไว้แล้ว — ผู้ดำเนินการคือระบบ
     *
     * @return ?string สถานะ Deposit หลัง Expire (void / paid) · null เมื่อไม่มี Deposit
     */
    private function expireLocked(Reservation $locked, ?Payment $deposit): ?string
    {
        $oldStatus = $locked->status;
        $locked->update(['status' => 'expired']);

        $this->slots->releaseReserved($locked->parking_slot_id);

        $depositVoided = $deposit?->payment_status === Payment::STATUS_UNPAID;

        if ($depositVoided) {
            $deposit->update(['payment_status' => Payment::STATUS_VOID]);
        }

        ReservationLog::create([
            'reservation_id' => $locked->id,
            'old_status'     => $oldStatus,
            'new_status'     => 'expired',
            'changed_by'     => null,
            'note'           => sprintf(
                'หมดอายุอัตโนมัติ: ไม่ Check-in ภายใน %d นาทีหลังเวลาจอง%s',
                Reservation::gracePeriodMinutes(),
                $depositVoided ? ' (ยกเลิกรายการมัดจำ)' : ''
            ),
        ]);

        audit_by(null, 'reservation.expire', $locked, [
            'old_status'    => $oldStatus,
            'reserve_start' => $locked->reserve_start->toDateTimeString(),
        ]);

        if ($depositVoided) {
            audit_by(null, 'payment.void', $deposit, ['reservation_id' => $locked->id, 'reason' => 'reservation_expired']);
        }

        return $deposit?->payment_status;
    }

    private function notifyExpired(Reservation $reservation, ?string $depositStatus): void
    {
        if ($reservation->is_walk_in) {
            return; // Walkin User ไม่ได้รับ Notification
        }

        $depositNote = match ($depositStatus) {
            Payment::STATUS_PAID => ' — ไม่คืนเงินมัดจำที่ชำระแล้ว',
            Payment::STATUS_VOID => ' — ยกเลิกรายการเงินมัดจำที่ยังไม่ชำระ',
            default              => '',
        };

        notify_user(
            $reservation->user_id,
            'การจองหมดอายุ',
            sprintf(
                'การจอง #%d หมดอายุแล้ว เนื่องจากไม่มีการเช็คอินภายใน %d นาทีหลังเวลาจอง (%s)%s',
                $reservation->id,
                Reservation::gracePeriodMinutes(),
                $reservation->reserve_start->format('d/m/Y H:i'),
                $depositNote
            )
        );
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
