<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotReservationsEnabledTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    // ─── [1] lot with reservations_enabled=true appears in create form ────

    public function test_reservable_lot_appears_in_create_form(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create(['reservations_enabled' => true]);

        $response = $this->actingAs($user)->get(route('user.reservations.create'));

        $response->assertStatus(200);
        $lots = $response->viewData('lots');
        $this->assertTrue($lots->contains('id', $lot->id));
    }

    // ─── [2] lot with reservations_enabled=false hidden from create form ──

    public function test_non_reservable_lot_hidden_from_create_form(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create(['reservations_enabled' => false]);

        $response = $this->actingAs($user)->get(route('user.reservations.create'));

        $lots = $response->viewData('lots');
        $this->assertFalse($lots->contains('id', $lot->id));
    }

    // ─── [3] server-side: cannot book a non-reservable lot ───────────────

    public function test_store_blocked_for_non_reservable_lot(): void
    {
        $user    = $this->user();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $lot     = ParkingLot::factory()->create(['reservations_enabled' => false]);

        $response = $this->actingAs($user)->post(route('user.reservations.store'), [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addMinutes(30)->format('Y-m-d H:i'),
        ]);

        $response->assertSessionHasErrors('parking_lot_id');
        $this->assertDatabaseCount('reservations', 0);
    }

    // ─── [4] admin can toggle reservations_enabled via lot update ─────────

    public function test_admin_can_disable_reservations_for_lot(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['reservations_enabled' => true]);

        $this->actingAs($admin)->put(route('admin.parking-lots.update', $lot), [
            'name'                 => $lot->name,
            'total_slots'          => $lot->total_slots,
            'hourly_rate'          => $lot->hourly_rate,
            'is_active'            => '1',
            'reservations_enabled' => '0',  // hidden input sends "0"
        ]);

        $this->assertFalse((bool) $lot->fresh()->reservations_enabled);
    }

    // ─── [5] admin can re-enable reservations ────────────────────────────

    public function test_admin_can_enable_reservations_for_lot(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['reservations_enabled' => false]);

        $this->actingAs($admin)->put(route('admin.parking-lots.update', $lot), [
            'name'                 => $lot->name,
            'total_slots'          => $lot->total_slots,
            'hourly_rate'          => $lot->hourly_rate,
            'is_active'            => '1',
            'reservations_enabled' => '1',
        ]);

        $this->assertTrue((bool) $lot->fresh()->reservations_enabled);
    }

    // ─── [6] new lots default to reservations_enabled=true ───────────────

    public function test_new_lot_defaults_to_reservations_enabled(): void
    {
        $this->assertDatabaseCount('parking_lots', 0);

        $lot = ParkingLot::factory()->create();

        $this->assertTrue((bool) $lot->reservations_enabled);
    }
}
