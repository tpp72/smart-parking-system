<?php

namespace App\Services;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Check-in รถเข้าลาน — ทุกการจอดต้องมาจาก Reservation (project-plan.md §10–11)
 * - Reservation ที่ confirmed เข้าช่องที่ระบบ Lock ไว้ให้
 * - Walk-in สร้าง Reservation ของ Walkin User แล้วเช็คอินทันที
 */
class CheckInService
{
    const OUTCOME_CHECKED_IN     = 'checked_in';
    const OUTCOME_ALREADY_PARKED = 'already_parked';
    const OUTCOME_NOT_CHECKABLE  = 'not_checkable';
    const OUTCOME_LOT_FULL       = 'lot_full';

    public function __construct(private SlotAllocator $slots) {}

    /** รถคันนี้ (ทะเบียน + จังหวัด) มีการจอดที่ยังไม่ Check-out อยู่หรือไม่ */
    public function isParked(string $licensePlate, string $plateProvince): bool
    {
        return ParkingLog::whereNull('check_out_time')
            ->where('license_plate', $licensePlate)
            ->where('plate_province', $plateProvince)
            ->exists();
    }

    /**
     * เช็คอินรถของ Reservation ที่ confirmed
     *
     * @param User|null $actor      null = ระบบ (Auto Check-in) · มีค่า = Manual Check-in โดยเจ้าหน้าที่
     * @param bool      $allowEarly Manual Check-in รับรถที่มาก่อนเวลาจองได้ · Auto Check-in ไม่ได้
     * @return array{success:bool, outcome:string, error:?string, reservation:?Reservation, slot:?ParkingSlot, log:?ParkingLog}
     */
    public function checkInReservation(Reservation $reservation, ?User $actor = null, bool $allowEarly = false): array
    {
        if ($this->isParked($reservation->license_plate, $reservation->plate_province)) {
            return $this->fail(self::OUTCOME_ALREADY_PARKED, 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }

        try {
            return DB::transaction(function () use ($reservation, $actor, $allowEarly) {
                $reservation = Reservation::lockForUpdate()->findOrFail($reservation->id);

                if ($reservation->status !== 'confirmed') {
                    return $this->fail(self::OUTCOME_NOT_CHECKABLE, "ไม่สามารถเช็คอินได้ สถานะปัจจุบันคือ '{$reservation->status}'");
                }

                $window = $allowEarly ? Reservation::manuallyCheckable() : Reservation::checkable();

                if (!$window->whereKey($reservation->id)->exists()) {
                    return $this->fail(self::OUTCOME_NOT_CHECKABLE, $allowEarly
                        ? 'เลยเวลาเช็คอินของการจองนี้แล้ว'
                        : 'อยู่นอกช่วงเวลาเช็คอิน (เร็วเกินไปหรือเกินเวลากำหนด)');
                }

                // เข้าช่องที่ระบบ Lock ไว้ให้ · ไม่พบ → ระบบจัดสรรช่องว่างในลานเดียวกัน
                $slot = $this->slots->lockReservedSlotFor($reservation)
                    ?? $this->slots->lockAvailableSlot($reservation->parking_lot_id);

                if (!$slot) {
                    return $this->fail(self::OUTCOME_LOT_FULL, 'ไม่มีช่องจอดว่างในลานนี้');
                }

                $now = now();
                $log = $this->park($reservation, $slot, $now);

                $reservation->update([
                    'status'          => 'checked_in',
                    'checked_in_at'   => $now,
                    'parking_slot_id' => $slot->id,
                ]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => 'confirmed',
                    'new_status'     => 'checked_in',
                    'changed_by'     => $actor?->id,
                    'note'           => ($actor ? 'Manual check-in' : 'Auto check-in') . ": รถเข้าจอดที่ช่อง {$slot->slot_number}",
                ]);

                return $this->success($reservation, $slot, $log);
            });
        } catch (UniqueConstraintViolationException) {
            return $this->fail(self::OUTCOME_ALREADY_PARKED, 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }
    }

    /**
     * Walk-in: สร้าง Reservation ของ Walkin User (reserve_start = ตอนนี้ · Deposit 0 · ไม่มีส่วนลด)
     * แล้วเช็คอินทันที — เข้าได้ทุกลานที่มี Slot ว่าง ไม่ขึ้นกับ reservations_enabled
     *
     * @return array{success:bool, outcome:string, error:?string, reservation:?Reservation, slot:?ParkingSlot, log:?ParkingLog}
     */
    public function checkInWalkIn(ParkingLot $lot, string $licensePlate, string $plateProvince, ?string $brand, ?string $color): array
    {
        if ($this->isParked($licensePlate, $plateProvince)) {
            return $this->fail(self::OUTCOME_ALREADY_PARKED, 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }

        try {
            return DB::transaction(function () use ($lot, $licensePlate, $plateProvince, $brand, $color) {
                $slot = $this->slots->lockAvailableSlot($lot->id);

                if (!$slot) {
                    return $this->fail(self::OUTCOME_LOT_FULL, 'ลานเต็ม');
                }

                $now = now();

                $reservation = Reservation::create([
                    'user_id'         => User::walkin()->id,
                    'is_walk_in'      => true,
                    'parking_lot_id'  => $lot->id,
                    'parking_slot_id' => $slot->id,
                    'license_plate'   => $licensePlate,
                    'plate_province'  => $plateProvince,
                    'brand'           => $brand,
                    'color'           => $color,
                    'reserve_start'   => $now,
                    'checked_in_at'   => $now,
                    'deposit_amount'  => 0,
                    'reservation_fee' => 0,
                    'status'          => 'checked_in',
                ]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => null,
                    'new_status'     => 'checked_in',
                    'changed_by'     => null,
                    'note'           => "Walk-in: ระบบสร้างการจองและเช็คอินอัตโนมัติ เข้าจอดที่ช่อง {$slot->slot_number}",
                ]);

                return $this->success($reservation, $slot, $this->park($reservation, $slot, $now));
            });
        } catch (UniqueConstraintViolationException) {
            return $this->fail(self::OUTCOME_ALREADY_PARKED, 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }
    }

    private function park(Reservation $reservation, ParkingSlot $slot, Carbon $now): ParkingLog
    {
        $log = ParkingLog::create([
            'reservation_id'  => $reservation->id,
            'parking_lot_id'  => $slot->parking_lot_id,
            'parking_slot_id' => $slot->id,
            'license_plate'   => $reservation->license_plate,
            'plate_province'  => $reservation->plate_province,
            'brand'           => $reservation->brand,
            'color'           => $reservation->color,
            'check_in_time'   => $now,
        ]);

        $slot->update(['status' => 'occupied']);

        return $log;
    }

    private function success(Reservation $reservation, ParkingSlot $slot, ParkingLog $log): array
    {
        return [
            'success'     => true,
            'outcome'     => self::OUTCOME_CHECKED_IN,
            'error'       => null,
            'reservation' => $reservation,
            'slot'        => $slot,
            'log'         => $log,
        ];
    }

    private function fail(string $outcome, string $message): array
    {
        return [
            'success'     => false,
            'outcome'     => $outcome,
            'error'       => $message,
            'reservation' => null,
            'slot'        => null,
            'log'         => null,
        ];
    }
}
