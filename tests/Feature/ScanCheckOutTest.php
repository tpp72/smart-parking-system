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

    private function notified(User $user, string $title): bool
    {
        return Notification::where('user_id', $user->id)->where('title', $title)->exists();
    }

    private function staffNotified(string $title): bool
    {
        return $this->notified($this->admin, $title) && $this->notified($this->owner, $title);
    }

    // ─── [1] รถที่จอดอยู่ในลานนี้ → Auto Check-out ─────────────────────────────

    public function test_scanning_a_car_parked_in_this_lot_checks_it_out(): void
    {
        $reservation = $this->parkedBooking();
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'checked_out' && $v['slot'] === 'A001' && $v['payment_id']);

        $reservation->refresh();
        $this->assertSame('completed', $reservation->status);
        $this->assertNotNull($reservation->parkingLog->check_out_time);
        $this->assertDatabaseHas('parking_slots', ['id' => $reservation->parking_slot_id, 'status' => 'available']);

        $payment = Payment::where('reservation_id', $reservation->id)->where('type', Payment::TYPE_CHECKOUT)->firstOrFail();
        $this->assertEquals(120, (float) $payment->parking_fee);
        $this->assertEquals(40, (float) $payment->deposit_deduction);
        $this->assertEquals(40, (float) $payment->reservation_discount);
        $this->assertEquals(40, (float) $payment->total_amount);

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

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'checked_out');

        $walkIn = Reservation::where('is_walk_in', true)->firstOrFail();
        $this->assertSame('completed', $walkIn->status);

        $payment = Payment::where('reservation_id', $walkIn->id)->firstOrFail();
        $this->assertEquals(80, (float) $payment->parking_fee);
        $this->assertEquals(0, (float) $payment->deposit_deduction);
        $this->assertEquals(0, (float) $payment->reservation_discount);
        $this->assertEquals(80, (float) $payment->total_amount);
        $this->assertSame(Payment::STATUS_UNPAID, $payment->payment_status);

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
        $this->assertTrue($this->staffNotified('AI Accuracy ไม่ผ่านเกณฑ์'));
    }

    // ─── [5] รถ Blacklist ขณะออก → ยัง Check-out ได้ + แจ้งเตือน ────────────────

    public function test_blacklisted_car_is_still_checked_out_and_alerted(): void
    {
        $reservation = $this->parkedBooking();
        SuspiciousVehicle::factory()->create(['license_plate' => self::PLATE, 'plate_province' => self::PROVINCE]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'checked_out');

        $this->assertSame('completed', $reservation->fresh()->status);
        $this->assertTrue($this->staffNotified('⚠ พบรถต้องสงสัย (Blacklist)'));
    }
}
