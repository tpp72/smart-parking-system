<?php

namespace Tests\Feature;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Services\CarScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ทะเบียนที่เขียนคนละรูปแบบต้องถือเป็นคันเดียวกันทุกทางเข้า (AI · การจอง · บัญชีดำ · ค้นหา)
 * และข้อมูลที่บันทึกต้องเป็นรูปแบบมาตรฐานของระบบเสมอ (ตัวคั่นเป็นช่องว่าง)
 */
class LicensePlateMatchingTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINCE = 'กรุงเทพมหานคร';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role' => $role, 'force_password_reset' => false, 'email_verified_at' => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function lotWithSlot(?User $owner = null): ParkingLot
    {
        $lot = ParkingLot::factory()->create(['owner_id' => $owner?->id]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        return $lot;
    }

    private function fakeAi(string $plate): void
    {
        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn([
            'license_plate' => $plate,
            'province'      => self::PROVINCE,
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 95,
        ]));
    }

    // ─── บันทึกเป็นรูปแบบมาตรฐานเสมอ ────────────────────────────────────────

    public function test_reservation_stores_the_canonical_plate_whatever_the_user_types(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => '1กข-1234',
            'plate_province' => self::PROVINCE,
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservations', ['license_plate' => '1กข 1234']);
        $this->assertDatabaseMissing('reservations', ['license_plate' => '1กข-1234']);
    }

    public function test_ai_result_is_stored_in_the_canonical_format(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();
        $this->fakeAi('กข1234');

        $this->actingAs($admin)->post(route('admin.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertDatabaseHas('license_plate_scans', ['license_plate' => 'กข 1234']);
    }

    // ─── จับคู่ข้ามรูปแบบ ───────────────────────────────────────────────────

    /** AI อ่านได้ "1กข1234" ต้องจับคู่กับการจองที่เก็บเป็น "1กข 1234" */
    public function test_ai_matches_a_booking_written_in_another_format(): void
    {
        $user = $this->makeUser();
        $owner = $this->makeUser('owner');
        $lot = $this->lotWithSlot($owner);
        $slot = $lot->slots()->first();

        Reservation::factory()->create([
            'user_id' => $user->id, 'parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id,
            'license_plate' => '1กข 1234', 'plate_province' => self::PROVINCE,
            'brand' => 'Toyota', 'color' => 'ขาว',
            'status' => 'confirmed', 'reserve_start' => now()->subMinutes(5), 'is_walk_in' => false,
        ]);

        $this->fakeAi('1กข-1234');
        $this->actingAs($owner)->post(route('owner.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertSame('checked_in', Reservation::first()->status, 'AI ไม่ได้จับคู่กับการจองที่รูปแบบต่างกัน');
    }

    /** บัญชีดำบันทึก "กข 1234" — AI อ่านได้ "กข-1234" ต้องยังแจ้งเตือน */
    public function test_blacklist_matches_across_formats(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();
        SuspiciousVehicle::factory()->create([
            'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE, 'is_active' => true,
        ]);

        $this->fakeAi('กข-1234');
        $this->actingAs($admin)->post(route('admin.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertTrue((bool) \App\Models\LicensePlateScan::first()->is_suspicious, 'บัญชีดำไม่จับคู่ข้ามรูปแบบ');
    }

    /** ทะเบียนคนละคัน และจังหวัดต่างกัน ต้องไม่จับคู่กัน */
    public function test_different_plate_or_province_never_matches(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();
        SuspiciousVehicle::factory()->create([
            'license_plate' => 'กข 1234', 'plate_province' => 'ชลบุรี', 'is_active' => true,
        ]);

        $this->fakeAi('กข 1234');   // ทะเบียนตรง แต่คนละจังหวัด
        $this->actingAs($admin)->post(route('admin.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertFalse((bool) \App\Models\LicensePlateScan::first()->is_suspicious);
    }

    // ─── กันการจองซ้ำข้ามรูปแบบ (requirement §24) ──────────────────────────

    public function test_duplicate_active_reservation_is_blocked_across_formats(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        Reservation::factory()->create([
            'user_id' => $user->id, 'parking_lot_id' => $lot->id,
            'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE,
            'status' => 'confirmed', 'is_walk_in' => false, 'reserve_start' => now()->addHour(),
        ]);

        foreach (['กข-1234', 'กข1234', 'กข  1234'] as $variant) {
            $this->actingAs($user)->post(route('user.reservations.store'), [
                'plate_number'   => $variant,
                'plate_province' => self::PROVINCE,
                'brand'          => 'Honda',
                'color'          => 'ดำ',
                'parking_lot_id' => $lot->id,
                'reserve_start'  => now()->addHours(3)->format('Y-m-d H:i:s'),
            ])->assertSessionHasErrors('license_plate');
        }

        $this->assertSame(1, Reservation::count());
    }

    public function test_parked_car_is_detected_across_formats(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        $reservation = Reservation::factory()->create([
            'user_id' => $user->id, 'parking_lot_id' => $lot->id,
            'license_plate' => 'กข 9999', 'plate_province' => self::PROVINCE,
            'status' => 'checked_in', 'is_walk_in' => false, 'reserve_start' => now()->subHour(),
        ]);
        ParkingLog::factory()->create([
            'reservation_id' => $reservation->id, 'parking_lot_id' => $lot->id,
            'license_plate' => 'กข 9999', 'plate_province' => self::PROVINCE,
            'check_in_time' => now()->subHour(), 'check_out_time' => null,
        ]);

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => 'กข-9999',
            'plate_province' => self::PROVINCE,
            'brand'          => 'Honda',
            'color'          => 'ดำ',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(3)->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('license_plate');
    }

    // ─── บัญชีดำซ้ำข้ามรูปแบบ ───────────────────────────────────────────────

    public function test_blacklist_rejects_the_same_plate_written_differently(): void
    {
        $admin = $this->makeUser('admin');
        SuspiciousVehicle::factory()->create([
            'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE,
        ]);

        $this->actingAs($admin)->post(route('admin.suspicious-vehicles.store'), [
            'license_plate'  => 'กข-1234',
            'plate_province' => self::PROVINCE,
            'level'          => 'low',
        ])->assertSessionHasErrors('license_plate');

        $this->assertSame(1, SuspiciousVehicle::count());
    }

    // ─── ค้นหา ─────────────────────────────────────────────────────────────

    public function test_search_finds_a_plate_typed_in_any_format(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();
        $reservation = Reservation::factory()->create([
            'user_id' => $this->makeUser()->id, 'parking_lot_id' => $lot->id,
            'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE,
            'status' => 'checked_in', 'is_walk_in' => false, 'reserve_start' => now()->subHour(),
        ]);
        ParkingLog::factory()->create([
            'reservation_id' => $reservation->id, 'parking_lot_id' => $lot->id,
            'license_plate' => 'กข 1234', 'plate_province' => self::PROVINCE,
            'check_in_time' => now()->subHour(),
        ]);

        foreach (['กข1234', 'กข-1234', 'กข 1234', 'กข  1234'] as $term) {
            $this->actingAs($admin)->get(route('admin.parking-logs.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('กข 1234');

            $this->actingAs($admin)->get(route('admin.reservations.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('กข 1234');
        }
    }

    public function test_search_with_only_separators_does_not_crash(): void
    {
        $admin = $this->makeUser('admin');

        foreach (['-', '   ', '--'] as $term) {
            $this->actingAs($admin)->get(route('admin.parking-logs.index', ['q' => $term]))->assertOk();
        }
    }

    // ─── ทะเบียนลักษณะพิเศษต้องบันทึกได้ ไม่ถูกปฏิเสธ ────────────────────────

    /** คำมั่วที่ไม่มีตัวเลขต้องถูกปัดตก แต่ป้ายลักษณะพิเศษยังบันทึกได้ */
    public function test_junk_input_is_rejected_on_every_form(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();

        foreach (['ฟ้าพฟฟด', 'กขคง', 'ABCD', '🚗'] as $junk) {
            $this->actingAs($user)->post(route('user.reservations.store'), [
                'plate_number'   => $junk,
                'plate_province' => self::PROVINCE,
                'brand'          => 'Toyota',
                'color'          => 'ขาว',
                'parking_lot_id' => $lot->id,
                'reserve_start'  => now()->addHours(2)->format('Y-m-d H:i:s'),
            ])->assertSessionHasErrors('plate_number');

            $this->actingAs($admin)->post(route('admin.suspicious-vehicles.store'), [
                'license_plate'  => $junk,
                'plate_province' => self::PROVINCE,
                'level'          => 'low',
            ])->assertSessionHasErrors('license_plate');
        }

        $this->assertSame(0, Reservation::count());
        $this->assertSame(0, SuspiciousVehicle::count());
    }

    /** ยี่ห้อรถต้องเลือกจากรายการเดียวกับที่ AI ถูกสั่งให้ตอบ เพื่อให้เทียบกันได้ตรง */
    public function test_brand_must_come_from_the_shared_list(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        $payload = fn (string $brand) => [
            'plate_number'   => 'กข 1111',
            'plate_province' => self::PROVINCE,
            'brand'          => $brand,
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(2)->format('Y-m-d H:i:s'),
        ];

        $this->actingAs($user)->post(route('user.reservations.store'), $payload('ยี่ห้อมั่ว'))
            ->assertSessionHasErrors('brand');
        $this->assertSame(0, Reservation::count());

        $this->actingAs($user)->post(route('user.reservations.store'), $payload('Toyota'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['brand' => 'Toyota']);

        // ทุกยี่ห้อที่ seeder ใช้ต้องอยู่ในรายการ ไม่งั้นแก้ข้อมูลรถผ่านฟอร์มไม่ได้
        foreach (['Toyota', 'Honda', 'Isuzu', 'Ford', 'Mazda', 'Nissan', 'BMW', 'Mercedes-Benz', 'Mitsubishi', 'Suzuki'] as $brand) {
            $this->assertContains($brand, config('car_brands'), "seeder ใช้ยี่ห้อ $brand ที่ไม่มีในรายการ");
        }
    }

    // ─── รหัสอ้างอิงสำหรับคนขับ Walk-in ───────────────────────────────────

    /** รถ Walk-in ต้องได้รหัสอ้างอิง และหน้าผลสแกนต้องแสดงให้เจ้าหน้าที่อ่านให้คนขับ */
    public function test_walk_in_gets_a_reference_code_shown_on_the_scan_screen(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->lotWithSlot();
        $this->fakeAi('กข 4321');

        $this->actingAs($admin)->from(route('admin.scan.create'))->followingRedirects()
            ->post(route('admin.scan.store'), [
                'car_image'      => UploadedFile::fake()->image('car.jpg'),
                'parking_lot_id' => $lot->id,
            ])->assertOk()->assertSee('รหัสอ้างอิงสำหรับคนขับ');

        $reservation = Reservation::first();
        $this->assertTrue((bool) $reservation->is_walk_in);
        $this->assertSame(6, strlen((string) $reservation->reference_code));
        // ตัดอักขระที่อ่านสับสนออก เพราะคนขับต้องอ่านจากจอแล้วพิมพ์เอง
        $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', $reservation->reference_code);
    }

    /** การจองล่วงหน้าไม่ต้องมีรหัส — เจ้าของบัญชีดูในหน้าหลักของตัวเองได้อยู่แล้ว */
    public function test_booked_reservation_has_no_reference_code(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => 'กข 7777',
            'plate_province' => self::PROVINCE,
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertNull(Reservation::first()->reference_code);
    }

    public function test_reference_codes_are_unique(): void
    {
        $codes = collect(range(1, 50))->map(fn () => Reservation::generateReferenceCode());

        $this->assertSame(50, $codes->unique()->count());
    }

    public function test_special_plates_can_be_saved_and_are_not_reshaped(): void
    {
        $user = $this->makeUser();
        $lot = $this->lotWithSlot();

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => 'น่ารัก 9999',
            'plate_province' => self::PROVINCE,
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHours(2)->format('Y-m-d H:i:s'),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reservations', ['license_plate' => 'น่ารัก 9999']);
    }
}
