<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ParkingLog;
use App\Models\Reservation;
use Illuminate\Support\Facades\DB;

class CheckOutController extends Controller
{
    /** แสดงรายการรถที่กำลังจอดอยู่ (check_out_time IS NULL) */
    public function index()
    {
        $logs = ParkingLog::with([
            'vehicle:id,license_plate,brand,color',
            'parkingLot:id,name,hourly_rate',
            'parkingSlot:id,slot_number',
        ])
            ->whereNull('check_out_time')
            ->orderBy('check_in_time')
            ->paginate(20);

        return view('admin.check-out.index', compact('logs'));
    }

    /** ทำ Check-Out: คำนวณเงิน + บันทึก payment + คืน slot */
    public function store(ParkingLog $log)
    {
        // [1] ห้าม check-out ซ้ำ
        if ($log->check_out_time !== null) {
            return redirect()->route('admin.check-out.index')
                ->withErrors(['error' => "ทะเบียน {$log->vehicle->license_plate} Check-Out ไปแล้ว"]);
        }

        // [2] ห้าม check-out ถ้ามี payment อยู่แล้ว (กัน double-submit)
        if ($log->payment()->exists()) {
            return redirect()->route('admin.check-out.index')
                ->withErrors(['error' => 'มีการบันทึก payment สำหรับรายการนี้แล้ว']);
        }

        // $log->check_in_time เป็น Carbon แล้ว (จาก $casts ใน ParkingLog)
        $checkOut    = now();
        $diffMinutes = (int) $log->check_in_time->diffInMinutes($checkOut);
        $totalHours  = max(1, (int) ceil($diffMinutes / 60));
        $hourlyRate  = (float) $log->parkingLot->hourly_rate;
        $parkingFee  = round($totalHours * $hourlyRate, 2);

        DB::transaction(function () use ($log, $checkOut, $totalHours, $hourlyRate, $parkingFee) {
            $log->update(['check_out_time' => $checkOut]);

            Payment::create([
                'parking_log_id'       => $log->id,
                'reservation_id'       => null,
                'total_hours'          => $totalHours,
                'hourly_rate'          => $hourlyRate,
                'parking_fee'          => $parkingFee,
                'reservation_discount' => 0,
                'total_amount'         => $parkingFee,
                'payment_status'       => 'unpaid',
            ]);

            if ($log->parkingSlot) {
                $log->parkingSlot->update(['status' => 'available']);
            }
        });

        // expire reservations ของรถคันนี้ที่เลยเวลาแล้ว
        Reservation::where('vehicle_id', $log->vehicle_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_end', '<=', now())
            ->update(['status' => 'expired']);

        $log->load('vehicle:id,license_plate', 'parkingSlot:id,slot_number');

        admin_audit('parking_log.check_out', $log, [
            'total_hours'  => $totalHours,
            'total_amount' => $parkingFee,
        ]);

        return redirect()->route('admin.check-out.index')
            ->with('success', sprintf(
                'Check-Out สำเร็จ! ทะเบียน %s | %d ชม. | ค่าจอด %.2f บาท',
                $log->vehicle->license_plate,
                $totalHours,
                $parkingFee,
            ));
    }
}
