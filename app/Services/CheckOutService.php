<?php

namespace App\Services;

use App\Models\ParkingLog;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CheckOutService
{
    /**
     * ทำ Check-Out: คำนวณเงิน + บันทึก payment + คืน slot + แจ้งเตือน
     *
     * @param array<int>|null $allowedLotIds จำกัดขอบเขตลานที่ผู้เรียกมีสิทธิ์ทำ check-out ให้
     *                                       (Admin ส่ง lot ที่ไม่มีเจ้าของ, Owner ส่ง lot ของตัวเอง)
     * @return array{success: bool, error: ?string, totalHours: ?int, parkingFee: ?float, deposit: ?float, totalAmount: ?float}
     */
    public function checkOut(ParkingLog $log, ?array $allowedLotIds = null): array
    {
        if ($allowedLotIds !== null && !in_array($log->parking_lot_id, $allowedLotIds, true)) {
            return $this->fail('ไม่มีสิทธิ์ทำ Check-Out ให้ลานจอดนี้');
        }

        if ($log->check_out_time !== null) {
            return $this->fail("ทะเบียน {$log->license_plate} Check-Out ไปแล้ว");
        }

        if ($log->payment()->exists()) {
            return $this->fail('มีการบันทึก payment สำหรับรายการนี้แล้ว');
        }

        $checkOut    = now();
        $diffMinutes = (int) $log->check_in_time->diffInMinutes($checkOut);
        $totalHours  = max(1, (int) ceil($diffMinutes / 60));
        $hourlyRate  = (float) $log->parkingLot->hourly_rate;
        $parkingFee  = round($totalHours * $hourlyRate, 2);

        $linkedReservation = $log->reservation_id
            ? Reservation::find($log->reservation_id)
            : null;
        $deposit     = min((float) ($linkedReservation?->reservation_fee ?? 0), $parkingFee);
        $totalAmount = round($parkingFee - $deposit, 2);

        DB::transaction(function () use ($log, $checkOut, $totalHours, $hourlyRate, $parkingFee, $deposit, $totalAmount, $linkedReservation) {
            $log->update(['check_out_time' => $checkOut]);

            Payment::create([
                'parking_log_id'       => $log->id,
                'reservation_id'       => $log->reservation_id,
                'total_hours'          => $totalHours,
                'hourly_rate'          => $hourlyRate,
                'parking_fee'          => $parkingFee,
                'reservation_discount' => $deposit,
                'total_amount'         => $totalAmount,
                'payment_status'       => $totalAmount <= 0 ? 'paid' : 'unpaid',
            ]);

            if ($log->parkingSlot) {
                $log->parkingSlot->update(['status' => 'available']);
            }

            if ($linkedReservation && $linkedReservation->status === 'checked_in') {
                $linkedReservation->update([
                    'status'       => 'completed',
                    'completed_at' => $checkOut,
                ]);

                ReservationLog::create([
                    'reservation_id' => $linkedReservation->id,
                    'old_status'     => 'checked_in',
                    'new_status'     => 'completed',
                    'changed_by'     => null,
                    'note'           => 'Auto completed: รถออกจากลานแล้ว',
                ]);
            }
        });

        // expire reservations ของทะเบียนนี้ที่เลย grace period แล้ว (ที่ยังไม่ได้ check-in)
        Reservation::where('license_plate', $log->license_plate)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '<=', now()->subMinutes(Reservation::gracePeriodMinutes()))
            ->update(['status' => 'expired']);

        $notifyUserId = $linkedReservation?->user_id
            ?? ($log->vehicle_id ? Vehicle::find($log->vehicle_id)?->user_id : null);

        if ($notifyUserId) {
            $msg = $deposit > 0
                ? sprintf(
                    'รถทะเบียน %s ออกจากลานแล้ว | จอด %d ชม. | ค่าจอด ฿%.2f | มัดจำ -฿%.2f | คงเหลือ ฿%.2f %s',
                    $log->license_plate,
                    $totalHours,
                    $parkingFee,
                    $deposit,
                    $totalAmount,
                    $totalAmount <= 0 ? '(ชำระแล้ว)' : '(รอชำระเงิน)',
                )
                : sprintf(
                    'รถทะเบียน %s ออกจากลานแล้ว | จอด %d ชม. | ค่าจอด ฿%.2f (รอชำระเงิน)',
                    $log->license_plate,
                    $totalHours,
                    $parkingFee,
                );
            notify_user($notifyUserId, 'เช็คเอาท์เรียบร้อย', $msg);
        }

        return [
            'success'     => true,
            'error'       => null,
            'totalHours'  => $totalHours,
            'parkingFee'  => $parkingFee,
            'deposit'     => $deposit,
            'totalAmount' => $totalAmount,
        ];
    }

    private function fail(string $message): array
    {
        return [
            'success'     => false,
            'error'       => $message,
            'totalHours'  => null,
            'parkingFee'  => null,
            'deposit'     => null,
            'totalAmount' => null,
        ];
    }
}
