<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\ReservationService;
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

        $reservation = Reservation::factory()->create([
            'user_id'         => $owner->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHour(),
            'status'          => $reservationStatus,
        ]);

        return compact('lot', 'slot', 'reservation');
    }

    // ─── [1] ยืนยันรับเงินมัดจำ: available → reserved ────────────────────────

    public function test_deposit_mark_paid_sets_slot_to_reserved(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = app(ReservationService::class)->create($this->regularUser(), $lot, [
            'license_plate'  => 'กข 7777',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now()->addHour(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertRedirect();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'reserved']);
        $this->assertDatabaseHas('reservations',  ['id' => $reservation->id, 'status' => 'confirmed', 'parking_slot_id' => $slot->id]);
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
            ->post(route('admin.reservations.cancel', $reservation))
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

        Reservation::factory()->create([
            'user_id'         => $user->id,
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
        $user = $this->regularUser();
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);

        $reservation = Reservation::factory()->create([
            'user_id'         => $user->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now(),
            'status'          => 'confirmed',
        ]);

        $result = app(CheckInService::class)->checkInReservation($reservation);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'occupied']);
    }

    // ─── [6] check-out: occupied → available ────────────────────────────────

    public function test_check_out_sets_occupied_slot_to_available(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 20]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $log = ParkingLog::factory()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHour(),
            'check_out_time'  => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.parking-logs.check-out', $log))
            ->assertRedirect();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }
}
