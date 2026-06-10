<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcrCheckInTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function regularUser(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    // ─── CheckInService unit tests ───────────────────────────────────────────

    // ─── [1] successful check-in without reservation ────────────────────────

    public function test_check_in_service_succeeds_with_available_slot(): void
    {
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create();

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['log']);
        $this->assertNotNull($result['slot']);
        $this->assertNull($result['reservation']);

        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'occupied',
        ]);
    }

    // ─── [2] check-in finds checkable reservation ───────────────────────────

    public function test_check_in_service_links_reservation_when_checkable(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subMinutes(5),
            'status'         => 'confirmed',
        ]);

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        $this->assertTrue($result['success']);
        $this->assertNotNull($result['reservation']);
        $this->assertEquals($reservation->id, $result['reservation']->id);

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'checked_in',
        ]);
    }

    // ─── [3] blocked when vehicle already parked ────────────────────────────

    public function test_check_in_service_fails_when_vehicle_already_parked(): void
    {
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        ParkingLog::factory()->create([
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'check_out_time' => null,
        ]);

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('จอดอยู่แล้ว', $result['error']);
    }

    // ─── [4] blocked when no available slots ────────────────────────────────

    public function test_check_in_service_fails_when_no_available_slots(): void
    {
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        ParkingSlot::factory()->count(3)->occupied()->create(['parking_lot_id' => $lot->id]);

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('ช่องจอดว่าง', $result['error']);
    }

    // ─── [5] duplicate check-in prevented ───────────────────────────────────

    public function test_check_in_service_prevents_duplicate_check_in(): void
    {
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $service = app(CheckInService::class);

        // First check-in
        $result1 = $service->checkIn($vehicle->id, $lot->id);
        $this->assertTrue($result1['success']);

        // Second check-in — should fail
        $result2 = $service->checkIn($vehicle->id, $lot->id);
        $this->assertFalse($result2['success']);

        // Only 1 parking log entry
        $this->assertDatabaseCount('parking_logs', 1);
    }

    // ─── [6] expired reservation is NOT checked in ──────────────────────────

    public function test_expired_reservation_is_not_used_for_check_in(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        // Reservation far in the past (outside grace window)
        Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subHours(5),
            'status'         => 'confirmed',
        ]);

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        // Check-in still succeeds (no reservation matched, just walk-in)
        $this->assertTrue($result['success']);
        // But reservation_id on the log is null
        $this->assertNull($result['reservation']);
    }

    // ─── [7] findMatchingReservation returns confirmed reservation ───────────

    public function test_find_matching_reservation_returns_confirmed(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->confirmed()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHour(),
        ]);

        $service = app(\App\Services\CarScanService::class);
        $found   = $service->findMatchingReservation($vehicle->license_plate);

        $this->assertNotNull($found);
        $this->assertEquals($reservation->id, $found->id);
    }

    // ─── [8] findMatchingReservation ignores cancelled/expired ──────────────

    public function test_find_matching_reservation_ignores_inactive_statuses(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHour(),
            'status'         => 'cancelled',
        ]);

        Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subHours(2),
            'status'         => 'expired',
        ]);

        $service = app(\App\Services\CarScanService::class);
        $found   = $service->findMatchingReservation($vehicle->license_plate);

        $this->assertNull($found);
    }

    // ─── [9] findMatchingReservation returns null for unknown plate ──────────

    public function test_find_matching_reservation_returns_null_for_unknown_plate(): void
    {
        $service = app(\App\Services\CarScanService::class);
        $found   = $service->findMatchingReservation('กข 9999');

        $this->assertNull($found);
    }

    // ─── [10] check-in service creates reservation_log ──────────────────────

    public function test_check_in_service_writes_reservation_log_on_linked_reservation(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subMinutes(5),
            'status'         => 'confirmed',
        ]);

        app(CheckInService::class)->checkIn($vehicle->id, $lot->id);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'confirmed',
            'new_status'     => 'checked_in',
        ]);
    }
}
