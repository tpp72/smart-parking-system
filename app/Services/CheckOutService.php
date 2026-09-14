<?php

namespace App\Services;

use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Checkout Flow เดียวสำหรับ Auto Check-out (สแกนขาออก) และ Manual Check-out (project-plan.md §10.1, §12, §13.3)
 *
 * ค่าจอด   = ชั่วโมงที่จอด (ปัดขึ้นรายชั่วโมง ขั้นต่ำ 1 ชม.) × hourly_rate ณ ตอน Check-in
 * ยอดสุทธิ = ค่าจอด − Deposit ที่ชำระแล้ว − reservation_fee (ไม่ติดลบ) · Walk-in ไม่หักทั้งสองรายการ
 * ยอดสุทธิ 0 → Checkout Payment ชำระแล้วโดยระบบ · มากกว่า 0 → รอ Admin/Owner Mark as Paid
 */
class CheckOutService
{
    /**
     * @param User|null $actor      null = ระบบ (Auto Check-out) · มีค่า = Manual Check-out โดยเจ้าหน้าที่
     * @param bool      $notifyUser false เมื่อผู้เรียกแจ้งผู้จองด้วยข้อความของตนเอง (เช่น Owner ลาออก)
     * @return array{success: bool, error: ?string, reservation: ?Reservation, payment: ?Payment}
     */
    public function checkOut(Reservation $reservation, ?User $actor = null, bool $notifyUser = true): array
    {
        try {
            $result = DB::transaction(function () use ($reservation, $actor) {
                $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

                if ($locked?->status !== 'checked_in') {
                    return $this->fail("ไม่สามารถเช็คเอาท์ได้ สถานะการจองปัจจุบันคือ '{$locked?->status}'");
                }

                $log = ParkingLog::where('reservation_id', $locked->id)->lockForUpdate()->first();

                if (!$log || $log->check_out_time !== null) {
                    return $this->fail("ทะเบียน {$locked->license_plate} ไม่มีรายการจอดที่ยังไม่ Check-out");
                }

                $now = now();
                $charge = $this->calculate($locked, $log, $now);
                $settled = $charge['total_amount'] <= 0;

                $log->update(['check_out_time' => $now]);

                $payment = Payment::create([
                    'type'                 => Payment::TYPE_CHECKOUT,
                    'reservation_id'       => $locked->id,
                    'parking_log_id'       => $log->id,
                    'hourly_rate'          => $charge['hourly_rate'],
                    'total_hours'          => $charge['total_hours'],
                    'parking_fee'          => $charge['parking_fee'],
                    'deposit_deduction'    => $charge['deposit_deduction'],
                    'reservation_discount' => $charge['reservation_discount'],
                    'total_amount'         => $charge['total_amount'],
                    // ไม่มียอดคงเหลือ → ระบบบันทึกว่าชำระแล้ว (paid_by ว่าง) โดยไม่สร้างหนี้ค้าง
                    'payment_status'       => $settled ? Payment::STATUS_PAID : Payment::STATUS_UNPAID,
                    'paid_at'              => $settled ? $now : null,
                ]);

                if ($log->parking_slot_id) {
                    ParkingSlot::whereKey($log->parking_slot_id)->where('status', 'occupied')->update(['status' => 'available']);
                }

                $locked->update(['status' => 'completed', 'completed_at' => $now]);

                ReservationLog::create([
                    'reservation_id' => $locked->id,
                    'old_status'     => 'checked_in',
                    'new_status'     => 'completed',
                    'changed_by'     => $actor?->id,
                    'note'           => ($actor ? 'Manual check-out' : 'Auto check-out') . ': ' . self::summary($payment),
                ]);

                audit_by($actor, 'reservation.check_out', $locked, [
                    'mode'           => $actor ? 'manual' : 'auto',
                    'parking_log_id' => $log->id,
                    'total_hours'    => $charge['total_hours'],
                ]);

                // Payment Log
                audit_by($actor, 'payment.checkout_created', $payment, [
                    'reservation_id'       => $locked->id,
                    'hourly_rate'          => $charge['hourly_rate'],
                    'parking_fee'          => $charge['parking_fee'],
                    'deposit_deduction'    => $charge['deposit_deduction'],
                    'reservation_discount' => $charge['reservation_discount'],
                    'total_amount'         => $charge['total_amount'],
                    'payment_status'       => $payment->payment_status,
                ]);

                return ['success' => true, 'error' => null, 'reservation' => $locked, 'payment' => $payment];
            });
        } catch (UniqueConstraintViolationException) {
            return $this->fail('มีการบันทึก Check-out ของรายการนี้แล้ว');
        }

        // Walkin User ไม่ได้รับ Notification
        if ($result['success'] && $notifyUser && !$result['reservation']->is_walk_in) {
            notify_user(
                $result['reservation']->user_id,
                'เช็คเอาท์เรียบร้อย',
                "รถทะเบียน {$result['reservation']->license_plate} ออกจากลานแล้ว | " . self::summary($result['payment'])
            );
        }

        $reservation->refresh();

        return $result;
    }

