<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Services\CheckOutService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function __construct(
        private ReservationService $reservations,
        private CheckOutService $checkOut,
    ) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'unpaid');
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');

        $payments = Payment::with([
            'parkingLog:id,license_plate,brand,parking_lot_id,reservation_id',
            'parkingLog.parkingLot:id,name',
            'reservation:id,user_id,parking_lot_id,license_plate,plate_province,brand',
            'reservation.user:id,name',
            'reservation.parkingLot:id,name',
        ])
            // Deposit ผูกกับลานผ่าน Reservation · Checkout ผูกผ่าน Parking Log
            ->where(fn ($q) => $q
                ->whereHas('parkingLog', fn ($x) => $x->whereIn('parking_lot_id', $ownedLotIds))
                ->orWhereHas('reservation', fn ($x) => $x->whereIn('parking_lot_id', $ownedLotIds)))
            ->when($status !== 'all', fn($q) => $q->where('payment_status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('owner.payments.index', compact('payments', 'status'));
    }

    /** ยืนยันรับเงิน (Mark as Paid) ของลานตัวเอง — Deposit: ยืนยันการจอง + Lock Slot · Checkout: ปิดยอดค้างชำระ */
    public function markPaid(Payment $payment)
    {
        $lotId = $payment->type === Payment::TYPE_DEPOSIT
            ? $payment->reservation?->parking_lot_id
            : $payment->parkingLog?->parking_lot_id;

        abort_unless(
            $lotId && ParkingLot::ownedBy(Auth::id())->whereKey($lotId)->exists(),
            403, 'ไม่มีสิทธิ์จัดการลานจอดนี้'
        );

        if ($payment->type === Payment::TYPE_DEPOSIT) {
            $result = $this->reservations->markDepositPaid($payment, Auth::user());

            if (!$result['success']) {
                return back()->withErrors(['error' => $result['error']]);
            }

            if ($result['outcome'] === ReservationService::OUTCOME_LOT_FULL) {
                return back()->withErrors(['error' => "ลานจอดเต็ม — ยกเลิกการจอง #{$payment->reservation_id} อัตโนมัติและเปลี่ยนเงินมัดจำเป็น void"]);
            }

            return back()->with('success', sprintf(
                'ยืนยันรับเงินมัดจำ ฿%s — การจอง #%d ได้รับการยืนยัน (ช่อง %s)',
                number_format((float) $payment->total_amount, 2),
                $payment->reservation_id,
                $result['slot']->slot_number
            ));
        }

        $result = $this->checkOut->markCheckoutPaid($payment, Auth::user());

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return back()->with('success', sprintf(
            'บันทึกการชำระเงิน ฿%s เรียบร้อยแล้ว',
            number_format((float) $payment->total_amount, 2)
        ));
    }
}
