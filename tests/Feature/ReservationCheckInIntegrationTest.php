<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCheckInIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function setupLot(): array
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        return [$lot, $slot];
    }

    private function confirmedReservation(Vehicle $vehicle, ParkingLot $lot, array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'user_id'        => $vehicle->user_id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now(),
            'status'         => 'confirmed',
        ], $overrides));
    }

    // ─── [1] confirmed → check-in → checked_in ──────────────────────────────

    public function test_confirmed_reservation_transitions_to_checked_in_on_check_in(): void
    {
        $user    = User::factory()->create(['force_password_reset' => false]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        [$lot]   = $this->setupLot();

        $reservation = $this->confirmedReservation($vehicle, $lot);

        $service = app(CheckInService::class);
        $result  = $service->checkIn($vehicle->id, $lot->id);

        $this->assertTrue($result['success']);

        // Reservation status → checked_in
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'checked_in',
        ]);

        // checked_in_at is set
        $reservation->refresh();
        $this->assertNotNull($reservation->checked_in_at);

        // ParkingLog links back to reservation
        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'reservation_id' => $reservation->id,
        ]);

        // ReservationLog audit trail written
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'confirmed',
            'new_status'     => 'checked_in',
        ]);
    }

    // ─── [2] check-out → completed ──────────────────────────────────────────

    public function test_check_out_transitions_reservation_to_completed(): void
    {
        $user    = User::factory()->create(['force_password_reset' => false]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        [$lot, $slot] = $this->setupLot();

        $reservation = $this->confirmedReservation($vehicle, $lot);

        // Perform check-in first
        $checkInResult = app(CheckInService::class)->checkIn($vehicle->id, $lot->id);
        $this->assertTrue($checkInResult['success']);

        $log = $checkInResult['log'];

        // Perform check-out via admin HTTP route
        $admin = User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.check-out.store', $log))
            ->assertRedirect(route('admin.check-out.index'));

        // Reservation → completed
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'completed',
        ]);

        // completed_at is set
        $reservation->refresh();
        $this->assertNotNull($reservation->completed_at);

        // ReservationLog audit trail written
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'checked_in',
            'new_status'     => 'completed',
        ]);
    }

    // ─── [3] Walk-in → reservation_id null ──────────────────────────────────

    public function test_walk_in_check_in_has_null_reservation_id(): void
    {
        $vehicle = Vehicle::factory()->create();
        [$lot]   = $this->setupLot();

        // No reservation exists for this vehicle
        $result = app(CheckInService::class)->checkIn($vehicle->id, $lot->id);

        $this->assertTrue($result['success']);
        $this->assertNull($result['reservation']);

        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'reservation_id' => null,
        ]);
    }

    // ─── [4] Reservation plate ไม่ตรงทะเบียน → ไม่เชื่อม ──────────────────

    public function test_reservation_for_different_vehicle_is_not_linked(): void
    {
        $user        = User::factory()->create(['force_password_reset' => false]);
        $vehicleA    = Vehicle::factory()->create(['user_id' => $user->id]);
        $vehicleB    = Vehicle::factory()->create(['user_id' => $user->id]);
        [$lot]       = $this->setupLot();

        // Reservation belongs to vehicleA, but we check-in vehicleB
        $reservation = $this->confirmedReservation($vehicleA, $lot);

        $result = app(CheckInService::class)->checkIn($vehicleB->id, $lot->id);

        $this->assertTrue($result['success']);
        $this->assertNull($result['reservation']);

        // ParkingLog for vehicleB must not reference vehicleA's reservation
        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicleB->id,
            'reservation_id' => null,
        ]);

        // vehicleA's reservation stays confirmed (untouched)
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    // ─── [5] Reservation หมดอายุ → ไม่เชื่อม ───────────────────────────────

    public function test_expired_reservation_outside_grace_period_is_not_linked(): void
    {
        $user    = User::factory()->create(['force_password_reset' => false]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        [$lot]   = $this->setupLot();

        $gracePeriod = Reservation::gracePeriodMinutes();

        // Reservation started well beyond the grace window
        $reservation = $this->confirmedReservation($vehicle, $lot, [
            'reserve_start' => now()->subMinutes($gracePeriod + 60),
        ]);

        $result = app(CheckInService::class)->checkIn($vehicle->id, $lot->id);

        // Check-in succeeds as walk-in
        $this->assertTrue($result['success']);
        $this->assertNull($result['reservation']);

        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'reservation_id' => null,
        ]);

        // Original reservation remains confirmed (not auto-expired by check-in)
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    // ─── [6] Reservation ถูกยกเลิก → ไม่เชื่อม ─────────────────────────────

    public function test_cancelled_reservation_is_not_linked_on_check_in(): void
    {
        $user    = User::factory()->create(['force_password_reset' => false]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        [$lot]   = $this->setupLot();

        $reservation = Reservation::factory()->create([
            'user_id'        => $vehicle->user_id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now(),
            'status'         => 'cancelled',
        ]);

        $result = app(CheckInService::class)->checkIn($vehicle->id, $lot->id);

        // Check-in succeeds as walk-in
        $this->assertTrue($result['success']);
        $this->assertNull($result['reservation']);

        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'reservation_id' => null,
        ]);

        // Cancelled reservation stays cancelled
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }
}
