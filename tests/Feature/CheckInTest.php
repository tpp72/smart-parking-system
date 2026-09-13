<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ───────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function postCheckIn(User $admin, Reservation $reservation): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)
            ->post(route('admin.reservations.check-in', $reservation));
    }

    /** การจองแบบ Plate-based พร้อมเช็คอินทันที */
    private function checkableReservation(array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'status'         => 'confirmed',
            'reserve_start'  => now(),
            'license_plate'  => 'กก 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
        ], $overrides));
    }

    // ─── [1] สำเร็จ ─────────────────────────────────────────────────────────

    public function test_check_in_success(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation(['parking_lot_id' => $lot->id]);

        $response = $this->postCheckIn($admin, $reservation);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // log ถูกสร้างพร้อม ทะเบียน/จังหวัด/ยี่ห้อ/สี
        $this->assertDatabaseHas('parking_logs', [
            'license_plate'  => 'กก 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reservation_id' => $reservation->id,
            'check_out_time' => null,
        ]);

        // slot เปลี่ยนเป็น occupied
        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'occupied',
        ]);

        // reservation ผูกกับ log และเปลี่ยนสถานะ
        $this->assertDatabaseHas('reservations', [
            'id'     => $reservation->id,
            'status' => 'checked_in',
        ]);
    }

    // ─── [2] ห้าม check-in ซ้ำ (รถคันนี้ยังอยู่ในลาน) ──────────────────────

    public function test_check_in_blocked_when_plate_already_parked(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        // รถคันนี้มี active log อยู่แล้ว
        ParkingLog::factory()->create([
            'license_plate'  => 'กก 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'parking_lot_id' => $lot->id,
            'check_out_time' => null,
        ]);

        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation(['parking_lot_id' => $lot->id]);

        $response = $this->postCheckIn($admin, $reservation);

        $response->assertSessionHasErrors('error');
        // ต้องไม่มี log เพิ่มขึ้น (ยังมีแค่ 1 รายการ)
        $this->assertDatabaseCount('parking_logs', 1);
    }

    // ─── [3] ห้าม check-in เมื่อ slot เต็ม ─────────────────────────────────

    public function test_check_in_blocked_when_no_available_slot(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        // slot ทุกช่องเต็ม
        ParkingSlot::factory()->count(3)->create([
            'parking_lot_id' => $lot->id,
            'status'         => 'occupied',
        ]);

        $reservation = $this->checkableReservation(['parking_lot_id' => $lot->id]);

        $response = $this->postCheckIn($admin, $reservation);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [4] ห้าม check-in ถ้าสถานะไม่ใช่ confirmed ────────────────────────

    public function test_check_in_requires_confirmed_status(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();

        $reservation = $this->checkableReservation([
            'parking_lot_id' => $lot->id,
            'status'         => 'pending',
        ]);

        $response = $this->postCheckIn($admin, $reservation);

        $response->assertSessionHasErrors('error');
    }

    // ─── [5] guest ถูก redirect ──────────────────────────────────────────────

    public function test_guest_cannot_check_in(): void
    {
        $lot = ParkingLot::factory()->create();
        $reservation = $this->checkableReservation(['parking_lot_id' => $lot->id]);

        $this->post(route('admin.reservations.check-in', $reservation))
            ->assertRedirect(route('login'));
    }

    // ─── [6] Manual Check-in รับรถที่มาก่อนเวลาจองได้ ──────────────────────

    public function test_manual_check_in_allows_early_arrival(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation([
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(5),
        ]);

        $this->postCheckIn($admin, $reservation)->assertSessionHas('success');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_in']);
    }

    // ─── [7] Manual Check-in ไม่ได้เมื่อเลยเวลาเช็คอินแล้ว ──────────────────

    public function test_manual_check_in_rejects_reservation_past_grace_period(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation([
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subMinutes(Reservation::gracePeriodMinutes() + 5),
        ]);

        $this->postCheckIn($admin, $reservation)->assertSessionHasErrors('error');

        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [8] Owner เช็คอินการจองของลานอื่นไม่ได้ ───────────────────────────

    public function test_owner_cannot_check_in_reservation_of_another_owners_lot(): void
    {
        $owner = User::factory()->create([
            'role'                 => 'owner',
            'owner_status'         => 'approved',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
        $otherLot = ParkingLot::factory()->create(['owner_id' => User::factory()->create(['role' => 'owner'])->id]);
        ParkingSlot::factory()->create(['parking_lot_id' => $otherLot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation(['parking_lot_id' => $otherLot->id]);

        $this->actingAs($owner)
            ->post(route('owner.reservations.check-in', $reservation))
            ->assertForbidden();

        $this->assertSame('confirmed', $reservation->fresh()->status);
    }
}
