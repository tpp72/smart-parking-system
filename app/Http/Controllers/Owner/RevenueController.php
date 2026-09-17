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

        // ยังรอยืนยันรับเงิน ณ ตอนนี้ (ไม่ขึ้นกับช่วงเวลา) — มัดจำ + ค่าจอด แยกกัน ตรงกับตัวเลขบนเมนูชำระเงิน
        $outstanding = fn (string $type) => DB::table('payments as p')
            ->join('reservations as r', 'r.id', '=', 'p.reservation_id')
            ->whereIn('r.parking_lot_id', $scopeLotIds)
            ->where('p.type', $type)
            ->where('p.payment_status', Payment::STATUS_UNPAID)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(p.total_amount), 0) as amount')
            ->first();

        $unpaidDeposits = $outstanding(Payment::TYPE_DEPOSIT);
        $unpaidCheckouts = $outstanding(Payment::TYPE_CHECKOUT);

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

        // รายได้ 12 เดือนล่าสุด (เงินที่รับจริง ตามเดือนที่ยืนยันรับเงิน)
        $monthly = RevenueQuery::paid($scopeLotIds)
            ->where('p.paid_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupByRaw("TO_CHAR(p.paid_at, 'YYYY-MM')")
            ->selectRaw("TO_CHAR(p.paid_at, 'YYYY-MM') as month_key, SUM(p.total_amount) as revenue")
            ->pluck('revenue', 'month_key');

        $revenueTrend = collect(range(11, 0))->map(function (int $ago) use ($monthly) {
            $month = now()->startOfMonth()->subMonths($ago);

            return ['label' => $month->translatedFormat('M y'), 'value' => (float) ($monthly[$month->format('Y-m')] ?? 0)];
        })->all();

        return view('owner.revenue.index', compact(
            'revenueTotal', 'depositRevenue', 'parkingRevenue', 'unpaidDeposits', 'unpaidCheckouts', 'transactionCount', 'reservationCount',
            'revenueByLot', 'revenueByDay', 'topStats', 'revenueTrend',
            'ownedLots', 'period', 'lotId', 'from', 'to'
        ));
    }
}
