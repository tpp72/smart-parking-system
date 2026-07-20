<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'unpaid');

        $payments = Payment::with([
            'parkingLog:id,license_plate,brand,parking_lot_id',
            'parkingLog.parkingLot:id,name',
        ])
            ->whereHas('parkingLog.parkingLot', fn($q) => $q->whereNull('owner_id'))
            ->when($status !== 'all', fn($q) => $q->where('payment_status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', compact('payments', 'status'));
    }

    public function markPaid(Payment $payment)
    {
        abort_if(
            ParkingLot::where('id', $payment->parkingLog->parking_lot_id)->whereNotNull('owner_id')->exists(),
            403, 'ลานจอดนี้มีเจ้าของแล้ว — เจ้าของลานเท่านั้นที่จัดการได้'
        );

        if ($payment->payment_status === 'paid') {
            return back()->withErrors(['error' => 'รายการนี้ชำระแล้ว']);
        }

        $payment->update(['payment_status' => 'paid']);

        admin_audit('payment.mark_paid', $payment->parkingLog, [
            'payment_id'   => $payment->id,
            'total_amount' => $payment->total_amount,
        ]);

        return back()->with('success', sprintf(
            'บันทึกการชำระเงิน ฿%s เรียบร้อยแล้ว',
            number_format((float) $payment->total_amount, 2)
        ));
    }
}
