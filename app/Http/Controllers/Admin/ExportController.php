<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use App\Models\Reservation;
use App\Queries\AdminExportQuery;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV Export ของ Admin (project-plan.md §17.5, §17.5.1) — หน้า Export กลาง + ไฟล์ของแต่ละประเภทข้อมูล
 * ข้อมูลทั้งระบบ ทุกลาน กรองลานได้ · Owner/User เข้าไม่ได้ (role:admin)
 */
class ExportController extends Controller
{
    public function index()
    {
        return view('admin.exports.index', [
            'lots'     => ParkingLot::with('owner:id,name')->orderBy('name')->get(['id', 'name', 'owner_id']),
            'statuses' => Reservation::STATUSES,
        ]);
    }

    public function reservations(Request $request): StreamedResponse
    {
        $filters = $this->filters($request, ['status' => ['nullable', Rule::in(Reservation::STATUSES)]]);

        return CsvExport::download('reservations', [
            'id', 'created_at', 'reserve_start', 'status', 'walk_in', 'parking_lot', 'lot_owner',
            'user_name', 'user_email', 'license_plate', 'plate_province', 'brand', 'color', 'slot_number',
            'deposit_amount', 'deposit_status', 'deposit_paid_at', 'reservation_fee', 'checked_in_at', 'completed_at',
        ], AdminExportQuery::reservations($filters), fn ($r) => [
            $r->id, $r->created_at, $r->reserve_start, $r->status, $r->is_walk_in ? 'yes' : 'no',
            $r->lot_name, $r->owner_name ?? 'Admin',
            $r->user_name, $r->user_email, $r->license_plate, $r->plate_province, $r->brand, $r->color, $r->slot_number,
            $r->deposit_amount, $r->deposit_status, $r->deposit_paid_at, $r->reservation_fee, $r->checked_in_at, $r->completed_at,
        ]);
    }

    public function parkingLogs(Request $request): StreamedResponse
    {
        $filters = $this->filters($request, ['state' => ['nullable', Rule::in(['parked', 'completed'])]]);

        return CsvExport::download('parking_logs', [
            'id', 'reservation_id', 'walk_in', 'parking_lot', 'lot_owner', 'slot_number',
            'license_plate', 'plate_province', 'brand', 'color', 'user_name',
            'check_in_time', 'check_out_time', 'hourly_rate', 'total_hours', 'parking_fee',
            'deposit_deduction', 'reservation_discount', 'total_amount', 'payment_status', 'paid_at',
        ], AdminExportQuery::parkingLogs($filters), fn ($r) => [
            $r->id, $r->reservation_id, $r->is_walk_in ? 'yes' : 'no', $r->lot_name, $r->owner_name ?? 'Admin', $r->slot_number,
            $r->license_plate, $r->plate_province, $r->brand, $r->color, $r->user_name,
            $r->check_in_time, $r->check_out_time, $r->hourly_rate, $r->total_hours, $r->parking_fee,
            $r->deposit_deduction, $r->reservation_discount, $r->total_amount, $r->payment_status, $r->paid_at,
        ]);
    }

    public function revenue(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return CsvExport::download('revenue_daily', [
            'date', 'parking_lot_id', 'parking_lot', 'lot_owner', 'deposit_revenue', 'parking_revenue', 'total_revenue', 'transactions',
        ], AdminExportQuery::dailyRevenue($filters), fn ($r) => [
            $r->day, $r->lot_id, $r->lot_name, $r->owner_name ?? 'Admin',
            $r->deposit_revenue, $r->parking_revenue, $r->total_revenue, $r->transactions,
        ]);
    }

    private function filters(Request $request, array $extra = []): array
    {
        return $request->validate([
            'q'      => ['nullable', 'string', 'max:100'],
            'lot_id' => ['nullable', 'integer', 'exists:parking_lots,id'],
            'from'   => ['nullable', 'date'],
            'to'     => ['nullable', 'date'],
        ] + $extra, [
            'lot_id.exists' => 'ไม่พบลานจอดที่เลือก',
            'from.date'     => 'วันที่เริ่มไม่ถูกต้อง',
            'to.date'       => 'วันที่สิ้นสุดไม่ถูกต้อง',
        ]);
    }
}