    /**
     * คำนวณยอด Checkout
     *
     * @return array{total_hours: int, hourly_rate: float, parking_fee: float, deposit_deduction: float, reservation_discount: float, total_amount: float}
     */
    public function calculate(Reservation $reservation, ParkingLog $log, CarbonInterface $checkOutAt): array
    {
        $minutes = (int) $log->check_in_time->diffInMinutes($checkOutAt);
        $hours = max(1, (int) ceil($minutes / 60));
        $rate = (float) $log->hourly_rate;
        $fee = round($hours * $rate, 2);

        $paidDeposit = $reservation->is_walk_in ? 0.0 : (float) Payment::where('reservation_id', $reservation->id)
            ->where('type', Payment::TYPE_DEPOSIT)
            ->where('payment_status', Payment::STATUS_PAID)
            ->value('total_amount');

        // หัก Deposit ก่อน แล้วจึงหักส่วนลด — แต่ละรายการหักได้ไม่เกินยอดที่เหลือ (ยอดสุทธิไม่ติดลบ)
        $depositDeduction = round(min($paidDeposit, $fee), 2);
        $discount = $reservation->is_walk_in ? 0.0 : round(min((float) $reservation->reservation_fee, $fee - $depositDeduction), 2);

        return [
            'total_hours'          => $hours,
            'hourly_rate'          => $rate,
            'parking_fee'          => $fee,
            'deposit_deduction'    => $depositDeduction,
            'reservation_discount' => $discount,
            'total_amount'         => round(max(0, $fee - $depositDeduction - $discount), 2),
        ];
    }

    /**
     * Admin/Owner ยืนยันรับชำระยอดค่าจอด (Mark as Paid) — Row Lock ป้องกันการยืนยันซ้ำ
     *
     * @return array{success: bool, error: ?string}
     */
    public function markCheckoutPaid(Payment $payment, User $actor): array
    {
        $result = DB::transaction(function () use ($payment, $actor) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($locked->type !== Payment::TYPE_CHECKOUT) {
                return ['success' => false, 'error' => 'รายการนี้ไม่ใช่ยอดค่าจอด'];
            }

            if ($locked->payment_status !== Payment::STATUS_UNPAID) {
                return ['success' => false, 'error' => 'รายการนี้ไม่อยู่ในสถานะรอชำระ'];
            }

            $locked->update([
                'payment_status' => Payment::STATUS_PAID,
                'paid_by'        => $actor->id,
                'paid_at'        => now(),
            ]);

            audit_by($actor, 'payment.mark_paid', $locked, [
                'type'           => Payment::TYPE_CHECKOUT,
                'reservation_id' => $locked->reservation_id,
                'total_amount'   => (float) $locked->total_amount,
            ]);

            return ['success' => true, 'error' => null];
        });

        $payment->refresh();

        return $result;
    }

    /** สรุปยอด Checkout สำหรับข้อความแจ้งเตือน / Log */
    public static function summary(Payment $payment): string
    {
        $parts = [
            sprintf('จอด %d ชม.', (int) $payment->total_hours),
            sprintf('ค่าจอด ฿%s', number_format((float) $payment->parking_fee, 2)),
        ];

        if ((float) $payment->deposit_deduction > 0) {
            $parts[] = sprintf('หักมัดจำ -฿%s', number_format((float) $payment->deposit_deduction, 2));
        }

        if ((float) $payment->reservation_discount > 0) {
            $parts[] = sprintf('ส่วนลด -฿%s', number_format((float) $payment->reservation_discount, 2));
        }

        $parts[] = sprintf(
            'ยอดสุทธิ ฿%s (%s)',
            number_format((float) $payment->total_amount, 2),
            $payment->payment_status === Payment::STATUS_PAID ? 'ชำระแล้ว' : 'รอชำระเงิน'
        );

        return implode(' | ', $parts);
    }

    private function fail(string $message): array
    {
        return ['success' => false, 'error' => $message, 'reservation' => null, 'payment' => null];
    }
}
