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
 * ยอดสุทธิ = ค่าจอด − Deposit ที่ชำระแล้ว − reservation_fee − ค่าจอดที่ชำระไปแล้ว (ไม่ติดลบ) · Walk-in ไม่หัก Deposit/ส่วนลด
 *
 * ชำระก่อนออก (§12.6) — กล้องขาออกปล่อยรถเฉพาะที่ชำระแล้ว:
 *   กด Check-out (requestCheckout) → ล็อกยอด ณ เวลานั้น ชำระได้ภายใน N นาที
 *   กดชำระ (payCheckout)          → สร้างใบชำระแล้ว สแกนออกได้ภายใน N นาที
 *   เลยช่วงไม่ว่าขั้นไหน → เวลานับต่อ · กด Check-out ใหม่ได้ยอด = ค่าจอดถึงตอนนั้น − ที่ชำระไปแล้ว
 * Manual Check-out ของเจ้าหน้าที่และการ Check-out ที่ระบบบังคับ (ลบผู้ใช้ · ปิดลาน) ไม่ต้องชำระก่อน
 * — ยอดที่เหลือเป็น "รอชำระ" ให้เจ้าหน้าที่ Mark as Paid เหมือนเดิม
 */
class CheckOutService
{
    /** สถานะการชำระก่อนออกของรถที่จอดอยู่ */
    const STATE_PARKED           = 'parked';
    const STATE_AWAITING_PAYMENT = 'awaiting_payment';
    const STATE_EXIT_OPEN        = 'exit_open';

    /** รหัสความล้มเหลวที่ผู้เรียกต้องแยกได้ (กล้องขาออกไม่แจ้งเจ้าหน้าที่ในกรณีนี้ เพราะเป็นเรื่องปกติของคนขับ) */
    const ERROR_PAYMENT_REQUIRED = 'payment_required';

    /** ช่วงเวลาชำระ และช่วงเวลาสแกนออกหลังชำระ (นาที) */
    public static function window(): int
    {
        return (int) config('parking.checkout_window', 5);
    }

    /**
     * สถานะการชำระก่อนออก ณ ตอนนี้ — ใช้ร่วมกันทั้งหน้าจอคนขับและกล้องขาออก
     *
     * @return array{state: string, deadline: ?CarbonInterface, charge: array, payment: ?Payment}
     *   charge = ยอดที่ต้องชำระ (awaiting_payment: ยอดที่ล็อกไว้ · parked: ประมาณ ณ ตอนนี้ · exit_open: ของใบที่ชำระ)
     */
    public function exitState(Reservation $reservation, ParkingLog $log, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $window = self::window();

        $latestPaid = Payment::where('parking_log_id', $log->id)
            ->where('type', Payment::TYPE_CHECKOUT)
            ->where('payment_status', Payment::STATUS_PAID)
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->first();

        if ($latestPaid && $latestPaid->paid_at->copy()->addMinutes($window)->gte($now)) {
            return [
                'state'    => self::STATE_EXIT_OPEN,
                'deadline' => $latestPaid->paid_at->copy()->addMinutes($window),
                'charge'   => $this->chargeOf($latestPaid),
                'payment'  => $latestPaid,
            ];
        }

        $requestedAt = $log->checkout_requested_at;

        if ($requestedAt && $requestedAt->copy()->addMinutes($window)->gte($now)) {
            return [
                'state'    => self::STATE_AWAITING_PAYMENT,
                'deadline' => $requestedAt->copy()->addMinutes($window),
                'charge'   => $this->calculate($reservation, $log, $requestedAt),
                'payment'  => null,
            ];
        }

        return [
            'state'    => self::STATE_PARKED,
            'deadline' => null,
            'charge'   => $this->calculate($reservation, $log, $now),
            'payment'  => null,
        ];
    }

