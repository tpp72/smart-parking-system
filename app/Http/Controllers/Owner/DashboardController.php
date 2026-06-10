<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\OwnerApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user    = Auth::user();
        $ownerId = $user->id;

        // Show pending/rejected state for non-approved owners
        if ($user->owner_status !== 'approved') {
            $application = OwnerApplication::where('user_id', $ownerId)->latest()->first();
            return view('owner.dashboard', [
                'ownerStatus' => $user->owner_status,
                'application' => $application,
                'stats'              => null,
                'lotsOverview'       => collect(),
                'recentReservations' => collect(),
                'activeNow'          => collect(),
            ]);
        }

        $lotIds = DB::table('parking_lots')
            ->where('owner_id', $ownerId)
            ->pluck('id');

        $lotsTotal = $lotIds->count();

        $slotStats = DB::table('parking_slots')
            ->whereIn('parking_lot_id', $lotIds)
            ->selectRaw("
                COUNT(*) as slots_total,
                SUM(CASE WHEN status='available' THEN 1 ELSE 0 END) as slots_available,
                SUM(CASE WHEN status='reserved' THEN 1 ELSE 0 END) as slots_reserved,
                SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) as slots_occupied
            ")
            ->first();

        $activeNowCount = DB::table('parking_logs')
            ->whereIn('parking_lot_id', $lotIds)
            ->whereNull('check_out_time')
            ->count();

        $today = now();
        $revenueToday = (float) DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->whereIn('pl.parking_lot_id', $lotIds)
            ->where('p.payment_status', 'paid')
            ->whereDate('p.created_at', $today->toDateString())
            ->sum('p.total_amount');

        $revenueMonth = (float) DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->whereIn('pl.parking_lot_id', $lotIds)
            ->where('p.payment_status', 'paid')
            ->whereYear('p.created_at', $today->year)
            ->whereMonth('p.created_at', $today->month)
            ->sum('p.total_amount');

        $reservationsToday = DB::table('reservations')
            ->whereIn('parking_lot_id', $lotIds)
            ->whereDate('reserve_start', $today->toDateString())
            ->count();

        $pendingReservations = DB::table('reservations')
            ->whereIn('parking_lot_id', $lotIds)
            ->where('status', 'pending')
            ->count();

        $stats = [
            'lots_total'          => $lotsTotal,
            'slots_total'         => (int)($slotStats->slots_total ?? 0),
            'slots_available'     => (int)($slotStats->slots_available ?? 0),
            'slots_reserved'      => (int)($slotStats->slots_reserved ?? 0),
            'slots_occupied'      => (int)($slotStats->slots_occupied ?? 0),
            'active_now'          => $activeNowCount,
            'revenue_today'       => $revenueToday,
            'revenue_month'       => $revenueMonth,
            'reservations_today'  => $reservationsToday,
            'pending_reservations' => $pendingReservations,
        ];

        $lotsOverview = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->where('lot.owner_id', $ownerId)
            ->groupBy('lot.id', 'lot.name', 'lot.total_slots', 'lot.hourly_rate', 'lot.is_active')
            ->orderBy('lot.name')
            ->selectRaw("
                lot.id, lot.name, lot.total_slots, lot.hourly_rate, lot.is_active,
                SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) as occupied,
                SUM(CASE WHEN s.status='reserved' THEN 1 ELSE 0 END) as reserved
            ")
            ->get();

        $recentReservations = DB::table('reservations as r')
            ->join('vehicles as v', 'v.id', '=', 'r.vehicle_id')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereIn('r.parking_lot_id', $lotIds)
            ->orderByDesc('r.created_at')
            ->limit(8)
            ->select(['r.id', 'v.license_plate', 'u.name as user_name', 'lot.name as lot_name', 'r.reserve_start', 'r.status'])
            ->get();

        $activeNow = DB::table('parking_logs as pl')
            ->join('vehicles as v', 'v.id', '=', 'pl.vehicle_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->leftJoin('parking_slots as s', 's.id', '=', 'pl.parking_slot_id')
            ->whereIn('pl.parking_lot_id', $lotIds)
            ->whereNull('pl.check_out_time')
            ->orderByDesc('pl.check_in_time')
            ->limit(8)
            ->select(['pl.id as log_id', 'v.license_plate', 'lot.name as lot_name', 's.slot_number', 'pl.check_in_time'])
            ->get();

        return view('owner.dashboard', compact(
            'stats',
            'lotsOverview',
            'recentReservations',
            'activeNow'
        ) + ['ownerStatus' => 'approved', 'application' => null]);
    }
}
