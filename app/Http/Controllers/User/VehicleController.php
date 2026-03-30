<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VehicleController extends Controller
{
    public function index()
    {
        $vehicles = DB::table('vehicles')
            ->where('user_id', Auth::id())
            ->orderBy('license_plate')
            ->paginate(15);

        return view('user.vehicles.index', compact('vehicles'));
    }

    public function create()
    {
        return view('user.vehicles.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'license_plate' => ['required', 'string', 'max:20', 'unique:vehicles,license_plate'],
            'brand'         => ['nullable', 'string', 'max:60'],
            'color'         => ['nullable', 'string', 'max:40'],
        ]);

        Vehicle::create([
            'user_id'       => Auth::id(),
            'license_plate' => strtoupper(trim($data['license_plate'])),
            'brand'         => $data['brand'] ?? null,
            'color'         => $data['color'] ?? null,
        ]);

        return redirect()->route('user.vehicles.index')
            ->with('success', 'เพิ่มรถสำเร็จแล้ว');
    }

    public function destroy(Vehicle $vehicle)
    {
        abort_if($vehicle->user_id !== Auth::id(), 403);

        $vehicle->delete();

        return redirect()->route('user.vehicles.index')
            ->with('success', 'ลบรถออกจากระบบแล้ว');
    }
}
