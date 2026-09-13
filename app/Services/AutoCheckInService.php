<?php

namespace App\Services;

use App\Models\LicensePlateScan;
use App\Models\ParkingLot;
use App\Models\Reservation;

/**
 * Auto Check-in จากผล AI Scan ที่ผ่านเกณฑ์ (project-plan.md §10.5–10.10, §11)
 *
 * ค้นหาการจอง (pending / confirmed) ของ ทะเบียน + จังหวัด แล้วตัดสินตามลานที่กล้องติดตั้ง:
 * - confirmed ในลานนี้ + อยู่ในช่วงเช็คอิน → เช็คอินด้วยการจองเดิม
 *   (ยี่ห้อและสีไม่ตรงทั้งคู่ → ยังเช็คอิน + แจ้ง Owner/Admin ให้ตรวจสอบ)
 * - confirmed ในลานนี้ + มาก่อนเวลาจอง     → ไม่เช็คอิน · แจ้ง Owner/Admin ให้ Manual Check-in
 * - การจองอยู่ลานอื่น / ยัง pending / เลยเวลาเช็คอิน → Walk-in + แจ้ง Owner/Admin
 * - ไม่พบการจอง (รวมทะเบียนหรือจังหวัดไม่ตรง) → Walk-in
 * - Walk-in แต่ลานเต็ม → OUTCOME_LOT_FULL (ผู้เรียกต้องไม่บันทึกผล Scan)
 */
class AutoCheckInService
{
    const OUTCOME_CHECKED_IN     = 'checked_in';
    const OUTCOME_WALK_IN        = 'walk_in';
    const OUTCOME_EARLY_ARRIVAL  = 'early_arrival';
    const OUTCOME_ALREADY_PARKED = 'already_parked';
    const OUTCOME_LOT_FULL       = 'lot_full';
    const OUTCOME_FAILED         = 'failed';

    public function __construct(private CheckInService $checkIn) {}