    /**
     * คนขับกด Check-out — ล็อกยอด ณ ตอนนี้ แล้วให้ชำระภายใน N นาที
     *
     * กดซ้ำระหว่างที่ยังล็อกอยู่ไม่ต่อเวลาให้ (ไม่งั้นกดทุก 4 นาทีเพื่อหยุดค่าจอดไว้ได้)
     * ยอดเป็น 0 (มัดจำครอบคลุม หรือที่ชำระไปแล้วยังพอ) → บันทึกว่าชำระแล้วทันที สแกนออกได้เลย
     *
     * @param User|null $actor null = คนขับที่ไม่ได้ล็อกอิน (Walk-in ที่หน้าเช็คสถานะรถ)
     * @return array{success: bool, error: ?string, state: ?array}
     */
    public function requestCheckout(Reservation $reservation, ?User $actor = null): array
    {
        return DB::transaction(function () use ($reservation, $actor) {
            [$locked, $log, $error] = $this->lockParked($reservation);

            if ($error) {
                return ['success' => false, 'error' => $error, 'state' => null];
            }

            $now = now();
            $state = $this->exitState($locked, $log, $now);

            // ชำระแล้ว หรือกำลังรอชำระ → คืนสถานะเดิม ไม่ล็อกยอดใหม่
            if ($state['state'] !== self::STATE_PARKED) {
                return ['success' => true, 'error' => null, 'state' => $state];
            }

            $log->update(['checkout_requested_at' => $now]);
            $charge = $this->calculate($locked, $log, $now);

            audit_by($actor, 'reservation.checkout_request', $locked, [
                'parking_log_id' => $log->id,
                'total_hours'    => $charge['total_hours'],
                'total_amount'   => $charge['total_amount'],
            ]);

            if ($charge['total_amount'] <= 0) {
                $this->recordCheckoutPayment($locked, $log, $charge, $now, paid: true, actor: $actor, byDriver: true);
            }

            return ['success' => true, 'error' => null, 'state' => $this->exitState($locked, $log->fresh(), $now)];
        });
    }

    /**
     * คนขับกดชำระ (จำลอง) — ชำระยอดที่ล็อกไว้ตอนกด Check-out แล้วเปิดให้สแกนออกได้ภายใน N นาที
     *
     * @param User|null $actor null = คนขับที่ไม่ได้ล็อกอิน
     * @return array{success: bool, error: ?string, state: ?array}
     */
    public function payCheckout(Reservation $reservation, ?User $actor = null): array
    {
        return DB::transaction(function () use ($reservation, $actor) {
            [$locked, $log, $error] = $this->lockParked($reservation);

            if ($error) {
                return ['success' => false, 'error' => $error, 'state' => null];
            }

            $now = now();
            $state = $this->exitState($locked, $log, $now);

            if ($state['state'] === self::STATE_EXIT_OPEN) {
                return ['success' => true, 'error' => null, 'state' => $state];
            }

            if ($state['state'] === self::STATE_PARKED) {
                // เคยกดแล้วแต่เลยเวลา → ล้างยอดที่ล็อกไว้ ให้กด Check-out ใหม่ด้วยยอดปัจจุบัน
                $expired = $log->checkout_requested_at !== null;
                $log->update(['checkout_requested_at' => null]);

                return [
                    'success' => false,
                    'error'   => $expired
                        ? 'หมดเวลาชำระแล้ว ('.self::window().' นาที) — ค่าจอดนับต่อจากเดิม กรุณากด Check-out ใหม่'
                        : 'กรุณากด Check-out ก่อนชำระค่าจอด',
                    'state'   => $this->exitState($locked, $log, $now),
                ];
            }

            $this->recordCheckoutPayment($locked, $log, $state['charge'], $now, paid: true, actor: $actor, byDriver: true);

            return ['success' => true, 'error' => null, 'state' => $this->exitState($locked, $log->fresh(), $now)];
        });
    }

