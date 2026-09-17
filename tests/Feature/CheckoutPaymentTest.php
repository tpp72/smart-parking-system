<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Checkout Payment — ค่าจอด − Deposit ที่ชำระแล้ว − reservation_fee (ไม่ติดลบ) · Mark as Paid */
class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    /** การจอง (มัดจำ = ส่วนลด = hourly_rate) ที่กำลังจอดอยู่มา $minutes นาที */
    private function parkedBooking(float $rate, int $minutes, ?User $owner = null, string $depositStatus = Payment::STATUS_PAID): Reservation
    {
        $lot  = ParkingLot::factory()->create(['hourly_rate' => $rate, 'owner_id' => $owner?->id]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = Reservation::factory()->checkedIn()->create([
            'user_id'         => $this->makeUser()->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'deposit_amount'  => $rate,
            'reservation_fee' => $rate,
        ]);

        Payment::create([
            'type'           => Payment::TYPE_DEPOSIT,
            'reservation_id' => $reservation->id,
            'hourly_rate'    => $rate,
            'total_amount'   => $rate,
            'payment_status' => $depositStatus,
            'paid_at'        => $depositStatus === Payment::STATUS_PAID ? now()->subDay() : null,
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

    private function checkOut(Reservation $reservation): Payment
    {
        $this->assertTrue(app(CheckOutService::class)->checkOut($reservation)['success']);

        return Payment::where('reservation_id', $reservation->id)->where('type', Payment::TYPE_CHECKOUT)->firstOrFail();
    }

    // ─── [1] หัก Deposit แล้วหัก reservation_fee ──────────────────────────────

    public function test_paid_deposit_and_reservation_fee_are_deducted(): void
    {
        $reservation = $this->parkedBooking(40, 180); // 3 ชม. × 40 = 120

        $payment = $this->checkOut($reservation);

        $this->assertEquals(120, (float) $payment->parking_fee);
        $this->assertEquals(40, (float) $payment->deposit_deduction);
        $this->assertEquals(40, (float) $payment->reservation_discount);
        $this->assertEquals(40, (float) $payment->total_amount);
        $this->assertSame(Payment::STATUS_UNPAID, $payment->payment_status);
        $this->assertNull($payment->paid_at);

        $notification = Notification::where('user_id', $reservation->user_id)->where('title', 'เช็คเอาท์เรียบร้อย')->firstOrFail();
        $this->assertStringContainsString('หักมัดจำ -฿40.00', $notification->message);
        $this->assertStringContainsString('ส่วนลด -฿40.00', $notification->message);
        $this->assertStringContainsString('ยอดสุทธิ ฿40.00', $notification->message);
    }

    // ─── [2] Deposit + ส่วนลดมากกว่าค่าจอด → ยอดสุทธิ 0 · ชำระแล้วโดยระบบ ─────

    public function test_deductions_never_exceed_parking_fee_and_zero_total_is_settled_by_system(): void
    {
        $oneHour = $this->checkOut($this->parkedBooking(40, 30));   // ค่าจอด 40
        $this->assertEquals(40, (float) $oneHour->deposit_deduction);
        $this->assertEquals(0, (float) $oneHour->reservation_discount);
        $this->assertEquals(0, (float) $oneHour->total_amount);

        $twoHours = $this->checkOut($this->parkedBooking(40, 90)); // ค่าจอด 80
        $this->assertEquals(40, (float) $twoHours->deposit_deduction);
        $this->assertEquals(40, (float) $twoHours->reservation_discount);
        $this->assertEquals(0, (float) $twoHours->total_amount);

        foreach ([$oneHour, $twoHours] as $payment) {
            $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
            $this->assertNull($payment->paid_by);
            $this->assertNotNull($payment->paid_at);
        }
    }

    // ─── [3] หักเฉพาะ Deposit ที่ชำระแล้ว ───────────────────────────────────

    public function test_only_paid_deposit_is_deducted(): void
    {
        $payment = $this->checkOut($this->parkedBooking(40, 180, depositStatus: Payment::STATUS_VOID));

        $this->assertEquals(0, (float) $payment->deposit_deduction);
        $this->assertEquals(40, (float) $payment->reservation_discount);
        $this->assertEquals(80, (float) $payment->total_amount);
    }

    // ─── [4] Admin Mark as Paid ยอดค่าจอด · ห้ามยืนยันซ้ำ · Audit ──────────────

    public function test_admin_marks_checkout_payment_paid_once(): void
    {
        $admin = $this->makeUser('admin');
        $payment = $this->checkOut($this->parkedBooking(40, 180));

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
        $this->assertSame($admin->id, $payment->paid_by);
        $this->assertNotNull($payment->paid_at);
        $this->assertDatabaseHas('admin_actions', ['action' => 'payment.mark_paid', 'subject_id' => $payment->id]);

        $firstPaidAt = $payment->paid_at;

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertSessionHasErrors('error');

        $this->assertSame($admin->id, $payment->fresh()->paid_by);
        $this->assertEquals($firstPaidAt, $payment->fresh()->paid_at);
    }

    // ─── [5] Owner Mark as Paid เฉพาะลานของตัวเอง ────────────────────────────

    public function test_owner_marks_checkout_payment_paid_only_for_own_lot(): void
    {
        $owner = $this->makeUser('owner');
        $own = $this->checkOut($this->parkedBooking(40, 180, $owner));
        $other = $this->checkOut($this->parkedBooking(40, 180, $this->makeUser('owner')));

        $this->actingAs($owner)->post(route('owner.payments.mark-paid', $other))->assertForbidden();
        $this->actingAs($owner)->post(route('owner.payments.mark-paid', $own))->assertSessionHas('success');

        $this->assertSame($owner->id, $own->fresh()->paid_by);
        $this->assertSame(Payment::STATUS_UNPAID, $other->fresh()->payment_status);
    }

    // ─── [6] หน้า Payments แสดงยอดหักมัดจำและส่วนลด ──────────────────────────

    public function test_payments_page_shows_deposit_deduction_and_discount(): void
    {
        $this->checkOut($this->parkedBooking(40, 180));

        $this->actingAs($this->makeUser('admin'))
            ->get(route('admin.payments.index', ['status' => 'all']))
            ->assertOk()
            // ใบเสร็จย่อ: ชื่อรายการและยอดหักอยู่คนละช่อง
            ->assertSeeInOrder(['หักมัดจำ', '−฿40.00', 'ส่วนลดการจอง', '−฿40.00']);
    }
}
