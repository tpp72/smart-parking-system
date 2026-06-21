<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCancelReservationTest extends TestCase
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

    private function reservationFor(User $user, string $status, ?ParkingSlot $slot = null): Reservation
    {
        $lot = ParkingLot::factory()->create();

        return Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => Vehicle::factory()->create(['user_id' => $user->id])->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot?->id,
            'reserve_start'   => now()->addHour(),
            'status'          => $status,
        ]);
    }

    private function cancelRoute(Reservation $r): string
    {
        return route('user.reservations.cancel', $r);
    }

    // ─── [1] cancel own pending reservation ─────────────────────────────────

    public function test_user_can_cancel_own_pending_reservation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'pending');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    // ─── [2] cancel own confirmed reservation ───────────────────────────────

    public function test_user_can_cancel_own_confirmed_reservation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'confirmed');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    // ─── [3] cannot cancel another user's reservation ───────────────────────

    public function test_user_cannot_cancel_another_users_reservation(): void
    {
        $owner  = $this->user();
        $other  = $this->user();
        $reservation = $this->reservationFor($owner, 'pending');

        $this->actingAs($other)
            ->post($this->cancelRoute($reservation))
            ->assertForbidden();

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'pending',
        ]);
    }

    // ─── [4] cannot cancel checked_in reservation ───────────────────────────

    public function test_user_cannot_cancel_checked_in_reservation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'checked_in');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'checked_in',
        ]);
    }

    // ─── [5] cannot cancel completed reservation ────────────────────────────

    public function test_user_cannot_cancel_completed_reservation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'completed');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'completed',
        ]);
    }

    // ─── [6] cannot cancel already-cancelled reservation ────────────────────

    public function test_user_cannot_cancel_already_cancelled_reservation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'cancelled');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'cancelled',
        ]);
    }

    // ─── [7] parking slot released after cancellation ───────────────────────

    public function test_reserved_slot_is_released_after_cancellation(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create([
            'parking_lot_id' => $lot->id,
            'status'         => 'reserved',
        ]);

        $reservation = Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => Vehicle::factory()->create(['user_id' => $user->id])->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHour(),
            'status'          => 'confirmed',
        ]);

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation));

        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'available',
        ]);
    }

    // ─── [8] reservation log created ────────────────────────────────────────

    public function test_reservation_log_is_created_on_cancellation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'pending');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation));

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'pending',
            'new_status'     => 'cancelled',
            'changed_by'     => $user->id,
        ]);
    }

    // ─── [9] notification created ───────────────────────────────────────────

    public function test_notification_is_created_for_user_on_cancellation(): void
    {
        $user        = $this->user();
        $reservation = $this->reservationFor($user, 'confirmed');

        $this->actingAs($user)
            ->post($this->cancelRoute($reservation));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);

        $notification = Notification::where('user_id', $user->id)->latest()->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString((string) $reservation->id, $notification->message);
    }
}
