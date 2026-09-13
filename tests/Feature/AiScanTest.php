<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\Notification;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** AI Scan pipeline — Accuracy > 85% · อ่านทะเบียนไม่ได้ · Blacklist · แจ้ง Owner + Admin */
class AiScanTest extends TestCase
{
    use RefreshDatabase;

    private const PLATE    = 'กข 1234';
    private const PROVINCE = 'กรุงเทพมหานคร';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    /** ลานของ Owner พร้อมช่องว่าง 1 ช่อง */
    private function ownerLot(User $owner, array $attrs = []): ParkingLot
    {
        $lot = ParkingLot::factory()->create(array_merge(['owner_id' => $owner->id], $attrs));
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        return $lot;
    }

    private function fakeAi(array $overrides = [], array $remove = []): void
    {
        $detected = array_diff_key(array_merge([
            'license_plate' => self::PLATE,
            'province'      => self::PROVINCE,
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 95,
        ], $overrides), array_flip($remove));

        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn($detected));
    }

    private function scanAs(User $user, ParkingLot $lot): TestResponse
    {
        return $this->actingAs($user)->post(route("{$user->role}.scan.store"), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);
    }

    private function notified(User $user, string $title): bool
    {
        return Notification::where('user_id', $user->id)->where('title', $title)->exists();
    }

    // ─── [1] เกณฑ์ Accuracy: > 85 เท่านั้นที่ผ่าน ──────────────────────────

    public function test_accuracy_threshold_is_strictly_greater_than_85(): void
    {
        $this->assertSame('passed', LicensePlateScan::classify(self::PLATE, self::PROVINCE, 85.01));
        $this->assertSame('passed', LicensePlateScan::classify(self::PLATE, self::PROVINCE, 100));
        $this->assertSame('low_accuracy', LicensePlateScan::classify(self::PLATE, self::PROVINCE, 85));
        $this->assertSame('low_accuracy', LicensePlateScan::classify(self::PLATE, self::PROVINCE, 10));
        $this->assertSame('low_accuracy', LicensePlateScan::classify(self::PLATE, self::PROVINCE, null));
        $this->assertSame('unreadable', LicensePlateScan::classify(null, self::PROVINCE, 99));
        $this->assertSame('unreadable', LicensePlateScan::classify('  ', self::PROVINCE, 99));
        // อ่านเลขทะเบียนได้แต่อ่านจังหวัดไม่ได้ = อ่านทะเบียนไม่ได้
        $this->assertSame('unreadable', LicensePlateScan::classify(self::PLATE, null, 99));
        $this->assertSame('unreadable', LicensePlateScan::classify(self::PLATE, ' ', 99));
    }

    // ─── [2] AI Output ถูกบันทึกครบ · Accuracy > 85 ผ่าน ─────────────────────

    public function test_ai_output_is_recorded_and_passes_above_threshold(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = $this->ownerLot($owner);
        $this->fakeAi(['confidence' => 86]);

        $this->scanAs($this->makeUser(), $lot)->assertSessionHasNoErrors();

        $scan = LicensePlateScan::firstOrFail();
        $this->assertSame(self::PLATE, $scan->license_plate);
        $this->assertSame(self::PROVINCE, $scan->plate_province);
        $this->assertSame('Toyota', $scan->brand);
        $this->assertSame('ขาว', $scan->color);
        $this->assertEquals(86, $scan->confidence);
        $this->assertSame('passed', $scan->result);
        $this->assertSame($lot->id, $scan->parking_lot_id);
        $this->assertNotNull($scan->image_path);

        $this->assertFalse($this->notified($admin, 'AI Accuracy ไม่ผ่านเกณฑ์'));
        $this->assertFalse($this->notified($owner, 'AI Accuracy ไม่ผ่านเกณฑ์'));
    }

    // ─── [3] Accuracy = 85 → ไม่ผ่าน · ไม่ Auto Check-in · แจ้ง Owner + Admin ─

    public function test_accuracy_at_threshold_blocks_auto_check_in_and_notifies_owner_and_admin(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $uploader = $this->makeUser();
        $lot = $this->ownerLot($owner);

        $reservation = Reservation::factory()->confirmed()->create([
            'parking_lot_id' => $lot->id,
            'license_plate'  => self::PLATE,
            'plate_province' => self::PROVINCE,
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now()->subMinute(),
        ]);

        $this->fakeAi(['confidence' => 85]);

        $this->scanAs($uploader, $lot)
            ->assertSessionHas('scan_check_in', fn ($v) => $v['success'] === false);

        $this->assertSame('low_accuracy', LicensePlateScan::firstOrFail()->result);
        $this->assertDatabaseCount('parking_logs', 0);
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $this->assertTrue($this->notified($owner, 'AI Accuracy ไม่ผ่านเกณฑ์'));
        $this->assertTrue($this->notified($admin, 'AI Accuracy ไม่ผ่านเกณฑ์'));
        $this->assertFalse($this->notified($uploader, 'AI Accuracy ไม่ผ่านเกณฑ์'));
    }

    // ─── [4] ไม่มีค่า Accuracy → ถือว่าไม่ผ่าน ─────────────────────────────

    public function test_missing_accuracy_is_not_treated_as_passing(): void
    {
        $this->makeUser('admin');
        $lot = $this->ownerLot($this->makeUser('owner'));
        $this->fakeAi(remove: ['confidence']);

        $this->scanAs($this->makeUser(), $lot);

        $scan = LicensePlateScan::firstOrFail();
        $this->assertNull($scan->confidence);
        $this->assertSame('low_accuracy', $scan->result);
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [5] AI อ่านทะเบียนไม่ได้ → บันทึก Scan + แจ้ง Owner + Admin · ไม่ Match ─

    public function test_unreadable_plate_is_logged_and_notifies_owner_and_admin(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = $this->ownerLot($owner);
        $this->fakeAi(['license_plate' => '', 'province' => '', 'confidence' => 30]);

        $this->scanAs($this->makeUser(), $lot)
            ->assertSessionHas('scan_check_in', fn ($v) => $v['success'] === false);

        $scan = LicensePlateScan::firstOrFail();
        $this->assertNull($scan->license_plate);
        $this->assertSame('unreadable', $scan->result);
        $this->assertDatabaseCount('parking_logs', 0);

        $this->assertTrue($this->notified($owner, 'AI อ่านทะเบียนไม่ได้'));
        $this->assertTrue($this->notified($admin, 'AI อ่านทะเบียนไม่ได้'));
    }

    // ─── [6] ลานของ Admin → แจ้ง Admin (ไม่มี Owner) ────────────────────────

    public function test_admin_lot_alerts_go_to_admins(): void
    {
        $adminA = $this->makeUser('admin');
        $adminB = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        $this->fakeAi(['confidence' => 40]);

        $this->scanAs($this->makeUser(), $lot);

        $this->assertTrue($this->notified($adminA, 'AI Accuracy ไม่ผ่านเกณฑ์'));
        $this->assertTrue($this->notified($adminB, 'AI Accuracy ไม่ผ่านเกณฑ์'));
    }

    // ─── [7] พบ Blacklist → Check-in ได้ + แจ้ง Admin + Owner ───────────────

    public function test_blacklisted_car_is_not_blocked_and_admin_and_owner_are_notified(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = $this->ownerLot($owner);
        SuspiciousVehicle::factory()->create(['license_plate' => self::PLATE, 'plate_province' => self::PROVINCE]);
        $this->fakeAi(['confidence' => 95]);

        $this->scanAs($this->makeUser(), $lot);

        $this->assertTrue(LicensePlateScan::firstOrFail()->is_suspicious);
        $this->assertTrue($this->notified($admin, '⚠ พบรถต้องสงสัย (Blacklist)'));
        $this->assertTrue($this->notified($owner, '⚠ พบรถต้องสงสัย (Blacklist)'));

        // ไม่ Block — รถยังเข้าจอดได้
        $this->assertDatabaseHas('parking_logs', ['license_plate' => self::PLATE, 'parking_lot_id' => $lot->id]);
    }

    // ─── [8] Blacklist ต้องตรงทั้งทะเบียนและจังหวัด ─────────────────────────

    public function test_blacklist_requires_matching_province(): void
    {
        $admin = $this->makeUser('admin');
        $lot = $this->ownerLot($this->makeUser('owner'));
        SuspiciousVehicle::factory()->create(['license_plate' => self::PLATE, 'plate_province' => 'เชียงใหม่']);
        $this->fakeAi(['confidence' => 95]);

        $this->scanAs($this->makeUser(), $lot);

        $this->assertFalse(LicensePlateScan::firstOrFail()->is_suspicious);
        $this->assertFalse($this->notified($admin, '⚠ พบรถต้องสงสัย (Blacklist)'));
    }

    // ─── [9] อัปโหลดจำลองกล้องได้ทุกลาน ไม่ขึ้นกับผู้อัปโหลด ─────────────────

    public function test_any_role_can_scan_for_any_lot(): void
    {
        $this->makeUser('admin');
        $otherOwnersLot = $this->ownerLot($this->makeUser('owner'), ['reservations_enabled' => false]);
        $this->fakeAi(['confidence' => 40]);

        $this->scanAs($this->makeUser('owner'), $otherOwnersLot)->assertSessionHasNoErrors();
        $this->scanAs($this->makeUser(), $otherOwnersLot)->assertSessionHasNoErrors();
        $this->scanAs($this->makeUser('admin'), $otherOwnersLot)->assertSessionHasNoErrors();

        $this->assertSame(3, LicensePlateScan::where('parking_lot_id', $otherOwnersLot->id)->count());
    }

    // ─── [10] AI API ล้มเหลว → แสดงข้อผิดพลาด ไม่มีข้อมูลเสียหาย ─────────────

    public function test_ai_failure_shows_error_without_creating_records(): void
    {
        $lot = $this->ownerLot($this->makeUser('owner'));
        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andThrow(new \RuntimeException('quota exceeded')));

        $this->scanAs($this->makeUser(), $lot)->assertSessionHasErrors('car_image');

        $this->assertDatabaseCount('license_plate_scans', 0);
        $this->assertSame(0, ParkingLog::count());
    }
}
