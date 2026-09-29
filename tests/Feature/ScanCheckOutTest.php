<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\Notification;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Services\CarScanService;
use App\Services\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Auto Check-out — สแกนรถที่กำลังจอดอยู่ในลานนั้น (ระบบตรวจทิศทางเอง) */
class ScanCheckOutTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE    = 'กข 1234';
    private const PROVINCE = 'กรุงเทพมหานคร';

    private User $admin;
    private User $owner;
    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = $this->makeUser('admin');
        $this->owner = $this->makeUser('owner');

        $this->lot = ParkingLot::factory()->create(['owner_id' => $this->owner->id, 'hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'slot_number' => 'A001']);
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function fakeAi(array $overrides = []): void
    {
        $detected = array_merge([
            'license_plate' => self::PLATE,
            'province'      => self::PROVINCE,
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 95,
        ], $overrides);

        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn($detected));
    }

    private function scan(?ParkingLot $lot = null): TestResponse
    {
        $uploader = $this->makeUser();

        return $this->actingAs($uploader)->post(route('user.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => ($lot ?? $this->lot)->id,
        ]);
    }

    /** การจองของรถคันนี้ที่ชำระมัดจำแล้วและกำลังจอดอยู่ 3 ชั่วโมง (อัตรา 40) */
    private function parkedBooking(?ParkingLot $lot = null): Reservation
    {
        $lot ??= $this->lot;
        $slot = ParkingSlot::where('parking_lot_id', $lot->id)->first()
            ?? ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $slot->update(['status' => 'occupied']);

        $reservation = Reservation::factory()->checkedIn()->create([
            'user_id'         => $this->makeUser()->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'license_plate'   => self::PLATE,
            'plate_province'  => self::PROVINCE,
            'deposit_amount'  => 40,
            'reservation_fee' => 40,
        ]);

        Payment::create([
            'type'           => Payment::TYPE_DEPOSIT,
            'reservation_id' => $reservation->id,
            'hourly_rate'    => 40,
            'total_amount'   => 40,
            'payment_status' => Payment::STATUS_PAID,
            'paid_at'        => now()->subDay(),
        ]);

        ParkingLog::factory()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => $reservation->id,
            'license_plate'   => self::PLATE,
            'plate_province'  => self::PROVINCE,
            'check_in_time'   => now()->subHours(3),
            'check_out_time'  => null,
        ]);

        return $reservation;
    }

    /** ผู้จองกด Check-out แล้วชำระจากหน้าของตัวเอง (§12.6) */
    private function bookerPays(Reservation $reservation): void
    {
        $this->actingAs($reservation->user)->post(route('user.reservations.checkout-request', $reservation))->assertSessionHas('success');
        $this->actingAs($reservation->user)->post(route('user.reservations.checkout-pay', $reservation))->assertSessionHas('success');
    }

    /** คนขับ Walk-in กด Check-out แล้วชำระที่หน้าเช็คสถานะรถ ด้วยทะเบียน + รหัสอ้างอิง */
    private function walkInPays(Reservation $walkIn): void
    {
        $credentials = [
            'plate_number'   => $walkIn->license_plate,
            'plate_province' => $walkIn->plate_province,
            'reference_code' => $walkIn->reference_code,
        ];

        $this->post(route('track.checkout'), $credentials)->assertOk()->assertSee('ชำระเงิน');
        $this->post(route('track.pay'), $credentials)->assertOk()->assertSee('สแกนออกได้ภายใน');
    }

    private function notified(User $user, string $title): bool
    {
        return Notification::where('user_id', $user->id)->where('title', $title)->exists();
    }

    private function staffNotified(string $title): bool
    {
        return $this->notified($this->admin, $title) && $this->notified($this->owner, $title);
    }

    // ─── [1] รถที่จอดอยู่ในลานนี้ + ชำระแล้ว → Auto Check-out ──────────────────

    public function test_scanning_a_car_parked_in_this_lot_checks_it_out(): void
    {
        $reservation = $this->parkedBooking();
        $this->bookerPays($reservation);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'checked_out' && $v['slot'] === 'A001' && $v['payment_id']);

        $reservation->refresh();
        $this->assertSame('completed', $reservation->status);
        $this->assertNotNull($reservation->parkingLog->check_out_time);
        $this->assertDatabaseHas('parking_slots', ['id' => $reservation->parking_slot_id, 'status' => 'available']);

        // ใบเดียว ชำระแล้วตั้งแต่ก่อนออก — ไม่มียอดค้างให้เจ้าหน้าที่ตามเก็บ
        $payment = Payment::where('reservation_id', $reservation->id)->where('type', Payment::TYPE_CHECKOUT)->sole();
        $this->assertEquals(120, (float) $payment->parking_fee);
        $this->assertEquals(40, (float) $payment->deposit_deduction);
        $this->assertEquals(40, (float) $payment->reservation_discount);
        $this->assertEquals(40, (float) $payment->total_amount);
        $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
        $this->assertSame($reservation->user_id, $payment->paid_by);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'new_status'     => 'completed',
            'changed_by'     => null,
        ]);
        $this->assertTrue($this->notified($reservation->user, 'เช็คเอาท์เรียบร้อย'));
        $this->assertSame('passed', LicensePlateScan::firstOrFail()->result);
        $this->assertDatabaseCount('reservations', 1); // ไม่สร้าง Walk-in ใหม่
    }

    // ─── [2] Walk-in เข้าแล้วสแกนอีกครั้งตอนออก → Check-out ไม่มีส่วนลด ─────────

    public function test_walk_in_enters_and_leaves_by_scan(): void
    {
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'walk_in');

        $this->travel(2)->hours();

        // คนขับ Walk-in ไม่มีบัญชี — กด Check-out และชำระที่หน้าเช็คสถานะรถ แล้วจึงสแกนออก
        $walkIn = Reservation::where('is_walk_in', true)->firstOrFail();
        $this->walkInPays($walkIn);

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');

        $walkIn->refresh();
        $this->assertSame('completed', $walkIn->status);

        $payment = Payment::where('reservation_id', $walkIn->id)->sole();
        $this->assertEquals(80, (float) $payment->parking_fee);
        $this->assertEquals(0, (float) $payment->deposit_deduction);
        $this->assertEquals(0, (float) $payment->reservation_discount);
        $this->assertEquals(80, (float) $payment->total_amount);
        // ชำระแล้วก่อนออก · ไม่มีบัญชีผู้ชำระ
        $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
        $this->assertNull($payment->paid_by);

        $this->assertSame(0, Notification::where('user_id', User::walkin()->id)->count());
    }

    // ─── [3] รถจอดอยู่ลานอื่น → ไม่ Check-out · บันทึก Scan + Audit Log (ไม่แจ้งเตือน — §15.4) ─

    public function test_car_parked_in_another_lot_is_not_checked_out(): void
    {
        $otherOwner = $this->makeUser('owner');
        $otherLot = ParkingLot::factory()->create(['owner_id' => $otherOwner->id]);
        $reservation = $this->parkedBooking($otherLot);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => !$v['success'] && $v['outcome'] === 'parked_elsewhere' && !$v['staff_notified']);

        $this->assertSame('checked_in', $reservation->fresh()->status);
        $this->assertNull($reservation->parkingLog->fresh()->check_out_time);
        $this->assertDatabaseMissing('payments', ['type' => Payment::TYPE_CHECKOUT]);
        $this->assertDatabaseCount('license_plate_scans', 1);
        $this->assertDatabaseHas('admin_actions', ['action' => 'ai_scan.parked_elsewhere', 'actor_role' => 'system']);

        $this->assertSame(0, Notification::whereIn('user_id', [$this->owner->id, $this->admin->id, $otherOwner->id])->count());
    }

    // ─── [4] AI ไม่ผ่านเกณฑ์ขณะออก → ไม่ Check-out · แจ้ง Owner/Admin ─────────────

    public function test_low_accuracy_exit_scan_does_not_check_out(): void
    {
        $reservation = $this->parkedBooking();
        $this->fakeAi(['confidence' => 70]);

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => !$v['success']);

        $this->assertSame('checked_in', $reservation->fresh()->status);
        $this->assertDatabaseMissing('payments', ['type' => Payment::TYPE_CHECKOUT]);
        $this->assertTrue($this->staffNotified('ความแม่นยำของ AI ไม่ผ่านเกณฑ์'));
    }

    // ─── [5] รถ Blacklist ขณะออก → ยัง Check-out ได้ + แจ้งเตือน ────────────────

    public function test_blacklisted_car_is_still_checked_out_and_alerted(): void
    {
        $reservation = $this->parkedBooking();
        SuspiciousVehicle::factory()->create(['license_plate' => self::PLATE, 'plate_province' => self::PROVINCE]);
        $this->bookerPays($reservation);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'checked_out');

        $this->assertSame('completed', $reservation->fresh()->status);
        $this->assertTrue($this->staffNotified('⚠ พบรถในบัญชีดำ'));
    }

    // ─── ชำระก่อนออก (§12.6) ─────────────────────────────────────────────────

    /** [6] สแกนออกโดยไม่ได้กด Check-out และชำระ → ไม่ปล่อยรถ */
    public function test_scanning_out_without_paying_is_refused(): void
    {
        $reservation = $this->parkedBooking();
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => ! $v['success']
            && $v['outcome'] === 'payment_required'
            && str_contains($v['message'], 'Check-out ไม่สำเร็จ กรุณาชำระค่าจอด')
            && ! $v['staff_notified']);

        // รถยังจอดอยู่ในระบบ ช่องยังไม่ว่าง และไม่มียอดค้างเกิดขึ้น
        $this->assertSame('checked_in', $reservation->fresh()->status);
        $this->assertNull($reservation->parkingLog->fresh()->check_out_time);
        $this->assertDatabaseHas('parking_slots', ['id' => $reservation->parking_slot_id, 'status' => 'occupied']);
        $this->assertDatabaseMissing('payments', ['type' => Payment::TYPE_CHECKOUT]);

        // เป็นเรื่องปกติของคนขับ ไม่ใช่ความผิดพลาด — ไม่รบกวนเจ้าหน้าที่ แต่บันทึกไว้ตรวจสอบ
        $this->assertFalse($this->staffNotified('Check-out อัตโนมัติไม่สำเร็จ'));
        $this->assertDatabaseHas('admin_actions', ['action' => 'ai_scan.payment_required', 'actor_role' => 'system']);
    }

    /** [7] กด Check-out แล้วแต่ยังไม่ชำระ → ยังออกไม่ได้ */
    public function test_requesting_check_out_without_paying_still_blocks_the_exit(): void
    {
        $reservation = $this->parkedBooking();
        $this->actingAs($reservation->user)->post(route('user.reservations.checkout-request', $reservation));
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'payment_required');
        $this->assertSame('checked_in', $reservation->fresh()->status);
    }

    /** [8] กด Check-out แล้วไม่ชำระภายใน 5 นาที → ยอดที่ล็อกไว้หมดอายุ ค่าจอดนับต่อ */
    public function test_unpaid_check_out_expires_after_five_minutes_and_time_keeps_counting(): void
    {
        $reservation = $this->parkedBooking();
        $service = app(CheckOutService::class);

        // จอด 3 ชม. → ค่าจอด 120 − มัดจำ 40 − ส่วนลด 40 = 40
        $quote = $service->requestCheckout($reservation)['state'];
        $this->assertSame(CheckOutService::STATE_AWAITING_PAYMENT, $quote['state']);
        $this->assertEquals(40, $quote['charge']['total_amount']);

        $this->travel(6)->minutes();

        $late = $service->payCheckout($reservation);
        $this->assertFalse($late['success']);
        $this->assertStringContainsString('หมดเวลาชำระแล้ว', $late['error']);
        $this->assertDatabaseMissing('payments', ['type' => Payment::TYPE_CHECKOUT]);
        $this->assertNull($reservation->parkingLog->fresh()->checkout_requested_at);

        // กดใหม่ได้ยอดตามเวลาปัจจุบัน (3 ชม. 6 นาที = 4 ชม.) ไม่ใช่ยอดเดิมที่เคยล็อกไว้
        $requote = $service->requestCheckout($reservation)['state'];
        $this->assertEquals(4, $requote['charge']['total_hours']);
        $this->assertEquals(80, $requote['charge']['total_amount']);
    }

    /** [9] กด Check-out ซ้ำระหว่างรอชำระ ไม่ต่อเวลาให้ — ไม่งั้นกดทุก 4 นาทีเพื่อหยุดค่าจอดได้ */
    public function test_pressing_check_out_again_does_not_extend_the_payment_deadline(): void
    {
        $reservation = $this->parkedBooking();
        $service = app(CheckOutService::class);

        $first = $service->requestCheckout($reservation)['state']['deadline'];
        $this->travel(3)->minutes();
        $again = $service->requestCheckout($reservation)['state']['deadline'];

        $this->assertTrue($first->equalTo($again));

        $this->travel(3)->minutes();
        $this->assertFalse($service->payCheckout($reservation)['success']);
    }

    /** [10] ชำระแล้วสแกนออกภายใน 5 นาที — เวลาที่เลยจากตอนล็อกยอดไม่ถูกคิดเพิ่ม แม้จะข้ามชั่วโมง */
    public function test_exit_within_five_minutes_after_paying_is_not_charged_extra(): void
    {
        $reservation = $this->parkedBooking();
        $this->bookerPays($reservation);
        $this->fakeAi();

        // 3 ชม. 4 นาที = ข้ามเข้าชั่วโมงที่ 4 แล้ว แต่ยังอยู่ในช่วงสแกนออกหลังชำระ
        $this->travel(4)->minutes();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');

        $this->assertSame(1, Payment::where('type', Payment::TYPE_CHECKOUT)->count());
        $this->assertEquals(40, (float) Payment::where('type', Payment::TYPE_CHECKOUT)->sum('total_amount'));
    }

    /**
     * [11] ชำระแล้วแต่ไม่สแกนออกภายใน 5 นาที → ค่าจอดนับต่อ
     * ตอนออกจริงคิดค่าจอดรวมตั้งแต่เข้าจนถึงเวลาออกจริง โดยเก็บเพิ่มเฉพาะส่วนที่เกินจากที่ชำระแล้ว
     */
    public function test_paid_but_late_exit_charges_the_difference_up_to_the_real_check_out(): void
    {
        $reservation = $this->parkedBooking();
        $this->bookerPays($reservation); // 3 ชม. → 40
        $this->fakeAi();

        $this->travel(6)->minutes();

        // เลยช่วงสแกนออก + ข้ามชั่วโมง → ยังไม่ปล่อย
        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'payment_required'
            && str_contains($v['message'], '฿40.00'));
        $this->assertSame('checked_in', $reservation->fresh()->status);

        // กด Check-out ใหม่ → เก็บเฉพาะชั่วโมงที่ 4 (ค่าจอดรวม 160 − มัดจำ 40 − ส่วนลด 40 − ชำระแล้ว 40)
        $this->bookerPays($reservation);
        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');

        $payments = Payment::where('type', Payment::TYPE_CHECKOUT)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame([40.0, 40.0], $payments->map(fn ($p) => (float) $p->total_amount)->all());

        // ใบล่าสุดอธิบายยอดทั้งหมดได้ในตัวเอง
        $last = $payments->last();
        $this->assertEquals(4, (float) $last->total_hours);
        $this->assertEquals(160, (float) $last->parking_fee);
        $this->assertEquals(40, (float) $last->prior_paid);
        $this->assertSame([Payment::STATUS_PAID, Payment::STATUS_PAID], $payments->pluck('payment_status')->all());

        // รวมที่เก็บได้ทั้งหมด = ค่าจอดถึงเวลาออกจริงหลังหักมัดจำและส่วนลด
        $this->assertEquals(160 - 40 - 40, (float) $payments->sum('total_amount'));
        $this->assertSame($last->id, $reservation->parkingLog->fresh()->payment->id);
    }

    /** [12] ชำระแล้ว เลย 5 นาทีแต่ยังไม่ข้ามชั่วโมง → ไม่มียอดต้องเก็บเพิ่ม จึงออกได้ */
    public function test_late_exit_within_the_same_paid_hour_owes_nothing_more(): void
    {
        $reservation = $this->parkedBooking();
        // ย้อนเวลาเข้าให้เหลือที่ว่างในชั่วโมงที่ 3 อีก 20 นาที
        $reservation->parkingLog->update(['check_in_time' => now()->subMinutes(160)]);
        $this->bookerPays($reservation);
        $this->fakeAi();

        $this->travel(10)->minutes(); // 2 ชม. 50 นาที — ยังอยู่ในชั่วโมงที่ 3 ที่ชำระไว้

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');
        $this->assertSame(1, Payment::where('type', Payment::TYPE_CHECKOUT)->count());
    }

    /** [13] ไม่มียอดต้องชำระ (มัดจำและส่วนลดครอบคลุมค่าจอด) → ไม่มีอะไรให้หนีจ่าย จึงสแกนออกได้เลย */
    public function test_nothing_owed_can_scan_out_without_pressing_check_out(): void
    {
        $reservation = $this->parkedBooking();
        $reservation->parkingLog->update(['check_in_time' => now()->subMinutes(30)]); // 1 ชม. = 40 = มัดจำพอดี
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');

        $payment = Payment::where('type', Payment::TYPE_CHECKOUT)->sole();
        $this->assertEquals(0, (float) $payment->total_amount);
        $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
    }

    /** [14] Manual Check-out ของเจ้าหน้าที่ยังทำได้โดยไม่ต้องชำระก่อน — ยอดที่เหลือเป็น "รอชำระ" */
    public function test_staff_manual_check_out_does_not_require_prepayment(): void
    {
        $reservation = $this->parkedBooking();

        $this->actingAs($this->owner)->post(route('owner.reservations.check-out', $reservation))->assertSessionHas('success');

        $this->assertSame('completed', $reservation->fresh()->status);
        $payment = Payment::where('type', Payment::TYPE_CHECKOUT)->sole();
        $this->assertSame(Payment::STATUS_UNPAID, $payment->payment_status);
        $this->assertEquals(40, (float) $payment->total_amount);
    }

    /** [15] ชำระไปแล้วบางส่วน แล้วเจ้าหน้าที่ Manual Check-out → หักที่ชำระแล้ว ไม่เก็บซ้ำ */
    public function test_manual_check_out_after_a_prior_payment_only_bills_the_remainder(): void
    {
        $reservation = $this->parkedBooking();
        $this->bookerPays($reservation);

        $this->travel(6)->minutes(); // เลยช่วงสแกนออก ข้ามเข้าชั่วโมงที่ 4

        $this->actingAs($this->owner)->post(route('owner.reservations.check-out', $reservation))->assertSessionHas('success');

        $last = Payment::where('type', Payment::TYPE_CHECKOUT)->orderByDesc('id')->first();
        $this->assertSame(Payment::STATUS_UNPAID, $last->payment_status);
        $this->assertEquals(40, (float) $last->prior_paid);
        $this->assertEquals(40, (float) $last->total_amount);
    }

    /** [16] ผู้ใช้กด Check-out ให้การจองของคนอื่นไม่ได้ */
    public function test_user_cannot_check_out_someone_elses_reservation(): void
    {
        $reservation = $this->parkedBooking();
        $stranger = $this->makeUser();

        $this->actingAs($stranger)->post(route('user.reservations.checkout-request', $reservation))->assertForbidden();
        $this->actingAs($stranger)->post(route('user.reservations.checkout-pay', $reservation))->assertForbidden();
        $this->assertNull($reservation->parkingLog->fresh()->checkout_requested_at);
    }

    /** [17] หน้าเช็คสถานะรถต้องยืนยันด้วยรหัสทุกครั้ง — รหัสผิดกดชำระให้คันอื่นไม่ได้ */
    public function test_track_page_actions_require_the_reference_code(): void
    {
        $this->fakeAi();
        $this->scan(); // Walk-in เข้า
        $walkIn = Reservation::where('is_walk_in', true)->firstOrFail();

        $wrong = ['plate_number' => $walkIn->license_plate, 'plate_province' => $walkIn->plate_province, 'reference_code' => 'ZZZ999'];

        $this->post(route('track.checkout'), $wrong)->assertRedirect(route('track.show'))->assertSessionHasErrors('reference_code');
        $this->post(route('track.pay'), $wrong)->assertRedirect(route('track.show'))->assertSessionHasErrors('reference_code');
        $this->assertNull($walkIn->parkingLog->fresh()->checkout_requested_at);
    }
}
