<?php

namespace App\Services;

use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /**
     * Perform vehicle check-in into a parking lot.
     *
     * ทะเบียน/ยี่ห้อ/สี คือข้อมูลระบุตัวรถหลัก (มาจาก reservation หรือผลสแกน AI เสมอ) ส่วน $vehicleId
     * เป็นแค่ลิงก์เสริมไปยัง Vehicle record ถ้ามีอยู่จริง (ไม่บังคับ เพราะตอนนี้จองแบบพิมพ์ทะเบียนเอง
     * ไม่ต้องลงทะเบียนรถล่วงหน้าแล้ว) — หา checkable reservation อัตโนมัติจากทะเบียน; fallback ไปที่
     * $fallbackLotId ถ้าไม่มี reservation ผูกอยู่ (เช่น walk-in)
     *
     * @param array<int>|null $allowedLotIds  จำกัดขอบเขตลานที่ผู้เรียกมีสิทธิ์ทำ check-in ให้
     *                                        (Admin ส่ง lot ที่ไม่มีเจ้าของ, Owner ส่ง lot ของตัวเอง)
     *                                        null = ไม่จำกัด (ใช้กับ flow ที่ผูกกับ reservation ของ vehicle owner เอง เช่น AI scan)
     * @return array{success:bool, log:?ParkingLog, slot:?ParkingSlot, reservation:?Reservation, error:?string}
     */
    public function checkIn(
        string $licensePlate,
        ?string $brand,
        ?string $color,
        int $fallbackLotId,
        ?array $allowedLotIds = null,
        ?int $vehicleId = null
    ): array {
        if ($allowedLotIds !== null && !in_array($fallbackLotId, $allowedLotIds, true)) {
            return $this->fail('ไม่มีสิทธิ์ทำ Check-In ให้ลานจอดนี้');
        }

        // Guard: ทะเบียนนี้มี parking session ที่ยังไม่ check-out อยู่แล้ว
        if (ParkingLog::where('license_plate', $licensePlate)->whereNull('check_out_time')->exists()) {
            return $this->fail('รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }

        // Find checkable reservation: match by license_plate (หลัก) หรือ vehicle_id (เสริม, ถ้ามี)
        $reservation = Reservation::checkable()
            ->where(function ($q) use ($vehicleId, $licensePlate) {
                $q->where('license_plate', $licensePlate);
                if ($vehicleId) {
                    $q->orWhere('vehicle_id', $vehicleId);
                }
            })
            ->when($allowedLotIds !== null, fn($q) => $q->whereIn('parking_lot_id', $allowedLotIds))
            ->orderBy('reserve_start')
            ->first();

        $lotId = $reservation ? $reservation->parking_lot_id : $fallbackLotId;
        $slot  = null;
        $log   = null;
        $error = null;
        $now   = now();

        DB::transaction(function () use ($vehicleId, $licensePlate, $brand, $color, $reservation, $lotId, $now, &$slot, &$error, &$log) {
            // Try the specifically reserved slot first (may be 'reserved' after confirm or 'available' for legacy)
            if ($reservation?->parking_slot_id) {
                $slot = ParkingSlot::where('id', $reservation->parking_slot_id)
                    ->whereIn('status', ['available', 'reserved'])
                    ->lockForUpdate()
                    ->first();
            }

            // Fallback: any available slot in the lot
            if (!$slot) {
                $slot = ParkingSlot::where('parking_lot_id', $lotId)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
            }

            if (!$slot) {
                $error = 'ไม่มีช่องจอดว่างในลานที่เลือก';
                return;
            }

            $log = ParkingLog::create([
                'vehicle_id'      => $vehicleId,
                'license_plate'   => $licensePlate,
                'brand'           => $brand,
                'color'           => $color,
                'parking_lot_id'  => $slot->parking_lot_id,
                'parking_slot_id' => $slot->id,
                'check_in_time'   => $now,
                'reservation_id'  => $reservation?->id,
            ]);

            $slot->update(['status' => 'occupied']);

            if ($reservation) {
                $oldStatus = $reservation->status;

                $reservation->update([
                    'status'        => 'checked_in',
                    'checked_in_at' => $now,
                ]);

                ReservationLog::create([
                    'reservation_id' => $reservation->id,
                    'old_status'     => $oldStatus,
                    'new_status'     => 'checked_in',
                    'changed_by'     => null,
                    'note'           => "Auto check-in: รถเข้าจอดที่ช่อง {$slot->slot_number}",
                ]);
            }
        });

        if ($error) {
            return $this->fail($error);
        }

        return [
            'success'     => true,
            'log'         => $log,
            'slot'        => $slot,
            'reservation' => $reservation,
            'error'       => null,
        ];
    }

    private function fail(string $message): array
    {
        return [
            'success'     => false,
            'log'         => null,
            'slot'        => null,
            'reservation' => null,
            'error'       => $message,
        ];
    }
}
