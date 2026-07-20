<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Models\ParkingLog;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

class CheckOutController extends Controller
{
    /** แสดงรายการรถที่กำลังจอดอยู่ (check_out_time IS NULL) — เฉพาะลานที่ไม่มีเจ้าของ */
    public function index()
    {
        $logs = ParkingLog::with([
            'parkingLot:id,name,hourly_rate',
            'parkingSlot:id,slot_number',
            'reservation:id,reserve_start,status',
        ])
            ->whereHas('parkingLot', fn($q) => $q->whereNull('owner_id'))
            ->whereNull('check_out_time')
            ->orderBy('check_in_time')
            ->paginate(20);

        return view('admin.check-out.index', compact('logs'));
    }

    /** ทำ Check-Out: คำนวณเงิน + บันทึก payment + คืน slot */
    public function store(ParkingLog $log)
    {
        abort_if(
            ParkingLot::where('id', $log->parking_lot_id)->whereNotNull('owner_id')->exists(),
            403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้'
        );

        // [1] ห้าม check-out ซ้ำ
        if ($log->check_out_time !== null) {
            return redirect()->route('admin.check-out.index')
                ->withErrors(['error' => "ทะเบียน {$log->license_plate} Check-Out ไปแล้ว"]);
        }

        // [2] ห้าม check-out ถ้ามี payment อยู่แล้ว (กัน double-submit)
        if ($log->payment()->exists()) {
            return redirect()->route('admin.check-out.index')
                ->withErrors(['error' => 'มีการบันทึก payment สำหรับรายการนี้แล้ว']);
        }

        $checkOut    = now();
        $diffMinutes = (int) $log->check_in_time->diffInMinutes($checkOut);
        $totalHours  = max(1, (int) ceil($diffMinutes / 60));
        $hourlyRate  = (float) $log->parkingLot->hourly_rate;
        $parkingFee  = round($totalHours * $hourlyRate, 2);

        // Apply reservation deposit as discount
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

            // ถ้า check-in มาจากการจอง → mark completed
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

        // Notify ผู้จอง ถ้ามี reservation ผูกอยู่ ไม่งั้น fallback ไปหาเจ้าของ Vehicle (ถ้ามี vehicle_id)
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

        admin_audit('parking_log.check_out', $log, [
            'total_hours'          => $totalHours,
            'parking_fee'          => $parkingFee,
            'reservation_discount' => $deposit,
            'total_amount'         => $totalAmount,
        ]);

        return redirect()->route('admin.check-out.index')
            ->with('success', sprintf(
                'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด ฿%.2f | คงเหลือ ฿%.2f',
                $log->license_plate,
                $totalHours,
                $parkingFee,
                $totalAmount,
            ));
    }
}