    /**
     * @param User|null $actor      null = ระบบ (Auto Check-out) · มีค่า = Manual Check-out โดยเจ้าหน้าที่
     * @param bool      $notifyUser false เมื่อผู้เรียกแจ้งผู้จองด้วยข้อความของตนเอง (เช่น Owner ลาออก)
     * @param bool      $prepaid    true = กล้องขาออก: ปล่อยรถเฉพาะที่ชำระแล้ว (หรือไม่มียอดต้องชำระ) ·
     *                              false = Manual / ระบบบังคับ: ยอดที่เหลือเป็น "รอชำระ" ให้เจ้าหน้าที่ตามเก็บ
     * @return array{success: bool, error: ?string, code: ?string, reservation: ?Reservation, payment: ?Payment, charge: ?array}
     */
    public function checkOut(Reservation $reservation, ?User $actor = null, bool $notifyUser = true, bool $prepaid = false): array
    {
        try {
            $result = DB::transaction(function () use ($reservation, $actor, $prepaid) {
                [$locked, $log, $error] = $this->lockParked($reservation);

                if ($error) {
                    return $this->fail($error);
                }

                $now = now();
                $state = $this->exitState($locked, $log, $now);

                if ($state['state'] === self::STATE_EXIT_OPEN) {
                    // ชำระแล้วและยังอยู่ในช่วงสแกนออก — ไม่คิดเวลาที่เลยจากตอนล็อกยอด
                    $payment = $state['payment'];
                } else {
                    $charge = $this->calculate($locked, $log, $now);

                    if ($prepaid && $charge['total_amount'] > 0) {
                        return $this->fail(
                            sprintf('Check-out ไม่สำเร็จ กรุณาชำระค่าจอด ฿%s ก่อนออกจากลาน', number_format($charge['total_amount'], 2)),
                            self::ERROR_PAYMENT_REQUIRED,
                            $charge
                        );
                    }

                    $latestPaid = $state['payment'] ?? Payment::where('parking_log_id', $log->id)
                        ->where('type', Payment::TYPE_CHECKOUT)
                        ->where('payment_status', Payment::STATUS_PAID)
                        ->orderByDesc('id')
                        ->first();

                    // ไม่มียอดเหลือและมีใบที่ชำระไว้แล้ว → ใช้ใบนั้นเป็นยอดสุดท้าย ไม่ต้องออกใบ 0 บาทเพิ่ม
                    // ยอดเหลือ → "รอชำระ" ให้เจ้าหน้าที่ Mark as Paid · ยอด 0 → ระบบบันทึกว่าชำระแล้ว
                    $payment = ($charge['total_amount'] <= 0 && $latestPaid)
                        ? $latestPaid
                        : $this->recordCheckoutPayment($locked, $log, $charge, $now, paid: $charge['total_amount'] <= 0, actor: $actor);
                }

                $log->update(['check_out_time' => $now, 'checkout_requested_at' => null]);

                if ($log->parking_slot_id) {
                    ParkingSlot::whereKey($log->parking_slot_id)->where('status', 'occupied')->update(['status' => 'available']);
                }

                $locked->update(['status' => 'completed', 'completed_at' => $now]);

                ReservationLog::create([
                    'reservation_id' => $locked->id,
                    'old_status'     => 'checked_in',
                    'new_status'     => 'completed',
                    'changed_by'     => $actor?->id,
                    'note'           => ($actor ? 'Check-out โดยเจ้าหน้าที่' : 'Check-out อัตโนมัติ') . ': ' . self::summary($payment),
                ]);

                audit_by($actor, 'reservation.check_out', $locked, [
                    'mode'           => $actor ? 'manual' : 'auto',
                    'parking_log_id' => $log->id,
                    'total_hours'    => (int) $payment->total_hours,
                    'prepaid'        => $state['state'] === self::STATE_EXIT_OPEN,
                ]);

                return ['success' => true, 'error' => null, 'code' => null, 'reservation' => $locked, 'payment' => $payment, 'charge' => null];
            });
        } catch (UniqueConstraintViolationException) {
            return $this->fail('มีการบันทึก Check-out ของรายการนี้แล้ว');
        }

        // บัญชีระบบ (Walkin User) ไม่ได้รับ Notification
        if ($result['success'] && $notifyUser && ! $result['reservation']->user?->is_system) {
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

        // ค่าจอดที่ชำระไปแล้วของการจอดครั้งนี้ (ชำระแล้วไม่ออกภายในเวลา → ใบใหม่เก็บเฉพาะส่วนที่เกิน §12.6)
        $priorPaid = (float) Payment::where('parking_log_id', $log->id)
            ->where('type', Payment::TYPE_CHECKOUT)
            ->where('payment_status', Payment::STATUS_PAID)
            ->sum('total_amount');
        $priorDeduction = round(min($priorPaid, max(0, $fee - $depositDeduction - $discount)), 2);

        return [
            'total_hours'          => $hours,
            'hourly_rate'          => $rate,
            'parking_fee'          => $fee,
            'deposit_deduction'    => $depositDeduction,
            'reservation_discount' => $discount,
            'prior_paid'           => $priorDeduction,
            'total_amount'         => round(max(0, $fee - $depositDeduction - $discount - $priorDeduction), 2),
        ];
    }

    /** ยอดของใบชำระในรูปแบบเดียวกับ calculate() — ใช้แสดงผลเมื่อชำระแล้ว */
    private function chargeOf(Payment $payment): array
    {
        return [
            'total_hours'          => (int) $payment->total_hours,
            'hourly_rate'          => (float) $payment->hourly_rate,
            'parking_fee'          => (float) $payment->parking_fee,
            'deposit_deduction'    => (float) $payment->deposit_deduction,
            'reservation_discount' => (float) $payment->reservation_discount,
            'prior_paid'           => (float) $payment->prior_paid,
            'total_amount'         => (float) $payment->total_amount,
        ];
    }

    /**
     * ล็อกการจองและรายการจอดที่ยังไม่ออก — ใช้ร่วมทุกขั้นของการออกจากลาน
     *
     * @return array{0: ?Reservation, 1: ?ParkingLog, 2: ?string}
     */
    private function lockParked(Reservation $reservation): array
    {
        $locked = Reservation::whereKey($reservation->id)->lockForUpdate()->first();

        if ($locked?->status !== 'checked_in') {
            return [null, null, "ไม่สามารถเช็คเอาท์ได้ สถานะการจองปัจจุบันคือ '{$locked?->status}'"];
        }

        $log = ParkingLog::where('reservation_id', $locked->id)->lockForUpdate()->first();

        if (! $log || $log->check_out_time !== null) {
            return [null, null, "ทะเบียน {$locked->license_plate} ไม่มีรายการจอดที่ยังไม่ Check-out"];
        }

        return [$locked, $log, null];
    }

    /**
     * บันทึกใบค่าจอด
     *
     * @param bool      $paid     true = ชำระแล้ว (คนขับชำระก่อนออก หรือยอด 0) · false = รอเจ้าหน้าที่ Mark as Paid
     * @param User|null $actor    ผู้ทำรายการ
     * @param bool      $byDriver true = คนขับชำระเองก่อนออก — บันทึกผู้ชำระ (ถ้าล็อกอิน) และล้างยอดที่ล็อกไว้
     */
    private function recordCheckoutPayment(Reservation $reservation, ParkingLog $log, array $charge, CarbonInterface $now, bool $paid, ?User $actor = null, bool $byDriver = false): Payment
    {
        $payment = Payment::create([
            'type'                 => Payment::TYPE_CHECKOUT,
            'reservation_id'       => $reservation->id,
            'parking_log_id'       => $log->id,
            'hourly_rate'          => $charge['hourly_rate'],
            'total_hours'          => $charge['total_hours'],
            'parking_fee'          => $charge['parking_fee'],
            'deposit_deduction'    => $charge['deposit_deduction'],
            'reservation_discount' => $charge['reservation_discount'],
            'prior_paid'           => $charge['prior_paid'],
            'total_amount'         => $charge['total_amount'],
            'payment_status'       => $paid ? Payment::STATUS_PAID : Payment::STATUS_UNPAID,
            'paid_at'              => $paid ? $now : null,
            // คนขับชำระเอง: ผู้ชำระคือบัญชีที่ล็อกอิน (Walk-in ไม่มีบัญชี = ว่าง) · ยอด 0 ที่ระบบปิดให้ = ว่าง
            'paid_by'              => $paid && $byDriver ? $actor?->id : null,
        ]);

        if ($byDriver) {
            $log->update(['checkout_requested_at' => null]);
        }

        audit_by($actor, $byDriver ? 'payment.checkout_paid' : 'payment.checkout_created', $payment, [
            'reservation_id'       => $reservation->id,
            'hourly_rate'          => $charge['hourly_rate'],
            'parking_fee'          => $charge['parking_fee'],
            'deposit_deduction'    => $charge['deposit_deduction'],
            'reservation_discount' => $charge['reservation_discount'],
            'prior_paid'           => $charge['prior_paid'],
            'total_amount'         => $charge['total_amount'],
            'payment_status'       => $payment->payment_status,
        ]);

        return $payment;
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

        if ((float) $payment->prior_paid > 0) {
            $parts[] = sprintf('หักที่ชำระแล้ว -฿%s', number_format((float) $payment->prior_paid, 2));
        }

        $parts[] = sprintf(
            'ยอดสุทธิ ฿%s (%s)',
            number_format((float) $payment->total_amount, 2),
            $payment->payment_status === Payment::STATUS_PAID ? 'ชำระแล้ว' : 'รอชำระเงิน'
        );

        return implode(' | ', $parts);
    }

    private function fail(string $message, ?string $code = null, ?array $charge = null): array
    {
        return ['success' => false, 'error' => $message, 'code' => $code, 'reservation' => null, 'payment' => null, 'charge' => $charge];
    }
}
