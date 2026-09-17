<?php

namespace App\Services;

use App\Models\LicensePlateScan;
use App\Models\ParkingLog;
use App\Models\Payment;
use App\Models\Reservation;

/**
 * กล้องลานจอด (จำลองด้วย Upload) — ระบบตรวจทิศทางเองจากสถานะรถ (project-plan.md §10.1, §12.5)
 *
 * - รถ (ทะเบียน + จังหวัด) กำลังจอดอยู่ในลานที่สแกน → Auto Check-out
 * - รถกำลังจอดอยู่ลานอื่น → ไม่ทำรายการ (บันทึก Audit Log)
 * - รถไม่ได้จอดอยู่ → Auto Check-in / Walk-in
 */
class ScanGateService
{
    const OUTCOME_CHECKED_OUT      = 'checked_out';
    const OUTCOME_PARKED_ELSEWHERE = 'parked_elsewhere';
    const OUTCOME_CHECK_OUT_FAILED = 'check_out_failed';

    public function __construct(
        private AutoCheckInService $autoCheckIn,
        private CheckOutService $checkOut,
    ) {}

    /**
     * @return array{outcome:string, success:bool, message:string, reservation:?Reservation, slot:?string, staff_notified:bool, payment:?Payment}
     */
    public function handle(LicensePlateScan $scan): array
    {
        $log = ParkingLog::with(['reservation', 'parkingLot:id,name', 'parkingSlot:id,slot_number'])
            ->whereNull('check_out_time')
            ->where('license_plate', $scan->license_plate)
            ->where('plate_province', $scan->plate_province)
            ->first();

        if (!$log) {
            return $this->autoCheckIn->handle($scan) + ['payment' => null];
        }

        $lot = $scan->parkingLot;
        $car = "{$log->license_plate} {$log->plate_province}";

        if ($log->parking_lot_id !== $lot->id) {
            audit_by(null, 'ai_scan.parked_elsewhere', $log->reservation, [
                'scan_id'        => $scan->id,
                'scanned_lot_id' => $lot->id,
                'parked_lot_id'  => $log->parking_lot_id,
            ]);

            return $this->result(
                self::OUTCOME_PARKED_ELSEWHERE,
                false,
                "รถคันนี้กำลังจอดอยู่ที่ลาน {$log->parkingLot->name} — ไม่สามารถ Check-out อัตโนมัติที่ลานนี้ได้",
                $log->reservation
            );
        }

        $result = $this->checkOut->checkOut($log->reservation);

        if (!$result['success']) {
            $lot->notifyManagers('Check-out อัตโนมัติไม่สำเร็จ', sprintf(
                'ทะเบียน %s ที่ลาน %s — %s กรุณาตรวจสอบและทำ Manual Check-out',
                $car, $lot->name, $result['error']
            ));

            audit_by(null, 'ai_scan.check_out_failed', $log->reservation, [
                'scan_id' => $scan->id,
                'error'   => $result['error'],
            ]);

            return $this->result(self::OUTCOME_CHECK_OUT_FAILED, false, "{$result['error']} (แจ้งผู้ดูแลลานแล้ว)", $log->reservation, staffNotified: true);
        }

        return $this->result(
            self::OUTCOME_CHECKED_OUT,
            true,
            CheckOutService::summary($result['payment']),
            $result['reservation'],
            $log->parkingSlot?->slot_number,
            payment: $result['payment']
        );
    }

    private function result(
        string $outcome,
        bool $success,
        string $message,
        ?Reservation $reservation = null,
        ?string $slot = null,
        bool $staffNotified = false,
        ?Payment $payment = null
    ): array {
        return [
            'outcome'        => $outcome,
            'success'        => $success,
            'message'        => $message,
            'reservation'    => $reservation,
            'slot'           => $slot,
            'staff_notified' => $staffNotified,
            'payment'        => $payment,
        ];
    }
}
