<?php

namespace Tests\Feature;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Manual Check-out (Fallback) — Checkout Flow เดียวกับ Auto Check-out · สูตรค่าจอด · สิทธิ์ */
class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    /** Walk-in ที่กำลังจอดอยู่มา $minutes นาที (ค่าเริ่มต้นเป็นลานของ Admin อัตรา 30 บาท/ชม.) */
    private function parked(int $minutes = 120, array $lotAttrs = []): Reservation
    {
        $lot  = ParkingLot::factory()->create(array_merge(['hourly_rate' => 30, 'owner_id' => null], $lotAttrs));
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = Reservation::factory()->walkIn()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
        ]);

        ParkingLog::factory()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => $reservation->id,
            'license_plate'   => $reservation->license_plate,
            'plate_province'  => $reservation->plate_province,
            'check_in_time'   => now()->subMinutes($minutes),
            'check_out_time'  => null,
        ]);

        return $reservation;
    }

    private function checkOutAsAdmin(Reservation $reservation): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.reservations.check-out', $reservation));
    }

    private function paymentOf(Reservation $reservation): Payment
    {
        return Payment::where('reservation_id', $reservation->id)->where('type', Payment::TYPE_CHECKOUT)->firstOrFail();
    }

    // ─── [1] สำเร็จ: completed · ปิด Parking Log · Payment · คืน Slot · Log · Audit ─

    public function test_manual_check_out_completes_the_flow(): void
    {
        $reservation = $this->parked(120);
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->post(route('admin.reservations.check-out', $reservation))
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame('completed', $reservation->status);
        $this->assertNotNull($reservation->completed_at);
        $this->assertNotNull($reservation->parkingLog->check_out_time);

        $payment = $this->paymentOf($reservation);
        $this->assertSame(Payment::STATUS_UNPAID, $payment->payment_status);
        $this->assertEquals(2, (float) $payment->total_hours);
        $this->assertEquals(60, (float) $payment->total_amount);
        $this->assertSame($reservation->parkingLog->id, $payment->parking_log_id);

        $this->assertDatabaseHas('parking_slots', ['id' => $reservation->parking_slot_id, 'status' => 'available']);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'checked_in',
            'new_status'     => 'completed',
            'changed_by'     => $admin->id,
        ]);
        $this->assertDatabaseHas('admin_actions', ['action' => 'reservation.check_out', 'actor_id' => $admin->id]);
    }

    // ─── [2] ห้าม Check-out ซ้ำ ─────────────────────────────────────────────

    public function test_check_out_cannot_happen_twice(): void
    {
        $reservation = $this->parked();

        $this->checkOutAsAdmin($reservation)->assertSessionHas('success');
        $this->checkOutAsAdmin($reservation)->assertSessionHasErrors('error');

        $this->assertDatabaseCount('payments', 1);
    }

    // ─── [3] เฉพาะการจองที่ checked_in ──────────────────────────────────────

    public function test_only_checked_in_reservations_can_be_checked_out(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'parking_lot_id' => ParkingLot::factory()->create(['owner_id' => null])->id,
        ]);

        $this->checkOutAsAdmin($reservation)->assertSessionHasErrors('error');

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    // ─── [4] ค่าจอดปัดขึ้นรายชั่วโมง ขั้นต่ำ 1 ชม. ─────────────────────────────

    public function test_fee_is_rounded_up_per_hour_with_one_hour_minimum(): void
    {
        foreach ([20 => [1, 30], 60 => [1, 30], 61 => [2, 60], 26 * 60 => [26, 780]] as $minutes => [$hours, $fee]) {
            $reservation = $this->parked($minutes);

            $this->checkOutAsAdmin($reservation)->assertSessionHas('success');

            $payment = $this->paymentOf($reservation);
            $this->assertEquals($hours, (float) $payment->total_hours, "{$minutes} นาที");
            $this->assertEquals($fee, (float) $payment->parking_fee, "{$minutes} นาที");
            $this->assertEquals($fee, (float) $payment->total_amount, "{$minutes} นาที");
        }
    }

    // ─── [5] ใช้อัตราค่าจอด ณ ตอน Check-in ──────────────────────────────────

    public function test_fee_uses_hourly_rate_at_check_in(): void
    {
        $reservation = $this->parked(120);
        $reservation->parkingLot->update(['hourly_rate' => 100]); // แก้อัตราระหว่างรถจอดอยู่

        $this->checkOutAsAdmin($reservation)->assertSessionHas('success');

        $payment = $this->paymentOf($reservation);
        $this->assertEquals(30, (float) $payment->hourly_rate);
        $this->assertEquals(60, (float) $payment->parking_fee);
    }

    // ─── [6] สิทธิ์ Manual Check-out: Owner ของลาน / Admin เฉพาะลานของ Admin ──

    public function test_manual_check_out_permissions(): void
    {
        $owner = $this->makeUser('owner');
        $ownLot = $this->parked(60, ['owner_id' => $owner->id]);
        $otherOwnersLot = $this->parked(60, ['owner_id' => $this->makeUser('owner')->id]);

        $this->actingAs($owner)->post(route('owner.reservations.check-out', $otherOwnersLot))->assertForbidden();
        $this->actingAs($this->makeUser('admin'))->post(route('admin.reservations.check-out', $ownLot))->assertForbidden();

        $this->actingAs($owner)->post(route('owner.reservations.check-out', $ownLot))->assertSessionHas('success');

        $this->assertSame('completed', $ownLot->fresh()->status);
        $this->assertSame('checked_in', $otherOwnersLot->fresh()->status);
    }

    // ─── [7] เส้นทาง Check-out แบบเดิม (ผ่าน Parking Log) ถูกลบแล้ว ─────────

    public function test_legacy_parking_log_check_out_routes_are_removed(): void
    {
        $this->assertFalse(Route::has('admin.parking-logs.check-out'));
        $this->assertFalse(Route::has('owner.parking-logs.check-out'));
    }
}
