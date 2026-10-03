<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CarScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Phase 15 — โหมดจำลอง AI Scan สำหรับ E2E (project-plan.md §25.2) */
class CarScanFakeModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fake_mode_reads_detection_from_filename(): void
    {
        $this->assertSame([
            'license_plate' => 'กข 1234',
            'province'      => 'กรุงเทพมหานคร',
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 72.5,
        ], CarScanService::fakeDetect('กข 1234__กรุงเทพมหานคร__Toyota__ขาว__72.5.png'));

        $unreadable = CarScanService::fakeDetect('____Honda.jpg');
        $this->assertSame('', $unreadable['license_plate']);
        $this->assertSame('', $unreadable['province']);
        $this->assertSame(95.0, $unreadable['confidence']);
    }

    /**
     * เปิดได้เฉพาะ APP_ENV=testing — เว็บ demo ใช้ local และเก็บข้อมูลประเมินผล ต้องเรียก AI จริงเสมอ
     * ต่อให้มีคนเผลอตั้ง CARSCAN_FAKE=true ก็ตาม
     */
    public function test_fake_mode_is_off_by_default_and_only_works_while_testing(): void
    {
        $this->assertFalse(CarScanService::fakeEnabled());

        config(['carscan.fake' => true]);
        $this->assertTrue(CarScanService::fakeEnabled());

        foreach (['local', 'production', 'staging'] as $env) {
            $this->app['env'] = $env;
            $this->assertFalse(CarScanService::fakeEnabled(), "โหมดจำลองเปิดได้ใน APP_ENV={$env}");
        }
    }

    public function test_scan_in_fake_mode_runs_the_real_pipeline_without_calling_claude(): void
    {
        config(['carscan.fake' => true, 'carscan.anthropic_api_key' => '']);
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin', 'force_password_reset' => false, 'email_verified_at' => now()]);
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $this->actingAs($admin)->post(route('admin.scan.store'), [
            'parking_lot_id' => $lot->id,
            'car_image'      => UploadedFile::fake()->image('วอ 5555__กรุงเทพมหานคร__Honda__ดำ__96.png'),
        ])->assertSessionHas('scan_check_in', fn ($result) => $result['success'] && $result['outcome'] === 'walk_in');

        $scan = LicensePlateScan::latest('id')->firstOrFail();
        $this->assertSame(['วอ 5555', 'กรุงเทพมหานคร', 'Honda', 'ดำ', LicensePlateScan::RESULT_PASSED],
            [$scan->license_plate, $scan->plate_province, $scan->brand, $scan->color, $scan->result]);

        $walkIn = Reservation::where('license_plate', 'วอ 5555')->firstOrFail();
        $this->assertTrue($walkIn->is_walk_in);
        $this->assertSame('checked_in', $walkIn->status);
        $this->assertSame('occupied', $slot->fresh()->status);
    }
}
