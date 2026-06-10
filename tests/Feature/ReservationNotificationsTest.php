<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationNotificationsTest extends TestCase
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

    private function pendingReservation(User $user): Reservation
    {
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $lot     = ParkingLot::factory()->create();

        return Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHour(),
            'status'         => 'pending',
        ]);
    }

    // ─── [1] confirm sends notification to user ─────────────────────────────

    public function test_confirm_reservation_sends_notification_to_user(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        $reservation = $this->pendingReservation($user);

        $this->actingAs($admin)
            ->post(route('admin.reservations.confirm', $reservation))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
    }

    // ─── [2] cancel via update sends notification ───────────────────────────

    public function test_cancel_via_update_sends_notification_to_user(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        $reservation = $this->pendingReservation($user);

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), [
                'parking_lot_id'  => $reservation->parking_lot_id,
                'parking_slot_id' => null,
                'reserve_start'   => now()->addHour()->format('Y-m-d H:i:s'),
                'reservation_fee' => 0,
                'status'          => 'cancelled',
            ])
            ->assertRedirect();

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification, 'Cancellation notification was not sent');
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
    }

    // ─── [3] update to non-cancelled status does NOT send notification ───────

    public function test_update_to_confirmed_does_not_send_cancel_notification(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        $reservation = $this->pendingReservation($user);

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $reservation), [
                'parking_lot_id'  => $reservation->parking_lot_id,
                'parking_slot_id' => null,
                'reserve_start'   => now()->addHour()->format('Y-m-d H:i:s'),
                'reservation_fee' => 0,
                'status'          => 'confirmed',
            ]);

        // No "cancelled" notification; confirm() route sends a different one
        $cancelNotif = Notification::where('user_id', $user->id)
            ->where('title', 'การจองถูกยกเลิก')
            ->first();
        $this->assertNull($cancelNotif);
    }

    // ─── [4] check-in sends notification ────────────────────────────────────

    public function test_check_in_sends_notification_when_reservation_exists(): void
    {
        $admin   = $this->admin();
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        // Reservation within check-in window
        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subMinutes(5),
            'status'         => 'confirmed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.check-in.store'), [
                'vehicle_id'     => $vehicle->id,
                'parking_lot_id' => $lot->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
    }

    // ─── [5] check-out sends notification ───────────────────────────────────

    public function test_check_out_sends_notification_to_vehicle_owner(): void
    {
        $admin   = $this->admin();
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create(['hourly_rate' => 40]);
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $log = ParkingLog::factory()->create([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHours(2),
            'check_out_time'  => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.check-out.store', $log))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertStringContainsString($vehicle->license_plate, $notification->message);
    }

    // ─── [6] expiry command sends notification ───────────────────────────────

    public function test_expire_command_sends_notification(): void
    {
        $user    = $this->regularUser();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $lot     = ParkingLot::factory()->create();

        Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subHours(2),
            'status'         => 'confirmed',
        ]);

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
    }
}
