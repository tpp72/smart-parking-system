<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * reservation_fee = ส่วนลด (= hourly_rate) — แยกจาก Deposit
 * (test ชุดนี้ไม่มี Deposit Payment ที่ชำระแล้ว จึงหักเฉพาะส่วนลด — การหัก Deposit อยู่ใน CheckoutPaymentTest)
 */
class CheckoutReservationFeeTest extends TestCase
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

    private function regularUser(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function makeLog(Reservation $reservation, array $attrs = []): ParkingLog
    {
        return ParkingLog::factory()->create(array_merge([
            'license_plate'   => $reservation->license_plate,
            'plate_province'  => $reservation->plate_province,
            'parking_lot_id'  => $reservation->parking_lot_id,
            'parking_slot_id' => null,
            'reservation_id'  => $reservation->id,
            'check_in_time'   => now()->subHours(3),
            'check_out_time'  => null,
        ], $attrs));
    }

    private function checkedInReservation(ParkingLot $lot, float $fee): Reservation
    {
        return Reservation::factory()->create([
            'user_id'         => $this->regularUser()->id,
            'parking_lot_id'  => $lot->id,
            'status'          => 'checked_in',
            'deposit_amount'  => 0,
            'reservation_fee' => $fee,
        ]);
    }

    // ─── [1] store() sets reservation_fee to lot's hourly_rate ───────────

    public function test_user_reservation_fee_equals_lot_hourly_rate(): void
    {
        $user = $this->regularUser();
        $lot  = ParkingLot::factory()->create(['hourly_rate' => 50.00]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => 'กข 5678',
            'plate_province' => 'เชียงใหม่',
            'brand'          => 'Honda',
            'color'          => 'ดำ',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addMinutes(30)->format('Y-m-d H:i'),
        ]);

        $this->assertDatabaseHas('reservations', [
            'license_plate'   => 'กข 5678',
            'plate_province'  => 'เชียงใหม่',
            'parking_lot_id'  => $lot->id,
            'reservation_fee' => 50.00,
        ]);
    }

    // ─── [2] checkout applies reservation_fee as reservation_discount ────

    public function test_checkout_applies_reservation_fee_discount(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 40.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = $this->checkedInReservation($lot, 40.00);

        $log = $this->makeLog($reservation, [
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHours(2),
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.check-out', $reservation));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        // 2 hrs * 40 = 80 parking fee; discount = 40; total = 40
        $this->assertEquals(Payment::TYPE_CHECKOUT, $payment->type);
        $this->assertEquals(80.00, (float) $payment->parking_fee);
        $this->assertEquals(40.00, (float) $payment->reservation_discount);
        $this->assertEquals(40.00, (float) $payment->total_amount);
        $this->assertEquals('unpaid', $payment->payment_status);
        $this->assertEquals($reservation->id, $payment->reservation_id);
    }

    // ─── [3] Walk-in → ไม่มีส่วนลด ───────────────────────────────────────

    public function test_checkout_of_walk_in_has_no_discount(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 30.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $walkIn = Reservation::factory()->walkIn()->create(['parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id]);

        $log = $this->makeLog($walkIn, [
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHours(2),
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.check-out', $walkIn));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        $this->assertEquals(0.00, (float) $payment->reservation_discount);
        $this->assertEquals((float) $payment->parking_fee, (float) $payment->total_amount);
        $this->assertSame($walkIn->id, $payment->reservation_id);
    }

    // ─── [4] discount cannot exceed parking_fee (clamp to 0) ─────────────

    public function test_checkout_discount_clamped_to_parking_fee(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 100.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = $this->checkedInReservation($lot, 100.00);

        // Only 20 minutes parked → ceil → 1 hour → parkingFee = 100
        $log = $this->makeLog($reservation, [
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(20),
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.check-out', $reservation));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        // parkingFee = 100, discount = 100 → total = 0, auto-paid
        $this->assertEquals(100.00, (float) $payment->parking_fee);
        $this->assertEquals(100.00, (float) $payment->reservation_discount);
        $this->assertEquals(0.00, (float) $payment->total_amount);
        $this->assertEquals('paid', $payment->payment_status);
    }

    // ─── [5] total_amount=0 auto-marks payment as paid (with paid_at) ────

    public function test_checkout_auto_paid_when_discount_covers_full_fee(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 60.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = $this->checkedInReservation($lot, 60.00);

        $log = $this->makeLog($reservation, [
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(45), // ceil → 1 hr
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.check-out', $reservation));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertEquals('paid', $payment->payment_status);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals(0.00, (float) $payment->total_amount);
    }

    // ─── [6] reservation_fee > parking_fee: discount capped, no negative total

    public function test_checkout_total_amount_never_negative(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 30.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = $this->checkedInReservation($lot, 999.00);

        $log = $this->makeLog($reservation, [
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(30), // 1 hr min → fee = 30
        ]);

        $this->actingAs($admin)->post(route('admin.reservations.check-out', $reservation));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertGreaterThanOrEqual(0, (float) $payment->total_amount);
        $this->assertEquals(30.00, (float) $payment->reservation_discount); // capped at parkingFee
        $this->assertEquals(0.00, (float) $payment->total_amount);
    }
}
