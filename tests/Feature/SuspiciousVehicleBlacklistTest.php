<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Services\CarScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Blacklist ตรวจด้วย ทะเบียน + จังหวัด */
class SuspiciousVehicleBlacklistTest extends TestCase
{
    use RefreshDatabase;

    private const PROVINCE = 'กรุงเทพมหานคร';

    // ─── [1] active blacklist entry → is_suspicious = true ─────────────────

    public function test_active_blacklist_entry_flags_scan_as_suspicious(): void
    {
        SuspiciousVehicle::factory()->create([
            'license_plate'  => 'กข 1234',
            'plate_province' => self::PROVINCE,
            'is_active'      => true,
        ]);

        $this->assertTrue($this->callIsSuspicious('กข 1234', self::PROVINCE));
    }

    // ─── [2] inactive blacklist entry → is_suspicious = false ──────────────

    public function test_inactive_blacklist_entry_does_not_flag_scan(): void
    {
        SuspiciousVehicle::factory()->inactive()->create([
            'license_plate'  => 'กข 5678',
            'plate_province' => self::PROVINCE,
        ]);

        $this->assertFalse($this->callIsSuspicious('กข 5678', self::PROVINCE));
    }

    // ─── [3] no blacklist entry → is_suspicious = false ────────────────────

    public function test_unknown_plate_is_not_suspicious(): void
    {
        $this->assertFalse($this->callIsSuspicious('ทด 9999', self::PROVINCE));
    }

    // ─── [4] same plate, different province → not suspicious ───────────────

    public function test_same_plate_in_different_province_is_not_suspicious(): void
    {
        SuspiciousVehicle::factory()->create([
            'license_plate'  => 'คค 1111',
            'plate_province' => 'เชียงใหม่',
        ]);

        $this->assertFalse($this->callIsSuspicious('คค 1111', self::PROVINCE));
    }

    // ─── [5] scopeActive() excludes inactive records ────────────────────────

    public function test_scope_active_excludes_inactive_records(): void
    {
        SuspiciousVehicle::factory()->count(3)->create();
        SuspiciousVehicle::factory()->count(2)->inactive()->create();

        $this->assertSame(3, SuspiciousVehicle::active()->count());
    }

    // ─── [6] full scan flow: scanAndSave stores correct is_suspicious ────────

    public function test_scan_and_save_marks_is_suspicious_correctly_for_active_blacklist(): void
    {
        Storage::fake('public');

        SuspiciousVehicle::factory()->create(['license_plate' => 'สส 2222', 'plate_province' => self::PROVINCE]);

        $scan = $this->scan('สส 2222', self::PROVINCE);

        $this->assertTrue((bool) $scan->is_suspicious);
        $this->assertSame(self::PROVINCE, $scan->plate_province);
    }

    // ─── [7] full scan flow: inactive blacklist → is_suspicious = false ──────

    public function test_scan_and_save_does_not_mark_suspicious_for_inactive_blacklist(): void
    {
        Storage::fake('public');

        SuspiciousVehicle::factory()->inactive()->create(['license_plate' => 'สส 3333', 'plate_province' => self::PROVINCE]);

        $scan = $this->scan('สส 3333', self::PROVINCE);

        $this->assertFalse((bool) $scan->is_suspicious);
    }

    // ─── [8] AI อ่านทะเบียนไม่ได้ → บันทึก Scan ได้ (license_plate = null) ──

    public function test_scan_and_save_stores_unreadable_plate_as_null(): void
    {
        Storage::fake('public');

        $scan = $this->scan('', '');

        $this->assertNull($scan->license_plate);
        $this->assertNull($scan->plate_province);
        $this->assertSame('unreadable', $scan->result);
        $this->assertFalse((bool) $scan->is_suspicious);
        $this->assertDatabaseHas('license_plate_scans', ['id' => $scan->id, 'license_plate' => null]);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function scan(string $plate, string $province)
    {
        $user = User::factory()->create(['force_password_reset' => false]);
        $lot  = ParkingLot::factory()->create();

        $service = $this->partialMock(CarScanService::class, function ($mock) use ($plate, $province) {
            $mock->shouldReceive('detect')->andReturn([
                'license_plate' => $plate,
                'province'      => $province,
                'color'         => 'ดำ',
                'brand'         => 'Toyota',
                'confidence'    => 95.0,
            ]);
        });

        return $service->scanAndSave(UploadedFile::fake()->image('car.jpg'), $user->id, $lot->id);
    }

    private function callIsSuspicious(string $licensePlate, string $province): bool
    {
        return SuspiciousVehicle::active()
            ->where('license_plate', $licensePlate)
            ->where('plate_province', $province)
            ->exists();
    }
}
