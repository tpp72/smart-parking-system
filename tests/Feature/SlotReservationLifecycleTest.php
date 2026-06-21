<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotReservationLifecycleTest extends TestCase
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

    private function makeReservationWithSlot(User $owner, string $reservationStatus, string $slotStatus): array
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create([
            'parking_lot_id' => $lot->id,
            'status'         => $slotStatus,
        ]);
        $vehicle = Vehicle::factory()->create(['user_id' => $owner->id]);

        $reservation = Reservation::factory()->create([
            'user_id'         => $owner->id,
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHour(),
            'status'          => $reservationStatus,
        ]);

        return compact('lot', 'slot', 'vehicle', 'reservation');
    }

    // ─── [1] confirm: available → reserved ──────────────────────────────────

    public function test_confirm_sets_slot_to_reserved(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        ['reservation' => $reservation, 'slot' => $slot] = $this->makeReservationWithSlot($user, 'pending', 'available');

        $this->actingAs($admin)
            ->post(route('admin.reservations.confirm', $reservation))
            ->assertRedirect();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('reservations',  ['id' => $reservation->id, 'status' => 'confirmed']);
    }

    // ─── [2] user cancel: reserved → available ───────────────────────────────

    public function test_user_cancel_releases_reserved_slot(): void
    {
        $user = $this->regularUser();
        ['reservation' => $reservation, 'slot' => $slot] = $this->makeReservationWithSlot($user, 'confirmed', 'reserved');

        $this->actingAs($user)
            ->post(route('user.reservations.cancel', $reservation))
            ->assertRedirect(route('user.reservations.index'));

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    // ─── [3] admin cancel: reserved → available ──────────────────────────────

    public function test_admin_cancel_releases_reserved_slot(): void
    {
        $admin = $this->admin();
        $user  = $this->regularUser();
        ['reservation' => $reservation, 'slot' => $slot] = $this->makeReservationWithSlot($user, 'confirmed', 'reserved');

        $this->actingAs($admin)
            ->patch(route('admin.reservations.update', $reservation), [
                'parking_lot_id'  => $reservation->parking_lot_id,
                'parking_slot_id' => $reservation->parking_slot_id,
                'reserve_start'   => $reservation->reserve_start->format('Y-m-d H:i:s'),
                'reservation_fee' => $reservation->reservation_fee,
                'status'          => 'cancelled',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
        $this->assertDatabaseHas('reservations',  ['id' => $reservation->id, 'status' => 'cancelled']);
    }

    // ─── [4] expire: reserved → available ───────────────────────────────────

    public function test_expire_releases_reserved_slot(): void
    {
        $user         = $this->regularUser();
        $graceMinutes = (int) config('parking.grace_period', 30);
        $lot          = ParkingLot::factory()->create();
        $slot         = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);
        $vehicle      = Vehicle::factory()->create(['user_id' => $user->id]);

        Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->subMinutes($graceMinutes + 1),
            'status'          => 'confirmed',
        ]);

        $this->artisan('reservations:expire')->assertSuccessful();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    // ─── [5] check-in: reserved → occupied ──────────────────────────────────

    public function test_check_in_sets_reserved_slot_to_occupied(): void
    {
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        Reservation::factory()->create([
            'user_id'         => $user->id,
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now(),
            'status'          => 'confirmed',
        ]);

        $result = app(CheckInService::class)->checkIn($vehicle->id, $lot->id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'occupied']);
    }

    // ─── [6] check-out: occupied → available ────────────────────────────────

    public function test_check_out_sets_occupied_slot_to_available(): void
    {
        $admin   = $this->admin();
        $user    = $this->regularUser();
        $lot     = ParkingLot::factory()->create(['hourly_rate' => 20]);
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $log = ParkingLog::factory()->create([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHour(),
            'check_out_time'  => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.check-out.store', $log))
            ->assertRedirect();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    // ─── [7] confirm blocked when slot is occupied ───────────────────────────

    public function test_confirm_fails_when_slot_is_occupied(): void
    {
        $admin = $this->admin();
        $user  = $this->regularUser();
        ['reservation' => $reservation, 'slot' => $slot] = $this->makeReservationWithSlot($user, 'pending', 'occupied');

        $this->actingAs($admin)
            ->post(route('admin.reservations.confirm', $reservation))
            ->assertRedirect()
            ->assertSessionHasErrors('error');

        $this->assertDatabaseHas('reservations',  ['id' => $reservation->id, 'status' => 'pending']);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id,        'status' => 'occupied']);
    }
}
