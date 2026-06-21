<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        $lotId = $request->query('lot_id');
        $from = $request->query('from');
        $to = $request->query('to');

        $ownedLots = ParkingLot::where('owner_id', Auth::id())->get(['id', 'name']);
        $ownedLotIds = $ownedLots->pluck('id');

        $reservations = Reservation::with([
            'user:id,name,email',
            'vehicle:id,user_id,license_plate',
            'parkingLot:id,name',
            'parkingSlot:id,parking_lot_id,slot_number',
        ])
            ->whereIn('parking_lot_id', $ownedLotIds)
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->whereHas('vehicle', fn($x) => $x->where('license_plate', 'like', "%{$q}%"))
                    ->orWhereHas('user', fn($x) => $x->where('name', 'like', "%{$q}%"));
            }))
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($lotId, fn($query) => $query->where('parking_lot_id', $lotId))
            ->when($from, fn($query) => $query->whereDate('reserve_start', '>=', $from))
            ->when($to, fn($query) => $query->whereDate('reserve_start', '<=', $to))
            ->orderByDesc('reserve_start')
            ->paginate(15)
            ->withQueryString();

        $statuses = ['pending', 'confirmed', 'checked_in', 'completed', 'cancelled', 'expired'];

        return view('owner.reservations.index', compact(
            'reservations', 'ownedLots', 'q', 'status', 'lotId', 'from', 'to', 'statuses'
        ));
    }

    public function confirm(Reservation $reservation)
    {
        $ownedLotIds = ParkingLot::where('owner_id', Auth::id())->pluck('id');
        abort_unless($ownedLotIds->contains($reservation->parking_lot_id), 403);

        if ($reservation->status !== 'pending') {
            return back()->withErrors(['error' => "ไม่สามารถยืนยันได้ สถานะปัจจุบันคือ '{$reservation->status}'"]);
        }

        $slotError = null;

        DB::transaction(function () use ($reservation, &$slotError) {
            if ($reservation->parking_slot_id) {
                $slot = ParkingSlot::where('id', $reservation->parking_slot_id)
                    ->lockForUpdate()
                    ->first();

                if (!$slot || $slot->status !== 'available') {
                    $slotError = "ช่องจอดที่จองไว้ไม่พร้อมใช้งาน (สถานะ: {$slot?->status})";
                    return;
                }

                $slot->update(['status' => 'reserved']);
            }

            $reservation->update(['status' => 'confirmed']);

            ReservationLog::create([
                'reservation_id' => $reservation->id,
                'old_status'     => 'pending',
                'new_status'     => 'confirmed',
                'changed_by'     => Auth::id(),
                'note'           => 'เจ้าของลานจอดยืนยันการจอง',
            ]);
        });

        if ($slotError) {
            return back()->withErrors(['error' => $slotError]);
        }

        notify_user(
            $reservation->user_id,
            'การจองได้รับการยืนยัน',
            "การจอง #{$reservation->id} ของคุณได้รับการยืนยันแล้ว กรุณาเช็คอินภายในเวลาที่กำหนด"
        );

        return back()->with('success', "ยืนยันการจอง #{$reservation->id} เรียบร้อยแล้ว");
    }
}
