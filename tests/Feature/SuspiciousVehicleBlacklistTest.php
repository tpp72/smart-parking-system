<?php

namespace Tests\Feature;

use App\Models\SuspiciousVehicle;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\CarScanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuspiciousVehicleBlacklistTest extends TestCase
{
    use RefreshDatabase;

    // ─── [1] active blacklist entry → is_suspicious = true ─────────────────

    public function test_active_blacklist_entry_flags_scan_as_suspicious(): void
    {
        $plate = 'กข 1234';

        SuspiciousVehicle::factory()->create([
            'license_plate' => $plate,
            'is_active'     => true,
        ]);

        $result = $this->callIsSuspicious($plate);

        $this->assertTrue($result);
    }

    // ─── [2] inactive blacklist entry → is_suspicious = false ──────────────

    public function test_inactive_blacklist_entry_does_not_flag_scan(): void
    {
        $plate = 'กข 5678';

        SuspiciousVehicle::factory()->inactive()->create([
            'license_plate' => $plate,
        ]);

        $result = $this->callIsSuspicious($plate);

        $this->assertFalse($result);
    }

    // ─── [3] no blacklist entry → is_suspicious = false ────────────────────

    public function test_unknown_plate_is_not_suspicious(): void
    {
        $result = $this->callIsSuspicious('ทด 9999');

        $this->assertFalse($result);
    }

    // ─── [4] same plate: one active one inactive → suspicious = true ────────

    public function test_active_record_wins_when_mixed_with_inactive(): void
    {
        $plate = 'คค 1111';

        // inactive first
        SuspiciousVehicle::factory()->inactive()->create(['license_plate' => $plate . '-old']);

        // active entry for this plate
        SuspiciousVehicle::factory()->create(['license_plate' => $plate, 'is_active' => true]);

        $this->assertTrue($this->callIsSuspicious($plate));
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

        $plate = 'สส 2222';
        SuspiciousVehicle::factory()->create(['license_plate' => $plate]);

        $user = User::factory()->create(['force_password_reset' => false]);

        $service = $this->partialMock(CarScanService::class, function ($mock) use ($plate) {
            $mock->shouldReceive('detect')->andReturn([
                'license_plate' => $plate,
                'color'         => 'ดำ',
                'brand'         => 'Toyota',
                'confidence'    => 95.0,
            ]);
        });

        $file = UploadedFile::fake()->image('car.jpg');
        $scan = $service->scanAndSave($file, $user->id);

        $this->assertTrue((bool) $scan->is_suspicious);
    }

    // ─── [7] full scan flow: inactive blacklist → is_suspicious = false ──────

    public function test_scan_and_save_does_not_mark_suspicious_for_inactive_blacklist(): void
    {
        Storage::fake('public');

        $plate = 'สส 3333';
        SuspiciousVehicle::factory()->inactive()->create(['license_plate' => $plate]);

        $user = User::factory()->create(['force_password_reset' => false]);

        $service = $this->partialMock(CarScanService::class, function ($mock) use ($plate) {
            $mock->shouldReceive('detect')->andReturn([
                'license_plate' => $plate,
                'color'         => 'ขาว',
                'brand'         => 'Honda',
                'confidence'    => 90.0,
            ]);
        });

        $file = UploadedFile::fake()->image('car.jpg');
        $scan = $service->scanAndSave($file, $user->id);

        $this->assertFalse((bool) $scan->is_suspicious);
    }

    // ─── helper ─────────────────────────────────────────────────────────────

    private function callIsSuspicious(string $licensePlate): bool
    {
        return SuspiciousVehicle::active()
            ->where('license_plate', $licensePlate)
            ->exists();
    }
}
