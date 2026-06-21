<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationDepositTest extends TestCase
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
            'vehicle_id'      => $reservation->vehicle_id,
            'parking_lot_id'  => $reservation->parking_lot_id,
            'parking_slot_id' => null,
            'reservation_id'  => $reservation->id,
            'check_in_time'   => now()->subHours(3),
            'check_out_time'  => null,
        ], $attrs));
    }

    // ─── [1] store() sets reservation_fee to lot's hourly_rate ───────────

    public function test_user_reservation_fee_equals_lot_hourly_rate(): void
    {
        $user    = $this->regularUser();
        $vehicle = Vehicle::factory()->create(['user_id' => $user->id]);
        $lot     = ParkingLot::factory()->create(['hourly_rate' => 50.00]);

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'vehicle_id'     => $vehicle->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addMinutes(30)->format('Y-m-d H:i'),
        ]);

        $this->assertDatabaseHas('reservations', [
            'vehicle_id'      => $vehicle->id,
            'parking_lot_id'  => $lot->id,
            'reservation_fee' => 50.00,
        ]);
    }

    // ─── [2] checkout applies deposit as reservation_discount ────────────

    public function test_checkout_applies_deposit_discount(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 40.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $user  = $this->regularUser();
        $veh   = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $veh->id,
            'parking_lot_id' => $lot->id,
            'status'         => 'checked_in',
            'reservation_fee' => 40.00,
        ]);

        $log = $this->makeLog($reservation, [
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subHours(2),
        ]);

        $this->actingAs($admin)->post(route('admin.check-out.store', $log));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        // 2 hrs * 40 = 80 parking fee; deposit = 40; total = 40
        $this->assertEquals(80.00, (float) $payment->parking_fee);
        $this->assertEquals(40.00, (float) $payment->reservation_discount);
        $this->assertEquals(40.00, (float) $payment->total_amount);
        $this->assertEquals('unpaid', $payment->payment_status);
        $this->assertEquals($reservation->id, $payment->reservation_id);
    }

    // ─── [3] no linked reservation → no discount ─────────────────────────

    public function test_checkout_without_reservation_has_no_discount(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 30.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $veh   = Vehicle::factory()->create();

        $log = ParkingLog::factory()->create([
            'vehicle_id'      => $veh->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => null,
            'check_in_time'   => now()->subHours(2),
            'check_out_time'  => null,
        ]);

        $this->actingAs($admin)->post(route('admin.check-out.store', $log));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        $this->assertEquals(0.00, (float) $payment->reservation_discount);
        $this->assertEquals((float) $payment->parking_fee, (float) $payment->total_amount);
        $this->assertNull($payment->reservation_id);
    }

    // ─── [4] deposit cannot exceed parking_fee (clamp to 0) ──────────────

    public function test_checkout_deposit_clamped_to_parking_fee(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 100.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $user  = $this->regularUser();
        $veh   = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $veh->id,
            'parking_lot_id' => $lot->id,
            'status'         => 'checked_in',
            'reservation_fee' => 100.00,
        ]);

        // Only 20 minutes parked → ceil → 1 hour → parkingFee = 100
        $log = $this->makeLog($reservation, [
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(20),
        ]);

        $this->actingAs($admin)->post(route('admin.check-out.store', $log));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertNotNull($payment);

        // parkingFee = 100, deposit = 100 → total = 0, auto-paid
        $this->assertEquals(100.00, (float) $payment->parking_fee);
        $this->assertEquals(100.00, (float) $payment->reservation_discount);
        $this->assertEquals(0.00, (float) $payment->total_amount);
        $this->assertEquals('paid', $payment->payment_status);
    }

    // ─── [5] total_amount=0 auto-marks payment as paid ───────────────────

    public function test_checkout_auto_paid_when_deposit_covers_full_fee(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 60.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $user  = $this->regularUser();
        $veh   = Vehicle::factory()->create(['user_id' => $user->id]);

        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $veh->id,
            'parking_lot_id' => $lot->id,
            'status'         => 'checked_in',
            'reservation_fee' => 60.00,
        ]);

        $log = $this->makeLog($reservation, [
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(45), // ceil → 1 hr
        ]);

        $this->actingAs($admin)->post(route('admin.check-out.store', $log));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertEquals('paid', $payment->payment_status);
        $this->assertEquals(0.00, (float) $payment->total_amount);
    }

    // ─── [6] reservation_fee > parking_fee: discount capped, no negative total

    public function test_checkout_total_amount_never_negative(): void
    {
        $admin = $this->admin();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 30.00]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $user  = $this->regularUser();
        $veh   = Vehicle::factory()->create(['user_id' => $user->id]);

        // Reservation with inflated fee (edge case from data correction)
        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'vehicle_id'     => $veh->id,
            'parking_lot_id' => $lot->id,
            'status'         => 'checked_in',
            'reservation_fee' => 999.00, // more than any parking fee here
        ]);

        $log = $this->makeLog($reservation, [
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'check_in_time'   => now()->subMinutes(30), // 1 hr min → fee = 30
        ]);

        $this->actingAs($admin)->post(route('admin.check-out.store', $log));

        $payment = Payment::where('parking_log_id', $log->id)->first();
        $this->assertGreaterThanOrEqual(0, (float) $payment->total_amount);
        $this->assertEquals(30.00, (float) $payment->reservation_discount); // capped at parkingFee
        $this->assertEquals(0.00, (float) $payment->total_amount);
    }
}
