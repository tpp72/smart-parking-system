<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
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

    /** ข้อมูลจองแบบ Plate-based: ทะเบียน + จังหวัด + ยี่ห้อ + สี */
    private function payload(array $override = []): array
    {
        return array_merge([
            'plate_number'   => 'กข 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now()->addHours(2)->format('Y-m-d\TH:i'),
        ], $override);
    }

    // ─── [1] สำเร็จ — User เลือกได้เฉพาะลาน ─────────────────────────────────

    public function test_reservation_success(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $response = $this->postReservation($user, $this->payload(['parking_lot_id' => $lot->id]));

        $response->assertRedirect(route('user.reservations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', [
            'user_id'         => $user->id,
            'license_plate'   => 'กข 1234',
            'plate_province'  => 'กรุงเทพมหานคร',
            'brand'           => 'Toyota',
            'color'           => 'ขาว',
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => null,
            'status'          => 'pending',
        ]);
    }

    // ─── [2] User เลือก Slot เองไม่ได้ ──────────────────────────────────────

    public function test_user_cannot_choose_a_slot(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $form = $this->actingAs($user)->get(route('user.reservations.create'));
        $form->assertViewMissing('slots');
        $form->assertDontSee('name="parking_slot_id"', false);

        $this->postReservation($user, $this->payload([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
        ]))->assertRedirect(route('user.reservations.index'));

        $this->assertNull(Reservation::firstOrFail()->parking_slot_id);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    // ─── [3] 1 Active Reservation ต่อ ทะเบียน + จังหวัด ─────────────────────

    public function test_reservation_blocked_when_same_plate_and_province_is_active(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();

        Reservation::factory()->create([
            'parking_lot_id' => $lot->id,
            'license_plate'  => 'กข 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'status'         => 'confirmed',
        ]);

        $response = $this->postReservation($user, $this->payload(['parking_lot_id' => $lot->id]));

        $response->assertSessionHasErrors('license_plate');
        $this->assertDatabaseCount('reservations', 1);
    }

    // ─── [4] ห้ามจองเวลาในอดีต ──────────────────────────────────────────────

    public function test_reservation_blocked_when_start_time_in_past(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();

        $response = $this->postReservation($user, $this->payload([
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subHour()->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('reserve_start');
        $this->assertDatabaseCount('reservations', 0);
    }

    // ─── [5] ห้ามจองล่วงหน้าเกิน 1 วัน ─────────────────────────────────────

    public function test_reservation_blocked_when_start_time_more_than_one_day_ahead(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create();

        $response = $this->postReservation($user, $this->payload([
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]));

        $response->assertSessionHasErrors('reserve_start');
        $this->assertDatabaseCount('reservations', 0);
    }

    // ─── [6] สร้าง Deposit Payment = hourly_rate × 1 · pending ไม่ถือครอง Slot ─

    public function test_reservation_creates_unpaid_one_hour_deposit_and_holds_no_slot(): void
    {
        $user = $this->user();
        $lot  = ParkingLot::factory()->create(['hourly_rate' => 45]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $this->postReservation($user, $this->payload(['parking_lot_id' => $lot->id]))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHas('success');

        $reservation = Reservation::firstOrFail();

        $this->assertSame('pending', $reservation->status);
        $this->assertEquals(45, (float) $reservation->deposit_amount);
        $this->assertEquals(45, (float) $reservation->reservation_fee);

        $this->assertDatabaseHas('payments', [
            'type'           => 'deposit',
            'reservation_id' => $reservation->id,
            'parking_log_id' => null,
            'total_amount'   => 45,
            'payment_status' => 'unpaid',
        ]);
        $this->assertDatabaseCount('payments', 1);

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    // ─── [7] ลานเต็มไม่แสดงให้จอง และจองไม่ได้ ───────────────────────────────

    public function test_full_lot_is_hidden_and_cannot_be_booked(): void
    {
        $user    = $this->user();
        $fullLot = ParkingLot::factory()->create();
        ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $fullLot->id]);
        ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $fullLot->id]);

        $lots = $this->actingAs($user)->get(route('user.reservations.create'))->viewData('lots');
        $this->assertFalse($lots->contains('id', $fullLot->id));

        $this->postReservation($user, $this->payload(['parking_lot_id' => $fullLot->id]))
            ->assertSessionHasErrors('parking_lot_id');

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('payments', 0);
    }
}
