<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Payment;
use App\Queries\RevenueQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** รายได้ของ Owner = เงินที่รับจริง (มัดจำ + ค่าจอด) นับตามเวลาที่ยืนยันรับเงิน (project-plan.md §16.1) */
class RevenueController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->query('period', 'month');
        $lotId = $request->query('lot_id');

        $ownedLots = ParkingLot::where('owner_id', Auth::id())->orderBy('name')->get(['id', 'name']);
        $ownedLotIds = $ownedLots->pluck('id');

        // เลือกลานได้เฉพาะลานของตัวเอง
        $scopeLotIds = $lotId ? $ownedLotIds->intersect([(int) $lotId])->values() : $ownedLotIds;

        [$from, $to] = match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'year'  => [now()->startOfYear(), now()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfDay()],
        };

        $paid = fn () => RevenueQuery::paid($scopeLotIds)->whereBetween('p.paid_at', [$from, $to]);

        $revenueTotal     = (float) $paid()->sum('p.total_amount');
        $depositRevenue   = (float) $paid()->where('p.type', Payment::TYPE_DEPOSIT)->sum('p.total_amount');
        $parkingRevenue   = (float) $paid()->where('p.type', Payment::TYPE_CHECKOUT)->sum('p.total_amount');
        $transactionCount = $paid()->count();

        // ยอดค่าจอดหลัง Check-out ที่ยังรอ Mark as Paid
        $unpaidTotal = (float) DB::table('payments as p')
            ->join('reservations as r', 'r.id', '=', 'p.reservation_id')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->where('p.type', Payment::TYPE_CHECKOUT)
            ->where('p.payment_status', Payment::STATUS_UNPAID)
            ->whereBetween('p.created_at', [$from, $to])
            ->sum('p.total_amount');

        $reservationCount = DB::table('reservations as r')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->whereBetween('r.created_at', [$from, $to])
            ->count();

        $revenueByLot = $paid()
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->groupBy('lot.id', 'lot.name')
            ->selectRaw('lot.id, lot.name, SUM(p.total_amount) as revenue, COUNT(*) as transactions')
            ->orderByDesc('revenue')
            ->get();

        $revenueByDay = $paid()
            ->groupByRaw('DATE(p.paid_at)')
            ->selectRaw('DATE(p.paid_at) as day, SUM(p.total_amount) as revenue, COUNT(*) as transactions')
            ->orderBy('day')
            ->get();

        $topStats = DB::table('reservations as r')
            ->join('parking_lots as lot', 'lot.id', '=', 'r.parking_lot_id')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->whereBetween('r.created_at', [$from, $to])
            ->groupBy('lot.id', 'lot.name')
            ->selectRaw('lot.id, lot.name, COUNT(*) as reservations')
            ->orderByDesc('reservations')
            ->first();

        $occupancyRate = $scopeLotIds->isNotEmpty()
            ? (float) DB::table('parking_slots')
                ->whereIn('parking_lot_id', $scopeLotIds)
                ->selectRaw("
                    ROUND(
                        100.0 * SUM(CASE WHEN status='occupied' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0),
                        1
                    ) as rate
                ")
                ->value('rate')
            : 0;

        return view('owner.revenue.index', compact(
            'revenueTotal', 'depositRevenue', 'parkingRevenue', 'unpaidTotal', 'transactionCount', 'reservationCount',
            'revenueByLot', 'revenueByDay', 'topStats', 'occupancyRate',
            'ownedLots', 'period', 'lotId', 'from', 'to'
        ));
    }
}
