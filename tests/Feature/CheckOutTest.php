<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckOutTest extends TestCase
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

    private function makeActiveLog(array $attrs = []): ParkingLog
    {
        $lot     = ParkingLot::factory()->create(['hourly_rate' => 30.00]);
        $slot    = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $vehicle = Vehicle::factory()->create();

        return ParkingLog::factory()->create(array_merge([
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHours(2),
            'check_out_time'  => null,
        ], $attrs));
    }

    private function postCheckOut(User $admin, ParkingLog $log): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)
            ->post(route('admin.check-out.store', $log));
    }

    // ─── [1] สำเร็จ ─────────────────────────────────────────────────────────

    public function test_check_out_success(): void
    {
        $admin = $this->admin();
        $log   = $this->makeActiveLog();

        $response = $this->postCheckOut($admin, $log);

        $response->assertRedirect(route('admin.check-out.index'));
        $response->assertSessionHas('success');

        // check_out_time ถูก set
        $this->assertNotNull($log->fresh()->check_out_time);

        // payment ถูกสร้าง
        $this->assertDatabaseHas('payments', [
            'parking_log_id' => $log->id,
            'payment_status' => 'unpaid',
        ]);

        // slot คืนเป็น available
        $this->assertDatabaseHas('parking_slots', [
            'id'     => $log->parking_slot_id,
            'status' => 'available',
        ]);
    }

    // ─── [2] ห้าม check-out ซ้ำ ─────────────────────────────────────────────

    public function test_check_out_blocked_when_already_checked_out(): void
    {
        $admin = $this->admin();
        $log   = $this->makeActiveLog(['check_out_time' => now()->subMinutes(30)]);

        $response = $this->postCheckOut($admin, $log);

        $response->assertRedirect(route('admin.check-out.index'));
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseCount('payments', 0);
    }

    // ─── [3] ห้าม check-out เมื่อมี payment อยู่แล้ว (double-submit) ────────

    public function test_check_out_blocked_when_payment_already_exists(): void
    {
        $admin = $this->admin();
        $log   = $this->makeActiveLog();

        // สมมติมี payment อยู่แล้ว (edge case จาก double-submit)
        Payment::create([
            'parking_log_id'       => $log->id,
            'total_hours'          => 1,
            'hourly_rate'          => 30.00,
            'parking_fee'          => 30.00,
            'reservation_discount' => 0,
            'total_amount'         => 30.00,
            'payment_status'       => 'unpaid',
        ]);

        $response = $this->postCheckOut($admin, $log);

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseCount('payments', 1); // ยังมีแค่ 1 รายการ
    }

    // ─── [4] check-out log ที่ไม่มีอยู่จริง → 404 ───────────────────────────

    public function test_check_out_nonexistent_log_returns_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.check-out.store', 99999))
            ->assertNotFound();
    }

    // ─── [5] คำนวณค่าจอดเกิน 24 ชั่วโมง ────────────────────────────────────

    public function test_check_out_calculates_fee_correctly_over_24_hours(): void
    {
        $admin = $this->admin();
        $log   = $this->makeActiveLog([
            'check_in_time' => now()->subHours(26), // จอด 26 ชั่วโมง
        ]);

        $this->postCheckOut($admin, $log);

        $payment = Payment::where('parking_log_id', $log->id)->first();

        // 26 ชม. → ceil(26*60/60) = 26, rate = 30 → fee = 780
        $this->assertNotNull($payment);
        $this->assertEquals(26, $payment->total_hours);
        $this->assertEquals(780.00, (float) $payment->total_amount);
    }

    // ─── [6] คำนวณค่าจอด < 1 ชั่วโมง → ขั้นต่ำ 1 ชม. ──────────────────────

    public function test_check_out_minimum_one_hour_fee(): void
    {
        $admin = $this->admin();
        $log   = $this->makeActiveLog([
            'check_in_time' => now()->subMinutes(20), // จอดแค่ 20 นาที
        ]);

        $this->postCheckOut($admin, $log);

        $payment = Payment::where('parking_log_id', $log->id)->first();

        // ขั้นต่ำ 1 ชม. → fee = 30.00
        $this->assertEquals(1, $payment->total_hours);
        $this->assertEquals(30.00, (float) $payment->total_amount);
    }
}
