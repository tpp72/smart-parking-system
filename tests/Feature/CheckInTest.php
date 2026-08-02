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

    /** การจองแบบพิมพ์ทะเบียนเอง (ไม่มี Vehicle) พร้อมเช็คอินทันที */
    private function checkableReservation(array $overrides = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'vehicle_id'     => null,
            'status'         => 'confirmed',
            'reserve_start'  => now(),
            'license_plate'  => 'กก 1234 กรุงเทพมหานคร',
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

        // log ถูกสร้างพร้อมทะเบียน/ยี่ห้อ/สี แม้ไม่มี Vehicle record
        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => null,
            'license_plate'  => $reservation->license_plate,
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
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

    // ─── [2] ห้าม check-in ซ้ำ (ทะเบียนนี้ยังอยู่ในลาน) ────────────────────

    public function test_check_in_blocked_when_plate_already_parked(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create();
        $plate = 'กก 1234 กรุงเทพมหานคร';

        // ทะเบียนนี้มี active log อยู่แล้ว
        ParkingLog::factory()->create([
            'vehicle_id'     => null,
            'license_plate'  => $plate,
            'parking_lot_id' => $lot->id,
            'check_out_time' => null,
        ]);

        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = $this->checkableReservation([
            'parking_lot_id' => $lot->id,
            'license_plate'  => $plate,
        ]);

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
}
