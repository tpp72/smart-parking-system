<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'unpaid');
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');

        $payments = Payment::with([
            'parkingLog.vehicle:id,license_plate,brand',
            'parkingLog.parkingLot:id,name',
        ])
            ->whereHas('parkingLog', fn($q) => $q->whereIn('parking_lot_id', $ownedLotIds))
            ->when($status !== 'all', fn($q) => $q->where('payment_status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('owner.payments.index', compact('payments', 'status'));
    }

    public function markPaid(Payment $payment)
    {
        $ownedLotIds = ParkingLot::ownedBy(Auth::id())->pluck('id');
        abort_unless($ownedLotIds->contains($payment->parkingLog->parking_lot_id), 403, 'ไม่มีสิทธิ์จัดการลานจอดนี้');

        if ($payment->payment_status === 'paid') {
            return back()->withErrors(['error' => 'รายการนี้ชำระแล้ว']);
        }

        $payment->update(['payment_status' => 'paid']);

        return back()->with('success', sprintf(
            'บันทึกการชำระเงิน ฿%s เรียบร้อยแล้ว',
            number_format((float) $payment->total_amount, 2)
        ));
    }
}
