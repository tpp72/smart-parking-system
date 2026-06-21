<?php

namespace App\Services;

use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    /**
     * Perform vehicle check-in into a parking lot.
     *
     * Finds a checkable reservation automatically; falls back to $fallbackLotId
     * when no reservation exists.
     *
     * @return array{success:bool, log:?ParkingLog, slot:?ParkingSlot, reservation:?Reservation, error:?string}
     */
    public function checkIn(int $vehicleId, int $fallbackLotId): array
    {
        // Guard: vehicle already has an active parking session
        if (ParkingLog::where('vehicle_id', $vehicleId)->whereNull('check_out_time')->exists()) {
            return $this->fail('รถคันนี้กำลังจอดอยู่แล้ว ยังไม่ได้ Check-Out');
        }

        // Find checkable reservation: match by license_plate (new) or vehicle_id (legacy)
        $plate = Vehicle::where('id', $vehicleId)->value('license_plate');

        $reservation = Reservation::checkable()
            ->where(function ($q) use ($vehicleId, $plate) {
                $q->where('vehicle_id', $vehicleId);
                if ($plate) {
                    $q->orWhere('license_plate', $plate);
                }
            })
            ->orderBy('reserve_start')
            ->first();

        $lotId = $reservation ? $reservation->parking_lot_id : $fallbackLotId;
        $slot  = null;
        $log   = null;
        $error = null;
        $now   = now();

        DB::transaction(function () use ($vehicleId, $reservation, $lotId, $now, &$slot, &$error, &$log) {
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
