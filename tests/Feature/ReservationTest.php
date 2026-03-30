<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ───────────────────────────────────────────────────────────

    private function user(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function postReservation(User $user, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->post(route('user.reservations.store'), $payload);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'reserve_start' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'reserve_end'   => now()->addHours(4)->format('Y-m-d\TH:i'),
        ], $override);
    }

    // ─── [1] สำเร็จ ─────────────────────────────────────────────────────────

    public function test_reservation_success(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $response = $this->postReservation($user, $this->payload([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
        ]));

        $response->assertRedirect(route('user.reservations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'status'          => 'pending',
        ]);
    }

    // ─── [2] ห้ามจอง slot ชนกัน (time overlap) ──────────────────────────────

    public function test_reservation_blocked_when_slot_time_overlaps(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        // จองแรกอยู่ที่ +2h ถึง +4h
        Reservation::factory()->create([
            'parking_slot_id' => $slot->id,
            'parking_lot_id'  => $lot->id,
            'status'          => 'confirmed',
            'reserve_start'   => now()->addHours(2),
            'reserve_end'     => now()->addHours(4),
        ]);

        // จองที่สอง: +3h ถึง +5h (ทับกัน)
        $response = $this->postReservation($user, $this->payload([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHours(3)->format('Y-m-d\TH:i'),
            'reserve_end'     => now()->addHours(5)->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('parking_slot_id');
        $this->assertDatabaseCount('reservations', 1);
    }

    // ─── [3] อนุญาตจอง slot เดิมได้ถ้าเวลาไม่ทับ ────────────────────────────

    public function test_reservation_allowed_when_slot_times_do_not_overlap(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        // จองแรก +2h ถึง +4h
        Reservation::factory()->create([
            'parking_slot_id' => $slot->id,
            'parking_lot_id'  => $lot->id,
            'status'          => 'confirmed',
            'reserve_start'   => now()->addHours(2),
            'reserve_end'     => now()->addHours(4),
        ]);

        // จองที่สอง: +5h ถึง +7h (ไม่ทับ)
        $response = $this->postReservation($user, $this->payload([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHours(5)->format('Y-m-d\TH:i'),
            'reserve_end'     => now()->addHours(7)->format('Y-m-d\TH:i'),
        ]));

        $response->assertRedirect(route('user.reservations.index'));
        $this->assertDatabaseCount('reservations', 2);
    }

    // ─── [4] slot ของ cancelled ไม่ถือว่าชน ─────────────────────────────────

    public function test_cancelled_reservation_does_not_block_new_booking(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        // จองที่ถูก cancel แล้ว
        Reservation::factory()->create([
            'parking_slot_id' => $slot->id,
            'parking_lot_id'  => $lot->id,
            'status'          => 'cancelled',  // ← cancel แล้ว
            'reserve_start'   => now()->addHours(2),
            'reserve_end'     => now()->addHours(4),
        ]);

        // จองเวลาเดียวกันได้ เพราะของเก่า cancel แล้ว
        $response = $this->postReservation($user, $this->payload([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->addHours(2)->format('Y-m-d\TH:i'),
            'reserve_end'     => now()->addHours(4)->format('Y-m-d\TH:i'),
        ]));

        $response->assertRedirect(route('user.reservations.index'));
        $this->assertDatabaseCount('reservations', 2);
    }

    // ─── [5] ห้ามจองรถของคนอื่น ─────────────────────────────────────────────

    public function test_user_cannot_reserve_other_users_vehicle(): void
    {
        $user        = $this->user();
        $otherUser   = $this->user();
        $lot         = ParkingLot::factory()->create();
        $otherVehicle = Vehicle::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postReservation($user, $this->payload([
            'vehicle_id'     => $otherVehicle->id,
            'parking_lot_id' => $lot->id,
        ]));

        $response->assertForbidden();
        $this->assertDatabaseCount('reservations', 0);
    }

    // ─── [6] ห้ามจองเวลาในอดีต ──────────────────────────────────────────────

    public function test_reservation_blocked_when_start_time_in_past(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $response = $this->postReservation($user, [
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'reserve_start'   => now()->subHour()->format('Y-m-d\TH:i'),
            'reserve_end'     => now()->addHour()->format('Y-m-d\TH:i'),
        ]);

        $response->assertSessionHasErrors('reserve_start');
        $this->assertDatabaseCount('reservations', 0);
    }

    // ─── [7] ห้ามให้ reserve_end < reserve_start ─────────────────────────────

    public function test_reservation_blocked_when_end_before_start(): void
    {
        $user    = $this->user();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);

        $response = $this->postReservation($user, [
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'reserve_start'   => now()->addHours(4)->format('Y-m-d\TH:i'),
            'reserve_end'     => now()->addHours(2)->format('Y-m-d\TH:i'),
        ]);

        $response->assertSessionHasErrors('reserve_end');
        $this->assertDatabaseCount('reservations', 0);
    }
}
