<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'unpaid');

        $payments = Payment::with([
            'parkingLog.vehicle:id,license_plate,brand',
            'parkingLog.parkingLot:id,name',
        ])
            ->when($status !== 'all', fn($q) => $q->where('payment_status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', compact('payments', 'status'));
    }

    public function markPaid(Payment $payment)
    {
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
