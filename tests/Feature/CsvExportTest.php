<?php

namespace Tests\Feature;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Phase 13 — CSV Export ของ Admin (project-plan.md §17.5, §17.5.1) */
class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private ParkingLot $adminLot;
    private ParkingLot $ownerLot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin');
        $this->adminLot = ParkingLot::factory()->create(['owner_id' => null, 'name' => 'ลานกลาง Admin']);
        $this->ownerLot = ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id, 'name' => 'ลานของ Owner']);
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    /** @return array<int, array<int, string>> แถวของ CSV (แถวแรก = หัวคอลัมน์) */
    private function rows(TestResponse $response): array
    {
        $csv = $response->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        return array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim(substr($csv, 3))));
    }

    /** @return array<int, array<string, string>> */
    private function records(TestResponse $response): array
    {
        $rows = $this->rows($response);
        $header = array_shift($rows);

        return array_map(fn ($row) => array_combine($header, $row), $rows);
    }

    private function completedWithPayment(ParkingLot $lot, float $amount, string $status, $paidAt): Reservation
    {
        $reservation = Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'completed']);
        $log = ParkingLog::factory()->create([
            'parking_lot_id' => $lot->id, 'reservation_id' => $reservation->id,
            'license_plate' => $reservation->license_plate, 'plate_province' => $reservation->plate_province,
            'check_in_time' => now()->subHours(3), 'check_out_time' => now()->subHour(),
        ]);
        Payment::create([
            'type' => Payment::TYPE_CHECKOUT, 'reservation_id' => $reservation->id, 'parking_log_id' => $log->id,
            'hourly_rate' => 30, 'total_amount' => $amount, 'payment_status' => $status, 'paid_at' => $paidAt,
        ]);

        return $reservation;
    }

    // ─── สิทธิ์ + หน้า Export กลาง ─────────────────────────────────────────────

    public function test_only_admin_can_open_export_page_and_download(): void
    {
        $this->actingAs($this->admin)->get(route('admin.exports.index'))
            ->assertOk()
            ->assertSee('รายงานรายได้รายวัน')
            ->assertSee('ลานของ Owner');

        $owner = $this->ownerLot->owner;
        foreach (['admin.exports.index', 'admin.exports.reservations', 'admin.exports.parking-logs', 'admin.exports.revenue'] as $route) {
            $this->actingAs($owner)->get(route($route))->assertRedirect(route('owner.dashboard'));
            $this->actingAs($this->makeUser('user'))->get(route($route))->assertForbidden();
        }

        // ปุ่ม Export ในหน้าจัดการ
        $this->actingAs($this->admin)->get(route('admin.reservations.index'))->assertSee(route('admin.exports.reservations'));
        $this->actingAs($this->admin)->get(route('admin.parking-logs.index'))->assertSee(route('admin.exports.parking-logs'));
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(route('admin.exports.index'));
    }

    // ─── ประวัติการจอง ────────────────────────────────────────────────────────

    public function test_reservation_export_covers_every_lot_and_applies_filters(): void
    {
        $adminBooking = Reservation::factory()->create(['parking_lot_id' => $this->adminLot->id, 'status' => 'pending']);
        $ownerBooking = Reservation::factory()->confirmed()->create(['parking_lot_id' => $this->ownerLot->id]);

        $records = collect($this->records($this->actingAs($this->admin)->get(route('admin.exports.reservations'))));
        $this->assertEqualsCanonicalizing([$adminBooking->id, $ownerBooking->id], $records->pluck('id')->map(fn ($id) => (int) $id)->all());

        $owner = $records->firstWhere('id', (string) $ownerBooking->id);
        $this->assertSame('ลานของ Owner', $owner['parking_lot']);
        $this->assertSame($this->ownerLot->owner->name, $owner['lot_owner']);
        $this->assertSame('confirmed', $owner['status']);
        $this->assertSame('Admin', $records->firstWhere('id', (string) $adminBooking->id)['lot_owner']);

        $filtered = $this->records($this->actingAs($this->admin)->get(route('admin.exports.reservations', [
            'lot_id' => $this->ownerLot->id, 'status' => 'confirmed',
        ])));
        $this->assertSame([(string) $ownerBooking->id], array_column($filtered, 'id'));

        $this->actingAs($this->admin)->get(route('admin.exports.reservations', ['from' => 'ไม่ใช่วันที่']))->assertSessionHasErrors('from');
        $this->actingAs($this->admin)->get(route('admin.exports.reservations', ['status' => 'unknown']))->assertSessionHasErrors('status');
    }

    public function test_cells_that_look_like_formulas_are_neutralised(): void
    {
        Reservation::factory()->create(['parking_lot_id' => $this->adminLot->id, 'brand' => '=HYPERLINK("http://x")']);

        $record = $this->records($this->actingAs($this->admin)->get(route('admin.exports.reservations')))[0];

        $this->assertSame('\'=HYPERLINK("http://x")', $record['brand']);
    }

    // ─── ประวัติการจอด ────────────────────────────────────────────────────────

    public function test_parking_log_export_includes_payment_and_filters_parked_cars(): void
    {
        $done = $this->completedWithPayment($this->adminLot, 90, Payment::STATUS_PAID, now());

        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $this->ownerLot->id, 'status' => 'occupied']);
        $parked = Reservation::factory()->walkIn()->create(['parking_lot_id' => $this->ownerLot->id, 'parking_slot_id' => $slot->id]);
        ParkingLog::factory()->create([
            'parking_lot_id' => $this->ownerLot->id, 'parking_slot_id' => $slot->id, 'reservation_id' => $parked->id,
            'license_plate' => $parked->license_plate, 'plate_province' => $parked->plate_province,
            'check_in_time' => now()->subHour(), 'check_out_time' => null,
        ]);

        $records = collect($this->records($this->actingAs($this->admin)->get(route('admin.exports.parking-logs'))));
        $this->assertCount(2, $records);

        $completed = $records->firstWhere('reservation_id', (string) $done->id);
        $this->assertSame(90.0, (float) $completed['total_amount']);
        $this->assertSame(Payment::STATUS_PAID, $completed['payment_status']);

        $onlyParked = $this->records($this->actingAs($this->admin)->get(route('admin.exports.parking-logs', ['state' => 'parked'])));
        $this->assertCount(1, $onlyParked);
        $this->assertSame((string) $parked->id, $onlyParked[0]['reservation_id']);
        $this->assertSame('yes', $onlyParked[0]['walk_in']);
        $this->assertSame('', $onlyParked[0]['check_out_time']);
    }

    // ─── รายงานรายได้รายวัน ────────────────────────────────────────────────────

    public function test_revenue_report_sums_money_received_per_day_and_lot(): void
    {
        $yesterday = now()->subDay();

        $deposit = Reservation::factory()->confirmed()->create(['parking_lot_id' => $this->ownerLot->id]);
        Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $deposit->id, 'hourly_rate' => 40,
            'total_amount' => 40, 'payment_status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);
        $this->completedWithPayment($this->ownerLot, 70, Payment::STATUS_PAID, now());
        $this->completedWithPayment($this->adminLot, 30, Payment::STATUS_PAID, $yesterday);
        $this->completedWithPayment($this->adminLot, 999, Payment::STATUS_UNPAID, null);      // ยังไม่รับเงิน
        $this->completedWithPayment($this->adminLot, 500, Payment::STATUS_PAID, now()->subMonths(3)); // นอกช่วง

        $range = ['from' => $yesterday->toDateString(), 'to' => now()->toDateString()];
        $records = collect($this->records($this->actingAs($this->admin)->get(route('admin.exports.revenue', $range))))
            ->keyBy(fn ($r) => $r['date'] . '|' . $r['parking_lot']);

        $this->assertCount(2, $records);

        $ownerToday = $records[now()->toDateString() . '|ลานของ Owner'];
        $this->assertSame([40.0, 70.0, 110.0], [(float) $ownerToday['deposit_revenue'], (float) $ownerToday['parking_revenue'], (float) $ownerToday['total_revenue']]);
        $this->assertSame('2', $ownerToday['transactions']);

        $adminYesterday = $records[$yesterday->toDateString() . '|ลานกลาง Admin'];
        $this->assertSame([0.0, 30.0, 30.0], [(float) $adminYesterday['deposit_revenue'], (float) $adminYesterday['parking_revenue'], (float) $adminYesterday['total_revenue']]);
        $this->assertSame('Admin', $adminYesterday['lot_owner']);

        $onlyAdminLot = $this->records($this->actingAs($this->admin)->get(route('admin.exports.revenue', $range + ['lot_id' => $this->adminLot->id])));
        $this->assertSame(['ลานกลาง Admin'], array_values(array_unique(array_column($onlyAdminLot, 'parking_lot'))));

        // ไม่ระบุช่วงวัน = เดือนปัจจุบัน
        $thisMonth = $this->records($this->actingAs($this->admin)->get(route('admin.exports.revenue')));
        $this->assertNotContains(now()->subMonths(3)->toDateString(), array_column($thisMonth, 'date'));
    }
}
