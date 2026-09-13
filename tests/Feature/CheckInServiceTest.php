<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** CheckInService — เช็คอินการจองที่ confirmed และ Walk-in Reservation ของ Walkin User */
class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE    = 'กข 4321';
    private const PROVINCE = 'ชลบุรี';

    private function service(): CheckInService
    {
        return app(CheckInService::class);
    }

    private function lotWithSlots(int $available, array $attrs = []): ParkingLot
    {
        $lot = ParkingLot::factory()->create($attrs);
        ParkingSlot::factory()->count($available)->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        return $lot;
    }

    /** การจองที่ยืนยันรับเงินมัดจำแล้ว พร้อมช่องที่ระบบ Lock ไว้ */
    private function confirmedBooking(ParkingLot $lot, array $attrs = []): Reservation
    {
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'reserved']);

        return Reservation::factory()->confirmed()->create(array_merge([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'license_plate'   => self::PLATE,
            'plate_province'  => self::PROVINCE,
            'brand'           => 'Toyota',
            'color'           => 'ขาว',
            'reserve_start'   => now()->subMinutes(5),
            'deposit_amount'  => 40,
            'reservation_fee' => 40,
        ], $attrs));
    }

    private function walkIn(ParkingLot $lot): array
    {
        return $this->service()->checkInWalkIn($lot, self::PLATE, self::PROVINCE, 'Toyota', 'ขาว');
    }

    // ─── [1] confirmed → เข้าช่องที่ Lock ไว้ → checked_in ─────────────────

    public function test_confirmed_reservation_checks_in_to_its_locked_slot(): void
    {
        $lot = $this->lotWithSlots(1);
        $booking = $this->confirmedBooking($lot);

        $result = $this->service()->checkInReservation($booking);

        $this->assertTrue($result['success']);
        $this->assertSame($booking->parking_slot_id, $result['slot']->id);
        $this->assertDatabaseHas('parking_slots', ['id' => $booking->parking_slot_id, 'status' => 'occupied']);
        $this->assertDatabaseHas('parking_logs', [
            'reservation_id' => $booking->id,
            'license_plate'  => self::PLATE,
            'plate_province' => self::PROVINCE,
            'check_out_time' => null,
        ]);

        $booking->refresh();
        $this->assertSame('checked_in', $booking->status);
        $this->assertNotNull($booking->checked_in_at);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $booking->id,
            'old_status'     => 'confirmed',
            'new_status'     => 'checked_in',
            'changed_by'     => null,
        ]);
    }

    // ─── [2] Manual Check-in บันทึกเจ้าหน้าที่ผู้ทำรายการ ────────────────────

    public function test_manual_check_in_records_the_staff_member(): void
    {
        $booking = $this->confirmedBooking($this->lotWithSlots(0));
        $owner = User::factory()->create(['role' => 'owner']);

        $this->assertTrue($this->service()->checkInReservation($booking, $owner, allowEarly: true)['success']);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $booking->id,
            'new_status'     => 'checked_in',
            'changed_by'     => $owner->id,
        ]);
    }

    // ─── [3] มาก่อนเวลาจอง: Auto ไม่ได้ · Manual ได้ ────────────────────────

    public function test_early_arrival_is_rejected_for_auto_but_allowed_for_manual_check_in(): void
    {
        $booking = $this->confirmedBooking($this->lotWithSlots(0), ['reserve_start' => now()->addHours(3)]);

        $auto = $this->service()->checkInReservation($booking);
        $this->assertFalse($auto['success']);
        $this->assertSame(CheckInService::OUTCOME_NOT_CHECKABLE, $auto['outcome']);
        $this->assertSame('confirmed', $booking->fresh()->status);

        $manual = $this->service()->checkInReservation($booking, User::factory()->create(['role' => 'admin']), allowEarly: true);
        $this->assertTrue($manual['success']);
        $this->assertSame('checked_in', $booking->fresh()->status);
    }

    // ─── [4] เลย grace period → เช็คอินไม่ได้ทั้ง Auto และ Manual ────────────

    public function test_reservation_past_grace_period_cannot_be_checked_in(): void
    {
        $booking = $this->confirmedBooking($this->lotWithSlots(0), [
            'reserve_start' => now()->subMinutes(Reservation::gracePeriodMinutes() + 1),
        ]);

        $this->assertFalse($this->service()->checkInReservation($booking)['success']);
        $this->assertFalse($this->service()->checkInReservation($booking, User::factory()->create(['role' => 'admin']), allowEarly: true)['success']);
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [5] เฉพาะ confirmed เท่านั้น ──────────────────────────────────────

    public function test_only_confirmed_reservations_can_be_checked_in(): void
    {
        $lot = $this->lotWithSlots(1);

        foreach (['pending', 'cancelled', 'expired'] as $status) {
            $reservation = Reservation::factory()->create([
                'parking_lot_id' => $lot->id,
                'status'         => $status,
                'reserve_start'  => now(),
            ]);

            $this->assertFalse($this->service()->checkInReservation($reservation)['success'], $status);
        }

        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [6] รถที่จอดอยู่แล้วเช็คอินซ้ำไม่ได้ ───────────────────────────────

    public function test_car_that_is_already_parked_cannot_check_in_again(): void
    {
        $lot = $this->lotWithSlots(2);
        $this->assertTrue($this->walkIn($lot)['success']);

        $second = $this->walkIn($lot);

        $this->assertFalse($second['success']);
        $this->assertSame(CheckInService::OUTCOME_ALREADY_PARKED, $second['outcome']);
        $this->assertDatabaseCount('parking_logs', 1);
        $this->assertDatabaseCount('reservations', 1);
    }

    // ─── [7] Walk-in = Reservation ของ Walkin User ──────────────────────────

    public function test_walk_in_creates_a_checked_in_reservation_for_the_walkin_user(): void
    {
        $lot = $this->lotWithSlots(1, ['reservations_enabled' => false]);

        $result = $this->walkIn($lot);

        $this->assertTrue($result['success']);

        $walkIn = $result['reservation']->fresh();
        $this->assertTrue($walkIn->is_walk_in);
        $this->assertSame(User::walkin()->id, $walkIn->user_id);
        $this->assertSame('checked_in', $walkIn->status);
        $this->assertEquals(0, (float) $walkIn->deposit_amount);
        $this->assertEquals(0, (float) $walkIn->reservation_fee);
        $this->assertSame(
            [self::PLATE, self::PROVINCE, 'Toyota', 'ขาว'],
            [$walkIn->license_plate, $walkIn->plate_province, $walkIn->brand, $walkIn->color]
        );
        $this->assertLessThan(5, abs(now()->diffInSeconds($walkIn->reserve_start)));
        $this->assertSame($result['slot']->id, $walkIn->parking_slot_id);

        $this->assertDatabaseHas('parking_slots', ['id' => $walkIn->parking_slot_id, 'status' => 'occupied']);
        $this->assertDatabaseHas('parking_logs', ['reservation_id' => $walkIn->id, 'parking_lot_id' => $lot->id, 'check_out_time' => null]);
        $this->assertDatabaseHas('reservation_logs', ['reservation_id' => $walkIn->id, 'old_status' => null, 'new_status' => 'checked_in']);
        $this->assertDatabaseMissing('payments', ['reservation_id' => $walkIn->id]);
    }

    // ─── [8] Walk-in เข้าลานเต็ม → ไม่บันทึกอะไร ────────────────────────────

    public function test_walk_in_into_full_lot_records_nothing(): void
    {
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'X001', 'status' => 'occupied']);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'X002', 'status' => 'reserved']);

        $result = $this->walkIn($lot);

        $this->assertFalse($result['success']);
        $this->assertSame(CheckInService::OUTCOME_LOT_FULL, $result['outcome']);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [9] Walk-in ไม่ถูกนับในกฎ 1 Active Reservation ──────────────────────

    public function test_walk_in_is_allowed_while_the_car_has_a_booking_in_another_lot(): void
    {
        $booking = $this->confirmedBooking($this->lotWithSlots(0), ['reserve_start' => now()->addHour()]);

        $this->assertTrue($this->walkIn($this->lotWithSlots(1))['success']);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    // ─── [10] Check-out ของ Walk-in: ไม่มีส่วนลด · Walkin User ไม่ได้รับแจ้งเตือน ─

    public function test_walk_in_check_out_has_no_discount_and_does_not_notify_walkin_user(): void
    {
        $lot = $this->lotWithSlots(1, ['hourly_rate' => 30]);
        $walkIn = $this->walkIn($lot);
        $walkIn['log']->update(['check_in_time' => now()->subHours(2)]);

        $this->assertTrue(app(CheckOutService::class)->checkOut($walkIn['log']->fresh())['success']);

        $payment = Payment::where('parking_log_id', $walkIn['log']->id)->firstOrFail();
        $this->assertEquals(60, (float) $payment->parking_fee);
        $this->assertEquals(0, (float) $payment->reservation_discount);
        $this->assertEquals(60, (float) $payment->total_amount);
        $this->assertSame('completed', $walkIn['reservation']->fresh()->status);
        $this->assertSame(0, Notification::where('user_id', User::walkin()->id)->count());
    }
}
