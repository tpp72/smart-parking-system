<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'month');
        $lotId = $request->query('lot_id');

        $ownedLots = ParkingLot::where('owner_id', Auth::id())->orderBy('name')->get(['id', 'name']);
        $ownedLotIds = $ownedLots->pluck('id');

        [$from, $to] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'year'  => [now()->startOfYear(), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };

        $lotFilter = fn($q) => $q->when($lotId, fn($qq) => $qq->where('pl.parking_lot_id', $lotId))
            ->whereIn('pl.parking_lot_id', $ownedLotIds);

        $revenueTotal = (float) DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->where('p.payment_status', 'paid')
            ->whereBetween('p.created_at', [$from, $to])
            ->tap($lotFilter)
            ->sum('p.total_amount');

        $unpaidTotal = (float) DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->where('p.payment_status', 'unpaid')
            ->whereBetween('p.created_at', [$from, $to])
            ->tap($lotFilter)
            ->sum('p.total_amount');

        $transactionCount = DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->where('p.payment_status', 'paid')
            ->whereBetween('p.created_at', [$from, $to])
            ->tap($lotFilter)
            ->count();

        $reservationCount = DB::table('reservations as r')
            ->whereIn('r.parking_lot_id', $ownedLotIds)
            ->when($lotId, fn($q) => $q->where('r.parking_lot_id', $lotId))
            ->whereBetween('r.created_at', [$from, $to])
            ->count();

        $revenueByLot = DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->join('parking_lots as lot', 'lot.id', '=', 'pl.parking_lot_id')
            ->where('p.payment_status', 'paid')
            ->whereBetween('p.created_at', [$from, $to])
            ->whereIn('pl.parking_lot_id', $ownedLotIds)
            ->when($lotId, fn($q) => $q->where('pl.parking_lot_id', $lotId))
            ->groupBy('lot.id', 'lot.name')
            ->selectRaw('lot.id, lot.name, SUM(p.total_amount) as revenue, COUNT(*) as transactions')
            ->orderByDesc('revenue')
            ->get();

        $revenueByDay = DB::table('payments as p')
            ->join('parking_logs as pl', 'pl.id', '=', 'p.parking_log_id')
            ->where('p.payment_status', 'paid')
            ->whereBetween('p.created_at', [$from, $to])
            ->whereIn('pl.parking_lot_id', $ownedLotIds)
            ->when($lotId, fn($q) => $q->where('pl.parking_lot_id', $lotId))
            ->groupByRaw("DATE(p.created_at)")
            ->selectRaw("DATE(p.created_at) as day, SUM(p.total_amount) as revenue, COUNT(*) as transactions")
            ->orderBy('day')
            ->get();

        $topStats = DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereIn('r.parking_lot_id', $ownedLotIds)
            ->when($lotId, fn($q) => $q->where('r.parking_lot_id', $lotId))
            ->whereBetween('r.created_at', [$from, $to])
            ->groupBy('lot.id', 'lot.name')
            ->selectRaw('lot.id, lot.name, COUNT(*) as reservations')
            ->orderByDesc('reservations')
            ->first();

        $occupancyRate = $ownedLotIds->isNotEmpty()
            ? (float) DB::table('parking_slots')
                ->whereIn('parking_lot_id', $ownedLotIds)
                ->when($lotId, fn($q) => $q->where('parking_lot_id', $lotId))
                ->selectRaw("
                    ROUND(
                        100.0 * SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                        1
                    ) as rate
                ")
                ->value('rate')
            : 0;

        return view('owner.revenue.index', compact(
            'revenueTotal', 'unpaidTotal', 'transactionCount', 'reservationCount',
            'revenueByLot', 'revenueByDay', 'topStats', 'occupancyRate',
            'ownedLots', 'period', 'lotId', 'from', 'to'
        ));
    }
}
