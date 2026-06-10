<?php

namespace App\Http\Controllers;

use App\Models\ParkingLot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'available');

        $lots = DB::table('parking_lots as lot')
            ->leftJoin('parking_slots as s', 's.parking_lot_id', '=', 'lot.id')
            ->leftJoin('users as u', 'u.id', '=', 'lot.owner_id')
            ->where('lot.is_active', true)
            ->when($q !== '', fn($query) => $query->where(function ($qq) use ($q) {
                $qq->where('lot.name', 'like', "%{$q}%")
                    ->orWhere('lot.location', 'like', "%{$q}%");
            }))
            ->groupBy('lot.id', 'lot.name', 'lot.location', 'lot.total_slots', 'lot.hourly_rate', 'lot.owner_id', 'u.name')
            ->selectRaw("
                lot.id, lot.name, lot.location, lot.total_slots, lot.hourly_rate,
                u.name as owner_name,
                SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN s.status='occupied' THEN 1 ELSE 0 END) as occupied,
                COUNT(s.id) as slot_count
            ")
            ->when($sort === 'rate', fn($q) => $q->orderBy('lot.hourly_rate'))
            ->when($sort !== 'rate', fn($q) => $q->orderByDesc(DB::raw("SUM(CASE WHEN s.status='available' THEN 1 ELSE 0 END)")))
            ->paginate(12)
            ->withQueryString();

        return view('marketplace.index', compact('lots', 'q', 'sort'));
    }
}
