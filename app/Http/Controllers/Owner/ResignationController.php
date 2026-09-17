<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Services\OwnerResignationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Owner ยื่นคำร้องลาออก — มีผลเมื่อ Admin อนุมัติ (project-plan.md §16) */
class ResignationController extends Controller
{
    public function __construct(private OwnerResignationService $resignations) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลในการลาออก',
            'reason.max'      => 'เหตุผลต้องไม่เกิน 1000 ตัวอักษร',
        ]);

        $result = $this->resignations->submit(Auth::user(), $data['reason']);

        if (!$result['success']) {
            return back()->withErrors(['reason' => $result['error']]);
        }

        return redirect()->route('owner.dashboard')
            ->with('success', 'ส่งคำร้องลาออกแล้ว — คุณยังคงเป็นเจ้าของลานจอดจนกว่าผู้ดูแลระบบจะอนุมัติ');
    }
}