    /**
     * @return array{outcome:string, success:bool, message:string, reservation:?Reservation, slot:?string, staff_notified:bool}
     */
    public function handle(LicensePlateScan $scan): array
    {
        $lot = $scan->parkingLot;

        if ($this->checkIn->isParked($scan->license_plate, $scan->plate_province)) {
            return $this->result(self::OUTCOME_ALREADY_PARKED, false, 'รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }

        $booking = Reservation::booking()
            ->with('parkingLot:id,name')
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('license_plate', $scan->license_plate)
            ->where('plate_province', $scan->plate_province)
            ->first();

        if ($booking && $booking->parking_lot_id === $lot->id && $booking->status === 'confirmed') {
            if ($booking->reserve_start->isFuture()) {
                return $this->earlyArrival($lot, $booking);
            }

            if (Reservation::checkable()->whereKey($booking->id)->exists()) {
                return $this->checkInBooking($scan, $lot, $booking);
            }
        }

        return $this->walkIn($scan, $lot, $booking);
    }

    private function earlyArrival(ParkingLot $lot, Reservation $booking): array
    {
        $reserveAt = $booking->reserve_start->format('d/m/Y H:i');

        $lot->notifyStaff('รถมาก่อนเวลาจอง', sprintf(
            'ทะเบียน %s %s (การจอง #%d) มาถึงลาน %s ก่อนเวลาจอง %s — ระบบไม่เช็คอินอัตโนมัติ กรุณาตรวจสอบรถและทำ Manual Check-in',
            $booking->license_plate, $booking->plate_province, $booking->id, $lot->name, $reserveAt
        ));

        return $this->result(
            self::OUTCOME_EARLY_ARRIVAL,
            false,
            "มาก่อนเวลาจอง ({$reserveAt}) — ระบบไม่เช็คอินอัตโนมัติ แจ้ง Owner / Admin ของลานให้ทำ Manual Check-in แล้ว",
            $booking,
            staffNotified: true
        );
    }

    private function checkInBooking(LicensePlateScan $scan, ParkingLot $lot, Reservation $booking): array
    {
        $result = $this->checkIn->checkInReservation($booking);

        if (!$result['success']) {
            $outcome = $result['outcome'] === CheckInService::OUTCOME_ALREADY_PARKED ? self::OUTCOME_ALREADY_PARKED : self::OUTCOME_FAILED;

            return $this->result($outcome, false, $result['error'], $booking);
        }

        $slotNumber = $result['slot']->slot_number;

        notify_user(
            $booking->user_id,
            'เช็คอินอัตโนมัติสำเร็จ',
            "ทะเบียน {$booking->license_plate} เช็คอินผ่านระบบสแกนรถ เข้าจอดที่ช่อง {$slotNumber} แล้ว (การจอง #{$booking->id})"
        );

        $mismatch = $this->vehicleMismatch($booking, $scan);

        if ($mismatch) {
            $lot->notifyStaff('ยี่ห้อ/สีรถไม่ตรงกับการจอง', sprintf(
                'ทะเบียน %s %s (การจอง #%d) เช็คอินอัตโนมัติที่ลาน %s ช่อง %s แล้ว แต่%s — กรุณาตรวจสอบรถคันนี้',
                $booking->license_plate, $booking->plate_province, $booking->id, $lot->name, $slotNumber, $mismatch
            ));
        }

        return $this->result(
            self::OUTCOME_CHECKED_IN,
            true,
            "เช็คอินด้วยการจอง #{$booking->id}" . ($mismatch ? " — {$mismatch} (แจ้ง Owner / Admin ให้ตรวจสอบแล้ว)" : ''),
            $result['reservation'],
            $slotNumber,
            (bool) $mismatch
        );
    }

    private function walkIn(LicensePlateScan $scan, ParkingLot $lot, ?Reservation $unusableBooking): array
    {
        $result = $this->checkIn->checkInWalkIn($lot, $scan->license_plate, $scan->plate_province, $scan->brand, $scan->color);

        if (!$result['success']) {
            return match ($result['outcome']) {
                CheckInService::OUTCOME_LOT_FULL       => $this->result(self::OUTCOME_LOT_FULL, false, "ลานเต็ม — ไม่มีช่องจอดว่างในลาน {$lot->name}"),
                CheckInService::OUTCOME_ALREADY_PARKED => $this->result(self::OUTCOME_ALREADY_PARKED, false, $result['error']),
                default                                => $this->result(self::OUTCOME_FAILED, false, $result['error']),
            };
        }

        $walkIn = $result['reservation'];
        $slotNumber = $result['slot']->slot_number;
        $reason = $unusableBooking ? $this->unusableBookingReason($unusableBooking, $lot) : null;

        if ($reason) {
            $lot->notifyStaff('Auto Check-in เป็น Walk-in (มีการจองค้างอยู่)', sprintf(
                'ทะเบียน %s %s เข้าลาน %s ช่อง %s เป็น Walk-in #%d เพราะ%s',
                $walkIn->license_plate, $walkIn->plate_province, $lot->name, $slotNumber, $walkIn->id, $reason
            ));
        }

        return $this->result(
            self::OUTCOME_WALK_IN,
            true,
            'เช็คอินเป็น Walk-in' . ($reason ? " — {$reason} (แจ้ง Owner / Admin แล้ว)" : ''),
            $walkIn,
            $slotNumber,
            (bool) $reason
        );
    }

    /** เหตุผลที่การจองของรถคันนี้ใช้ Auto Check-in ในลานนี้ไม่ได้ */
    private function unusableBookingReason(Reservation $booking, ParkingLot $lot): string
    {
        if ($booking->parking_lot_id !== $lot->id) {
            return "การจอง #{$booking->id} ของรถคันนี้อยู่ที่ลาน {$booking->parkingLot?->name}";
        }

        if ($booking->status === 'pending') {
            return "การจอง #{$booking->id} ของรถคันนี้ยังไม่ได้ยืนยันรับเงินมัดจำ";
        }

        return "การจอง #{$booking->id} ของรถคันนี้เลยเวลาเช็คอินแล้ว";
    }

    /**
     * กฎ Matching: ทะเบียน + จังหวัดตรง (ใช้ค้นหาการจองมาแล้ว) AND (ยี่ห้อตรง OR สีตรง)
     * คืนข้อความเมื่อยี่ห้อและสีไม่ตรงทั้งคู่ · null เมื่อผ่าน
     */
    private function vehicleMismatch(Reservation $booking, LicensePlateScan $scan): ?string
    {
        $brandOk = $booking->brand && $scan->brand
            && strcasecmp(trim($booking->brand), trim($scan->brand)) === 0;
        $colorOk = $booking->color && $scan->color
            && trim($booking->color) === trim($scan->color);

        if ($brandOk || $colorOk) {
            return null;
        }

        return sprintf(
            'ยี่ห้อและสีไม่ตรงกับที่แจ้งไว้ (แจ้งไว้ %s / %s · สแกนได้ %s / %s)',
            $booking->brand ?: '—', $booking->color ?: '—', $scan->brand ?: 'ไม่พบ', $scan->color ?: 'ไม่พบ'
        );
    }

    private function result(
        string $outcome,
        bool $success,
        string $message,
        ?Reservation $reservation = null,
        ?string $slot = null,
        bool $staffNotified = false
    ): array {
        return [
            'outcome'        => $outcome,
            'success'        => $success,
            'message'        => $message,
            'reservation'    => $reservation,
            'slot'           => $slot,
            'staff_notified' => $staffNotified,
        ];
    }
}
