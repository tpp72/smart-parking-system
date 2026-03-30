<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Vehicle;
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

    private function postCheckIn(User $admin, array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)
            ->post(route('admin.check-in.store'), $payload);
    }

    // ─── [1] สำเร็จ ─────────────────────────────────────────────────────────

    public function test_check_in_success(): void
    {
        $admin   = $this->admin();
        $lot     = ParkingLot::factory()->create();
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);
        $vehicle = Vehicle::factory()->create();

        $response = $this->postCheckIn($admin, [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
        ]);

        $response->assertRedirect(route('admin.check-in.create'));
        $response->assertSessionHas('success');

        // log ถูกสร้าง
        $this->assertDatabaseHas('parking_logs', [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'check_out_time' => null,
        ]);

        // slot เปลี่ยนเป็น occupied
        $this->assertDatabaseHas('parking_slots', [
            'id'     => $slot->id,
            'status' => 'occupied',
        ]);
    }

    // ─── [2] ห้าม check-in ซ้ำ (รถยังอยู่ในลาน) ────────────────────────────

    public function test_check_in_blocked_when_vehicle_already_parked(): void
    {
        $admin   = $this->admin();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // รถมี active log อยู่แล้ว
        ParkingLog::factory()->create([
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'check_out_time' => null,
        ]);

        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $response = $this->postCheckIn($admin, [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
        ]);

        $response->assertSessionHasErrors('vehicle_id');
        // ต้องไม่มี log เพิ่มขึ้น (ยังมีแค่ 1 รายการ)
        $this->assertDatabaseCount('parking_logs', 1);
    }

    // ─── [3] ห้าม check-in เมื่อ slot เต็ม ─────────────────────────────────

    public function test_check_in_blocked_when_no_available_slot(): void
    {
        $admin   = $this->admin();
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        // slot ทุกช่องเต็ม
        ParkingSlot::factory()->count(3)->create([
            'parking_lot_id' => $lot->id,
            'status'         => 'occupied',
        ]);

        $response = $this->postCheckIn($admin, [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
        ]);

        $response->assertSessionHasErrors('parking_lot_id');
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [4] ห้าม check-in โดยไม่เลือก lot ─────────────────────────────────

    public function test_check_in_requires_parking_lot(): void
    {
        $admin   = $this->admin();
        $vehicle = Vehicle::factory()->create();

        $response = $this->postCheckIn($admin, [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => '',
        ]);

        $response->assertSessionHasErrors('parking_lot_id');
    }

    // ─── [5] guest ถูก redirect ──────────────────────────────────────────────

    public function test_guest_cannot_check_in(): void
    {
        $lot     = ParkingLot::factory()->create();
        $vehicle = Vehicle::factory()->create();

        $this->post(route('admin.check-in.store'), [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
        ])->assertRedirect(route('login'));
    }
}
