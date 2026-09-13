<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Services\CarScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Auto Check-in + Walk-in จากผล AI Scan ที่ผ่านเกณฑ์ (Matching · Lot Scope · ลานเต็ม) */
class AutoCheckInTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE    = 'กข 1234';
    private const PROVINCE = 'กรุงเทพมหานคร';

    private const WALK_IN_ALERT = 'Auto Check-in เป็น Walk-in (มีการจองค้างอยู่)';

    private User $admin;
    private User $owner;
    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = $this->makeUser('admin');
        $this->owner = $this->makeUser('owner');

        // ลานที่กล้องติดตั้ง — ว่าง 1 ช่อง
        $this->lot = ParkingLot::factory()->create(['owner_id' => $this->owner->id]);
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

    private function scan(?User $uploader = null): TestResponse
    {
        $uploader ??= $this->makeUser();

        return $this->actingAs($uploader)->post(route("{$uploader->role}.scan.store"), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $this->lot->id,
        ]);
    }

    /** การจองที่ยืนยันแล้วของรถคันนี้ พร้อมช่องที่ Lock ไว้ */
    private function booking(array $attrs = []): Reservation
    {
        $lotId = $attrs['parking_lot_id'] ?? $this->lot->id;
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lotId, 'slot_number' => 'R001', 'status' => 'reserved']);

        return Reservation::factory()->confirmed()->create(array_merge([
            'parking_lot_id'  => $lotId,
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

    private function notified(User $user, string $title): bool
    {
        return Notification::where('user_id', $user->id)->where('title', $title)->exists();
    }

    private function staffNotified(string $title): bool
    {
        return $this->notified($this->admin, $title) && $this->notified($this->owner, $title);
    }

    private function walkIns()
    {
        return Reservation::where('is_walk_in', true);
    }

    // ─── [1] การจอง confirmed ในลานนี้ + ตรงเงื่อนไข → Auto Check-in ──────

    public function test_matching_confirmed_booking_is_checked_in_automatically(): void
    {
        $booking = $this->booking();
        $this->fakeAi(['color' => 'ดำ']); // ยี่ห้อตรง สีไม่ตรง → ผ่าน (ยี่ห้อ OR สี)

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'checked_in' && $v['slot'] === 'R001' && !$v['staff_notified']);

        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertDatabaseHas('parking_logs', ['reservation_id' => $booking->id, 'parking_slot_id' => $booking->parking_slot_id]);
        $this->assertSame(0, $this->walkIns()->count());
        $this->assertTrue($this->notified($booking->user, 'เช็คอินอัตโนมัติสำเร็จ'));
        $this->assertFalse($this->notified($this->owner, 'ยี่ห้อ/สีรถไม่ตรงกับการจอง'));
    }

    // ─── [2] ยี่ห้อและสีไม่ตรงทั้งคู่ → ยังเช็คอินด้วยการจองเดิม + แจ้ง Owner/Admin ─

    public function test_brand_and_color_mismatch_still_checks_in_booking_and_alerts_staff(): void
    {
        $booking = $this->booking();
        $this->fakeAi(['brand' => 'Honda', 'color' => 'ดำ']);

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'checked_in' && $v['staff_notified']);

        $this->assertSame('checked_in', $booking->fresh()->status);
        $this->assertSame(0, $this->walkIns()->count());
        $this->assertTrue($this->staffNotified('ยี่ห้อ/สีรถไม่ตรงกับการจอง'));
    }

    // ─── [3] มาก่อนเวลาจอง → ไม่ Auto Check-in · แจ้งให้ Manual Check-in ────

    public function test_early_arrival_is_not_checked_in_and_staff_is_asked_to_check_in_manually(): void
    {
        $booking = $this->booking(['reserve_start' => now()->addHours(2)]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => !$v['success'] && $v['outcome'] === 'early_arrival');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('parking_logs', 0);
        $this->assertSame(0, $this->walkIns()->count());
        $this->assertTrue($this->staffNotified('รถมาก่อนเวลาจอง'));
        $this->assertDatabaseHas('license_plate_scans', ['license_plate' => self::PLATE, 'result' => 'passed']);

        // Owner ของลานทำ Manual Check-in ได้ก่อนเวลาจอง
        $this->actingAs($this->owner)
            ->post(route('owner.reservations.check-in', $booking))
            ->assertSessionHas('success');

        $this->assertSame('checked_in', $booking->fresh()->status);
    }

    // ─── [4] การจองอยู่ลานอื่น → Walk-in ในลานนี้ + แจ้ง Owner/Admin ของลานนี้ ─

    public function test_booking_in_another_lot_becomes_walk_in_here_and_alerts_this_lots_staff(): void
    {
        $otherOwner = $this->makeUser('owner');
        $otherLot = ParkingLot::factory()->create(['owner_id' => $otherOwner->id]);
        $booking = $this->booking(['parking_lot_id' => $otherLot->id]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'walk_in' && $v['slot'] === 'A001' && $v['staff_notified']);

        $walkIn = $this->walkIns()->firstOrFail();
        $this->assertSame($this->lot->id, $walkIn->parking_lot_id);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertTrue($this->staffNotified(self::WALK_IN_ALERT));
        $this->assertFalse($this->notified($otherOwner, self::WALK_IN_ALERT));
    }

    // ─── [5] การจองในลานนี้ยัง pending → Walk-in แยก + แจ้ง Owner/Admin ─────

    public function test_pending_booking_in_this_lot_becomes_separate_walk_in(): void
    {
        $booking = Reservation::factory()->create([
            'parking_lot_id'  => $this->lot->id,
            'license_plate'   => self::PLATE,
            'plate_province'  => self::PROVINCE,
            'brand'           => 'Toyota',
            'color'           => 'ขาว',
            'reserve_start'   => now()->subMinutes(5),
            'deposit_amount'  => 40,
            'reservation_fee' => 40,
            'status'          => 'pending',
        ]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'walk_in');

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(1, $this->walkIns()->where('status', 'checked_in')->count());
        $this->assertTrue($this->staffNotified(self::WALK_IN_ALERT));
    }

    // ─── [6] การจองเลยเวลาเช็คอิน → Walk-in + แจ้ง Owner/Admin ──────────────

    public function test_booking_past_grace_period_becomes_walk_in(): void
    {
        $booking = $this->booking(['reserve_start' => now()->subMinutes(Reservation::gracePeriodMinutes() + 10)]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['success'] && $v['outcome'] === 'walk_in');

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, $this->walkIns()->count());
        $this->assertTrue($this->staffNotified(self::WALK_IN_ALERT));
    }

    // ─── [7] ไม่พบการจอง → Walk-in (ไม่ขึ้นกับ reservations_enabled) ─────────

    public function test_car_without_booking_is_checked_in_as_walk_in_even_when_lot_does_not_accept_bookings(): void
    {
        $this->lot->update(['reservations_enabled' => false]);
        $uploader = $this->makeUser();
        $this->fakeAi();

        $this->scan($uploader)->assertSessionHas('scan_check_in',
            fn ($v) => $v['success'] && $v['outcome'] === 'walk_in' && $v['slot'] === 'A001' && !$v['staff_notified']);

        $walkIn = $this->walkIns()->firstOrFail();
        $this->assertSame(User::walkin()->id, $walkIn->user_id);
        $this->assertSame('checked_in', $walkIn->status);
        $this->assertEquals(0, (float) $walkIn->deposit_amount);
        $this->assertEquals(0, (float) $walkIn->reservation_fee);
        $this->assertSame([self::PLATE, self::PROVINCE, 'Toyota', 'ขาว'], [$walkIn->license_plate, $walkIn->plate_province, $walkIn->brand, $walkIn->color]);

        $this->assertDatabaseHas('parking_logs', ['reservation_id' => $walkIn->id, 'license_plate' => self::PLATE, 'plate_province' => self::PROVINCE]);
        $this->assertDatabaseHas('parking_slots', ['parking_lot_id' => $this->lot->id, 'slot_number' => 'A001', 'status' => 'occupied']);

        $this->assertSame(0, Notification::where('user_id', User::walkin()->id)->count());
        $this->assertSame(0, Notification::where('user_id', $uploader->id)->count());
        $this->assertSame(0, Notification::where('user_id', $this->owner->id)->count());
    }

    // ─── [8] ทะเบียนตรงแต่จังหวัดไม่ตรง = คนละคัน → Walk-in ปกติ ────────────

    public function test_same_plate_with_different_province_is_a_different_car(): void
    {
        $booking = $this->booking(['plate_province' => 'เชียงใหม่']);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => $v['outcome'] === 'walk_in' && !$v['staff_notified']);

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(0, Notification::where('user_id', $this->owner->id)->count());
    }

    // ─── [9] อ่านเลขทะเบียนได้แต่จังหวัดไม่ได้ = อ่านทะเบียนไม่ได้ ───────────

    public function test_unreadable_province_is_treated_as_unreadable_plate(): void
    {
        $this->booking();
        $this->fakeAi(['province' => '']);

        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => !$v['success']);

        $this->assertSame('unreadable', LicensePlateScan::firstOrFail()->result);
        $this->assertDatabaseCount('parking_logs', 0);
        $this->assertSame(0, $this->walkIns()->count());
        $this->assertTrue($this->staffNotified('AI อ่านทะเบียนไม่ได้'));
    }

    // ─── [10] รถที่จอดอยู่แล้วสแกนซ้ำ → ไม่เช็คอินซ้ำ ──────────────────────

    public function test_car_already_parked_is_not_checked_in_again(): void
    {
        ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'slot_number' => 'A002']);
        $this->fakeAi();

        $this->scan();
        $this->scan()->assertSessionHas('scan_check_in', fn ($v) => !$v['success'] && $v['outcome'] === 'already_parked');

        $this->assertSame(1, $this->walkIns()->count());
        $this->assertDatabaseCount('parking_logs', 1);
    }

    // ─── [11] Walk-in เข้าลานเต็ม → แสดงลานเต็ม · ไม่บันทึกข้อมูลใด ๆ ─────────

    public function test_walk_in_into_full_lot_shows_lot_full_and_records_nothing(): void
    {
        ParkingSlot::where('parking_lot_id', $this->lot->id)->update(['status' => 'occupied']);
        $this->fakeAi();

        $this->scan()
            ->assertSessionHas('scan_lot_full', fn ($v) => str_contains($v['message'], 'ลานเต็ม') && !$v['is_suspicious'])
            ->assertSessionMissing('scan_result');

        $this->assertDatabaseCount('license_plate_scans', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('parking_logs', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('admin_actions', 0);
        $this->assertSame(0, Notification::count());
    }

    // ─── [12] ลานเต็ม + Blacklist → แจ้ง Admin/Owner + บันทึกเหตุการณ์ Blacklist ─

    public function test_blacklisted_car_at_full_lot_is_alerted_and_logged_without_recording_the_scan(): void
    {
        ParkingSlot::where('parking_lot_id', $this->lot->id)->update(['status' => 'occupied']);
        $blacklist = SuspiciousVehicle::factory()->create(['license_plate' => self::PLATE, 'plate_province' => self::PROVINCE]);
        $this->fakeAi();

        $this->scan()->assertSessionHas('scan_lot_full', fn ($v) => $v['is_suspicious']);

        $this->assertDatabaseCount('license_plate_scans', 0);
        $this->assertDatabaseCount('reservations', 0);
        $this->assertTrue($this->staffNotified('⚠ พบรถต้องสงสัย (Blacklist)'));
        $this->assertDatabaseHas('admin_actions', [
            'action'       => 'blacklist.detected',
            'actor_role'   => 'system',
            'actor_id'     => null,
            'subject_type' => 'SuspiciousVehicle',
            'subject_id'   => $blacklist->id,
        ]);
    }
}
