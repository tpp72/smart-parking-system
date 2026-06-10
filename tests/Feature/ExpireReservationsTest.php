<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireReservationsTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function user(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function pastReservation(array $state = []): Reservation
    {
        $user    = $this->user();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $lot     = ParkingLot::factory()->create();

        return Reservation::factory()->create(array_merge([
            'user_id'       => $user->id,
            'vehicle_id'    => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start' => now()->subHours(2),
            'status'        => 'confirmed',
        ], $state));
    }

    // ─── [1] expired ────────────────────────────────────────────────────────

    public function test_expired_reservations_are_marked_expired(): void
    {
        $r = $this->pastReservation();

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id'     => $r->id,
            'status' => 'expired',
        ]);
    }

    // ─── [2] grace period respected ─────────────────────────────────────────

    public function test_reservations_within_grace_period_are_not_expired(): void
    {
        // reserve_start 10 min ago — inside default 30-min grace
        $r = $this->pastReservation([
            'reserve_start' => now()->subMinutes(10),
        ]);

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id'     => $r->id,
            'status' => 'confirmed',  // unchanged
        ]);
    }

    // ─── [3] slot released when reserved ────────────────────────────────────

    public function test_reserved_slot_is_released_to_available_on_expiry(): void
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create([
            'parking_lot_id' => $lot->id,
            'status'         => 'reserved',
        ]);

        $user    = $this->user();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->subHours(2),
            'status'          => 'confirmed',
        ]);

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'available',
        ]);
    }

    // ─── [4] occupied slots are NOT released ────────────────────────────────

    public function test_occupied_slot_is_not_released_on_expiry(): void
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $lot->id]);

        $user    = $this->user();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->subHours(2),
            'status'          => 'confirmed',
        ]);

        $this->artisan('reservations:expire')->assertExitCode(0);

        // Still occupied — another vehicle may be in the slot
        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'occupied',
        ]);
    }

    // ─── [5] already expired/cancelled not touched ──────────────────────────

    public function test_already_expired_reservations_are_not_reprocessed(): void
    {
        $r = $this->pastReservation(['status' => 'expired']);

        $this->artisan('reservations:expire')->assertExitCode(0);

        // Only 1 reservation_log row ever (none added by command)
        $this->assertDatabaseCount('reservation_logs', 0);
    }

    // ─── [6] dry-run does not modify data ───────────────────────────────────

    public function test_dry_run_does_not_change_reservation_status(): void
    {
        $r = $this->pastReservation();

        $this->artisan('reservations:expire --dry-run')->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id'     => $r->id,
            'status' => 'confirmed',
        ]);
    }

    // ─── [7] reservation_logs created ───────────────────────────────────────

    public function test_reservation_log_is_written_on_expiry(): void
    {
        $r = $this->pastReservation();

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $r->id,
            'old_status'     => 'confirmed',
            'new_status'     => 'expired',
        ]);
    }

    // ─── [8] notification sent to user ──────────────────────────────────────

    public function test_notification_is_sent_to_reservation_owner_on_expiry(): void
    {
        $r = $this->pastReservation();

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $r->user_id,
        ]);

        $notification = Notification::where('user_id', $r->user_id)->first();
        $this->assertStringContainsString("#{$r->id}", $notification->message);
    }

    // ─── [9] pending reservations also expire ───────────────────────────────

    public function test_pending_reservations_also_expire(): void
    {
        $r = $this->pastReservation(['status' => 'pending']);

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('reservations', [
            'id'     => $r->id,
            'status' => 'expired',
        ]);
    }

    // ─── [10] no reservations → 0 exit, no error ────────────────────────────

    public function test_command_succeeds_when_no_reservations_to_expire(): void
    {
        $this->artisan('reservations:expire')->assertExitCode(0);
    }
}
