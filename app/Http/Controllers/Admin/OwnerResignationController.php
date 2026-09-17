<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OwnerResignation;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Services\OwnerResignationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Admin พิจารณาคำร้องลาออกของ Owner (project-plan.md §16, §16.1) */
class OwnerResignationController extends Controller
{
    private const STATUSES = [
        OwnerResignation::STATUS_PENDING,
        OwnerResignation::STATUS_APPROVED,
        OwnerResignation::STATUS_REJECTED,
    ];

    public function __construct(private OwnerResignationService $resignations) {}

    public function index(Request $request)
    {
        $status = $request->query('status', OwnerResignation::STATUS_PENDING);

        $resignations = OwnerResignation::with(['user:id,name,email,role', 'reviewer:id,name'])
            ->when(in_array($status, self::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        // ผลกระทบถ้าอนุมัติ: ลานที่จะถูกลบ / การจองที่จะถูกยกเลิก / รถที่จะถูกเช็คเอาท์
        $impact = [];
        foreach ($resignations as $resignation) {
            if ($resignation->isPending()) {
                $lotIds = ParkingLot::ownedBy($resignation->user_id)->pluck('id');

                $impact[$resignation->id] = [
                    'lots'     => $lotIds->count(),
                    'bookings' => Reservation::whereIn('parking_lot_id', $lotIds)->whereIn('status', ['pending', 'confirmed'])->count(),
                    'parked'   => Reservation::whereIn('parking_lot_id', $lotIds)->where('status', 'checked_in')->count(),
                ];
            }
        }

        $counts = OwnerResignation::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.owner-resignations.index', compact('resignations', 'impact', 'status', 'counts'));
    }

    public function approve(OwnerResignation $ownerResignation)
    {
        $result = $this->resignations->approve($ownerResignation, Auth::user());

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return redirect()->route('admin.owner-resignations.index')->with('success', sprintf(
            'อนุมัติคำร้องลาออกแล้ว — ยกเลิกการจอง %d รายการ · เช็คเอาท์รถ %d คัน · ลบลานจอด %d แห่ง · บัญชีกลับเป็นผู้ใช้',
            $result['summary']['reservations_cancelled'],
            $result['summary']['cars_checked_out'],
            $result['summary']['lots_deleted']
        ));
    }

    public function reject(Request $request, OwnerResignation $ownerResignation)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'rejection_reason.required' => 'กรุณาระบุเหตุผลที่ไม่อนุมัติ',
            'rejection_reason.min'      => 'เหตุผลต้องมีอย่างน้อย 10 ตัวอักษร',
        ]);

        $result = $this->resignations->reject($ownerResignation, Auth::user(), $data['rejection_reason']);

        if (!$result['success']) {
            return back()->withErrors(['error' => $result['error']]);
        }

        return redirect()->route('admin.owner-resignations.index')->with('success', 'ไม่อนุมัติคำร้องลาออก — แจ้งเหตุผลให้เจ้าของลานแล้ว');
    }
}
