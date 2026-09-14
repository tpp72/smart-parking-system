<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\LicensePlateScan;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Phase 1 — โครงสร้าง Database / Data Model ตาม docs/project-plan.md */
class DataModelTest extends TestCase
{
    use RefreshDatabase;

    /** รันคำสั่งใน savepoint แล้วยืนยันว่า Database ปฏิเสธ (constraint violation) */
    private function assertRejectedByDatabase(callable $callback): void
    {
        try {
            DB::transaction(fn () => $callback());
        } catch (QueryException $e) {
            $this->addToAssertionCount(1);
            return;
        }

        $this->fail('Expected the database to reject this write.');
    }

    private function reservation(array $attrs = []): Reservation
    {
        return Reservation::factory()->create($attrs);
    }

    // ─── Vehicle / Legacy ───────────────────────────────────────────────────

    public function test_vehicle_architecture_is_removed(): void
    {
        $this->assertFalse(Schema::hasTable('vehicles'));
        $this->assertFileDoesNotExist(app_path('Models/Vehicle.php'));

        foreach (['reservations', 'parking_logs', 'license_plate_scans'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'vehicle_id'), "{$table}.vehicle_id still exists");
        }

        $this->assertFalse(Schema::hasColumn('parking_lots', 'is_active'));
        $this->assertFalse(Schema::hasColumn('admin_actions', 'admin_id'));
    }

    // ─── Walkin User ────────────────────────────────────────────────────────

    public function test_walkin_system_user_exists(): void
    {
        $walkin = User::walkin();

        $this->assertSame('Walkin User', $walkin->name);
        $this->assertSame('user', $walkin->role);
        $this->assertTrue($walkin->is_system);
    }

    public function test_walk_in_reservation_has_no_deposit_and_no_discount(): void
    {
        $reservation = Reservation::factory()->walkIn()->create();

        $this->assertSame(User::walkin()->id, $reservation->user_id);
        $this->assertTrue($reservation->is_walk_in);
        $this->assertSame('checked_in', $reservation->status);
        $this->assertEquals(0, (float) $reservation->deposit_amount);
        $this->assertEquals(0, (float) $reservation->reservation_fee);

        $this->assertRejectedByDatabase(fn () => Reservation::factory()->walkIn()->create(['deposit_amount' => 40]));
        $this->assertRejectedByDatabase(fn () => Reservation::factory()->walkIn()->create(['reservation_fee' => 40]));
    }

    public function test_walk_in_is_not_counted_in_the_one_active_reservation_rule(): void
    {
        $car = ['license_plate' => 'กข 7777', 'plate_province' => 'ระยอง'];

        $this->reservation($car + ['status' => 'confirmed']);
        Reservation::factory()->walkIn()->create($car);

        $this->assertRejectedByDatabase(fn () => $this->reservation($car + ['status' => 'pending']));
        $this->assertDatabaseCount('reservations', 2);
    }

    // ─── Slots ──────────────────────────────────────────────────────────────

    public function test_slot_number_is_unique_within_a_lot(): void
    {
        $lotA = ParkingLot::factory()->create();
        $lotB = ParkingLot::factory()->create();

        ParkingSlot::factory()->create(['parking_lot_id' => $lotA->id, 'slot_number' => 'A001']);

        $this->assertRejectedByDatabase(fn () => ParkingSlot::factory()->create(['parking_lot_id' => $lotA->id, 'slot_number' => 'A001']));

        ParkingSlot::factory()->create(['parking_lot_id' => $lotB->id, 'slot_number' => 'A001']);
        $this->assertDatabaseCount('parking_slots', 2);
    }

    public function test_slot_status_is_restricted(): void
    {
        $this->assertRejectedByDatabase(fn () => ParkingSlot::factory()->create(['status' => 'broken']));
    }

    // ─── Reservations ───────────────────────────────────────────────────────

    public function test_one_active_reservation_per_plate_and_province(): void
    {
        $first = $this->reservation(['license_plate' => 'กข 1234', 'plate_province' => 'กรุงเทพมหานคร', 'status' => 'pending']);

        $this->assertRejectedByDatabase(fn () => $this->reservation([
            'license_plate' => 'กข 1234', 'plate_province' => 'กรุงเทพมหานคร', 'status' => 'confirmed',
        ]));

        // ทะเบียนเดียวกันแต่คนละจังหวัด = คนละคัน
        $this->reservation(['license_plate' => 'กข 1234', 'plate_province' => 'เชียงใหม่', 'status' => 'pending']);

        // เมื่อรายการแรกไม่ Active แล้ว จองใหม่ได้
        $first->update(['status' => 'cancelled']);
        $this->reservation(['license_plate' => 'กข 1234', 'plate_province' => 'กรุงเทพมหานคร', 'status' => 'pending']);

        $this->assertDatabaseCount('reservations', 3);
    }

    public function test_reservation_status_is_restricted(): void
    {
        $this->assertRejectedByDatabase(fn () => $this->reservation(['status' => 'waiting']));
    }

    public function test_deposit_and_reservation_fee_are_separate_non_negative_amounts(): void
    {
        $reservation = $this->reservation(['deposit_amount' => 50, 'reservation_fee' => 50]);

        $this->assertEquals(50, (float) $reservation->fresh()->deposit_amount);
        $this->assertEquals(50, (float) $reservation->fresh()->reservation_fee);

        $this->assertRejectedByDatabase(fn () => $this->reservation(['deposit_amount' => -1]));
    }

    public function test_reservation_requires_plate_and_province(): void
    {
        $this->assertRejectedByDatabase(fn () => $this->reservation(['plate_province' => null]));
    }

    // ─── Payments ───────────────────────────────────────────────────────────

    private function depositPayment(Reservation $reservation, array $attrs = []): Payment
    {
        return Payment::create(array_merge([
            'type'           => Payment::TYPE_DEPOSIT,
            'reservation_id' => $reservation->id,
            'hourly_rate'    => 40,
            'total_amount'   => 40,
            'payment_status' => Payment::STATUS_UNPAID,
        ], $attrs));
    }

    public function test_deposit_payment_exists_before_parking_log_and_only_once(): void
    {
        $reservation = $this->reservation(['deposit_amount' => 40]);

        $payment = $this->depositPayment($reservation);
        $this->assertNull($payment->parking_log_id);

        $this->assertRejectedByDatabase(fn () => $this->depositPayment($reservation));
    }

    public function test_deposit_payment_cannot_reference_parking_log(): void
    {
        $reservation = $this->reservation(['status' => 'checked_in']);
        $log = ParkingLog::factory()->create(['reservation_id' => $reservation->id, 'parking_lot_id' => $reservation->parking_lot_id]);

        $this->assertRejectedByDatabase(fn () => $this->depositPayment($reservation, ['parking_log_id' => $log->id]));
    }

    public function test_checkout_payment_requires_parking_log(): void
    {
        $reservation = $this->reservation();

        $this->assertRejectedByDatabase(fn () => Payment::create([
            'type'           => Payment::TYPE_CHECKOUT,
            'reservation_id' => $reservation->id,
            'hourly_rate'    => 40,
            'total_amount'   => 40,
        ]));
    }

    public function test_payment_status_supports_void_only_among_defined_states(): void
    {
        $payment = $this->depositPayment($this->reservation(), ['payment_status' => Payment::STATUS_VOID]);
        $this->assertSame('void', $payment->fresh()->payment_status);

        $this->assertRejectedByDatabase(fn () => $this->depositPayment($this->reservation(), ['payment_status' => 'refunded']));
    }

    public function test_paid_payment_requires_paid_at_and_records_confirmer(): void
    {
        $this->assertRejectedByDatabase(fn () => $this->depositPayment($this->reservation(), ['payment_status' => Payment::STATUS_PAID]));

        $admin = User::factory()->create(['role' => 'admin']);
        $payment = $this->depositPayment($this->reservation(), [
            'payment_status' => Payment::STATUS_PAID,
            'paid_by'        => $admin->id,
            'paid_at'        => now(),
        ]);

        $this->assertSame($admin->id, $payment->paidBy->id);
    }

    public function test_payment_amounts_cannot_be_negative(): void
    {
        $this->assertRejectedByDatabase(fn () => $this->depositPayment($this->reservation(), ['total_amount' => -10]));
    }

    // ─── Parking Logs ───────────────────────────────────────────────────────

    public function test_car_can_have_only_one_open_parking_log(): void
    {
        $lot = ParkingLot::factory()->create();
        $car = ['license_plate' => 'ทด 5555', 'plate_province' => 'ภูเก็ต', 'parking_lot_id' => $lot->id];

        ParkingLog::factory()->create($car + ['check_out_time' => null]);

        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create($car + ['check_out_time' => null]));

        ParkingLog::factory()->create($car + ['check_in_time' => now()->subHours(5), 'check_out_time' => now()->subHours(4)]);
        $this->assertDatabaseCount('parking_logs', 2);
    }

    public function test_parking_log_requires_reservation_and_province(): void
    {
        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create(['reservation_id' => null]));
        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create(['plate_province' => null]));
        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create(['hourly_rate' => null]));
        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create(['hourly_rate' => -1]));
    }

    public function test_check_out_time_cannot_precede_check_in_time(): void
    {
        $this->assertRejectedByDatabase(fn () => ParkingLog::factory()->create([
            'check_in_time'  => now(),
            'check_out_time' => now()->subHour(),
        ]));
    }

    // ─── Blacklist / Scans ──────────────────────────────────────────────────

    public function test_blacklist_is_unique_by_plate_and_province(): void
    {
        SuspiciousVehicle::factory()->create(['license_plate' => 'ฆฒ 1000', 'plate_province' => 'สงขลา']);

        $this->assertRejectedByDatabase(fn () => SuspiciousVehicle::factory()->create(['license_plate' => 'ฆฒ 1000', 'plate_province' => 'สงขลา']));
        $this->assertRejectedByDatabase(fn () => SuspiciousVehicle::factory()->create(['level' => 'extreme']));

        SuspiciousVehicle::factory()->create(['license_plate' => 'ฆฒ 1000', 'plate_province' => 'ตรัง']);
        $this->assertDatabaseCount('suspicious_vehicles', 2);
    }

    public function test_scan_stores_unreadable_plate_and_bounded_accuracy(): void
    {
        $lot = ParkingLot::factory()->create();
        $base = ['parking_lot_id' => $lot->id, 'scan_time' => now()];

        $scan = LicensePlateScan::create($base + ['license_plate' => null, 'confidence' => 12.5, 'result' => 'unreadable']);
        $this->assertNull($scan->fresh()->license_plate);

        $this->assertRejectedByDatabase(fn () => LicensePlateScan::create($base + ['license_plate' => 'กข 1', 'confidence' => 150, 'result' => 'passed']));
        $this->assertRejectedByDatabase(fn () => LicensePlateScan::create($base + ['license_plate' => 'กข 1', 'confidence' => 90, 'result' => 'maybe']));
        $this->assertRejectedByDatabase(fn () => LicensePlateScan::create($base + ['license_plate' => 'กข 1', 'confidence' => 90]));
    }

    // ─── Audit Log ──────────────────────────────────────────────────────────

    public function test_audit_log_records_actor_role_for_every_role_and_system(): void
    {
        foreach (['user', 'owner', 'admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            audit_log("test.{$role}");
            $this->assertDatabaseHas('admin_actions', ['action' => "test.{$role}", 'actor_role' => $role]);
        }

        audit_by(null, 'test.system');
        $this->assertDatabaseHas('admin_actions', ['action' => 'test.system', 'actor_role' => 'system', 'actor_id' => null, 'ip_address' => null]);

        auth()->logout();
        audit_log('test.no_login');
        $this->assertDatabaseHas('admin_actions', ['action' => 'test.no_login', 'actor_role' => 'system', 'actor_id' => null]);

        $this->assertRejectedByDatabase(fn () => AdminAction::create(['action' => 'x', 'actor_role' => 'guest']));
    }

    // ─── Delete behavior (cascade) ──────────────────────────────────────────

    public function test_deleting_parking_lot_removes_its_slots_reservations_logs_and_payments(): void
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->reservation(['parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id, 'status' => 'completed']);
        $log = ParkingLog::factory()->create([
            'parking_lot_id' => $lot->id,
            'reservation_id' => $reservation->id,
            'check_in_time'  => now()->subHours(2),
            'check_out_time' => now(),
        ]);
        $this->depositPayment($reservation);
        Payment::create([
            'type' => Payment::TYPE_CHECKOUT, 'reservation_id' => $reservation->id, 'parking_log_id' => $log->id,
            'hourly_rate' => 40, 'total_amount' => 40,
        ]);
        LicensePlateScan::create(['parking_lot_id' => $lot->id, 'license_plate' => 'กข 9', 'result' => 'passed', 'scan_time' => now()]);

        $lot->delete();

        foreach (['parking_slots', 'reservations', 'parking_logs', 'payments', 'license_plate_scans'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_deleting_owner_removes_owned_lots(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'owner_status' => 'approved']);
        ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $adminLot = ParkingLot::factory()->create(['owner_id' => null]);

        $owner->delete();

        $this->assertDatabaseCount('parking_lots', 1);
        $this->assertDatabaseHas('parking_lots', ['id' => $adminLot->id]);
    }
}
