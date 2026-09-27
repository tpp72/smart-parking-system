<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserVehicle;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ผูกทะเบียนรถกับบัญชี — ทำให้รถที่เข้าลานแบบ Walk-in ผูกกับเจ้าของบัญชีอัตโนมัติ
 * ระบบยังเป็น Plate-based: การจองยังกรอกทะเบียนตอนจอง ไม่ต้องผูกรถก่อน
 */
class UserVehicleTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINCE = 'กรุงเทพมหานคร';

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create([
            'role' => $role, 'force_password_reset' => false, 'email_verified_at' => now(),
        ]);
    }

    private function lotWithSlot(): ParkingLot
    {
        $lot = ParkingLot::factory()->create(['hourly_rate' => 20]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        return $lot;
    }

    /** รถที่กำลังจอดอยู่ พร้อมรหัสอ้างอิง (ใช้พิสูจน์ตัวตนตอนผูกรถ) */
    private function parkedCar(string $plate = 'กข 1234', string $code = 'ABC234'): Reservation
    {
        $lot = $this->lotWithSlot();
        $slot = $lot->slots()->first();

        $reservation = Reservation::factory()->create([
            'user_id' => User::walkin()->id, 'is_walk_in' => true,
            'parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id,
            'license_plate' => $plate, 'plate_province' => self::PROVINCE,
            'status' => 'checked_in', 'deposit_amount' => 0, 'reservation_fee' => 0,
            'reserve_start' => now()->subHour(), 'reference_code' => $code,
        ]);

        ParkingLog::factory()->create([
            'reservation_id' => $reservation->id, 'parking_lot_id' => $lot->id,
            'parking_slot_id' => $slot->id, 'license_plate' => $plate,
            'plate_province' => self::PROVINCE, 'hourly_rate' => 20,
            'check_in_time' => now()->subHour(), 'check_out_time' => null,
        ]);

        return $reservation;
    }

    private function verify(string $plate = 'กข 1234', string $code = 'ABC234'): void
    {
        $this->post(route('track.find'), [
            'plate_number' => $plate, 'plate_province' => self::PROVINCE, 'reference_code' => $code,
        ])->assertOk();
    }

    // ─── ต้องพิสูจน์ด้วยรหัสก่อนถึงผูกได้ ──────────────────────────────────

    public function test_cannot_claim_without_verifying_with_the_reference_code_first(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('track.claim.form'))->assertRedirect(route('track.show'));
        $this->actingAs($user)->post(route('track.claim'))->assertRedirect(route('track.show'));

        $this->assertSame(0, UserVehicle::count());
    }

    public function test_claiming_after_verification_links_the_plate(): void
    {
        $user = $this->makeUser();
        $this->parkedCar();

        $this->actingAs($user);
        $this->verify();

        $this->get(route('track.claim.form'))->assertOk()->assertSee('ผูกรถกับบัญชี');
        $this->post(route('track.claim'))->assertRedirect(route('profile.edit'));

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $user->id, 'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE,
        ]);
        $this->assertDatabaseHas('admin_actions', ['action' => 'user_vehicle.link']);
    }

    /** หลักฐานใช้ได้ครั้งเดียว — ผูกคันที่สองต้องเช็คสถานะด้วยรหัสใหม่ */
    public function test_proof_is_consumed_after_one_claim(): void
    {
        $user = $this->makeUser();
        $this->parkedCar();

        $this->actingAs($user);
        $this->verify();
        $this->post(route('track.claim'));

        $this->post(route('track.claim'))->assertRedirect(route('track.show'));
        $this->assertSame(1, UserVehicle::count());
    }

    public function test_one_plate_belongs_to_one_account_only(): void
    {
        $first = $this->makeUser();
        $second = $this->makeUser();
        $this->parkedCar();

        $this->actingAs($first);
        $this->verify();
        $this->post(route('track.claim'));

        $this->actingAs($second);
        $this->verify();
        $this->post(route('track.claim'))->assertSessionHasErrors('plate');

        $this->assertSame(1, UserVehicle::count());
        $this->assertSame($first->id, UserVehicle::first()->user_id);
    }

    public function test_guest_is_sent_to_login_and_back(): void
    {
        $this->parkedCar();
        $this->verify();

        $this->get(route('track.claim.form'))->assertRedirect(route('login'));
    }

    // ─── รถที่ผูกไว้ผูกกับบัญชีอัตโนมัติตอนเข้าลาน ──────────────────────────

    public function test_walk_in_is_linked_to_the_owner_account(): void
    {
        $user = $this->makeUser();
        UserVehicle::create([
            'user_id' => $user->id, 'license_plate' => 'ชล 5678', 'plate_province' => self::PROVINCE,
        ]);

        $lot = $this->lotWithSlot();
        $result = app(CheckInService::class)->checkInWalkIn($lot, 'ชล 5678', self::PROVINCE, 'Toyota', 'ขาว');

        $this->assertTrue($result['success']);
        $reservation = $result['reservation'];

        // เป็นของเจ้าของบัญชี ไม่ใช่บัญชีระบบ
        $this->assertSame($user->id, $reservation->user_id);
        // แต่ยังเป็น Walk-in — ไม่มีมัดจำ ไม่มีส่วนลด
        $this->assertTrue((bool) $reservation->is_walk_in);
        $this->assertSame('0.00', (string) $reservation->deposit_amount);
        $this->assertSame('0.00', (string) $reservation->reservation_fee);

        // เจ้าของได้รับการแจ้งเตือน (รถที่ไม่มีเจ้าของบัญชีไม่มีใครรับ)
        $this->assertTrue(Notification::where('user_id', $user->id)->where('title', 'รถของคุณเข้าจอดแล้ว')->exists());
    }

    public function test_unclaimed_plate_still_belongs_to_the_system_account(): void
    {
        $lot = $this->lotWithSlot();
        $result = app(CheckInService::class)->checkInWalkIn($lot, 'ขค 9999', self::PROVINCE, 'Honda', 'ดำ');

        $this->assertSame(User::walkin()->id, $result['reservation']->user_id);
        $this->assertSame(0, Notification::count());
    }

    /** ทะเบียนที่ผูกไว้เทียบแบบไม่สนตัวคั่น เหมือนที่อื่นทั้งระบบ */
    public function test_linked_plate_matches_across_formats(): void
    {
        $user = $this->makeUser();
        UserVehicle::create([
            'user_id' => $user->id, 'license_plate' => 'กท-1111', 'plate_province' => self::PROVINCE,
        ]);

        // เก็บเป็นรูปแบบมาตรฐาน
        $this->assertSame('กท 1111', UserVehicle::first()->license_plate);

        $lot = $this->lotWithSlot();
        $result = app(CheckInService::class)->checkInWalkIn($lot, 'กท1111', self::PROVINCE, null, null);

        $this->assertSame($user->id, $result['reservation']->user_id);
    }

    // ─── หน้าโปรไฟล์ ───────────────────────────────────────────────────────

    public function test_profile_lists_and_removes_vehicles(): void
    {
        $user = $this->makeUser();
        $vehicle = UserVehicle::create([
            'user_id' => $user->id, 'license_plate' => 'กข 2222', 'plate_province' => self::PROVINCE,
        ]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('รถของฉัน')
            ->assertSee('กข 2222');

        $this->actingAs($user)->delete(route('my-vehicles.destroy', $vehicle))->assertRedirect();
        $this->assertSame(0, UserVehicle::count());
        $this->assertDatabaseHas('admin_actions', ['action' => 'user_vehicle.unlink']);
    }

    public function test_cannot_remove_someone_elses_vehicle(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $vehicle = UserVehicle::create([
            'user_id' => $owner->id, 'license_plate' => 'กข 3333', 'plate_province' => self::PROVINCE,
        ]);

        $this->actingAs($other)->delete(route('my-vehicles.destroy', $vehicle))->assertForbidden();
        $this->assertSame(1, UserVehicle::count());
    }

    /** เจ้าของลานและผู้ดูแลระบบไม่มีส่วน "รถของฉัน" */
    public function test_my_vehicles_is_only_for_the_user_role(): void
    {
        $this->actingAs($this->makeUser('admin'))->get(route('profile.edit'))->assertOk()->assertDontSee('รถของฉัน');
    }
}
