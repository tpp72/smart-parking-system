<?php

namespace Tests\Feature;

use App\Models\LicensePlateScan;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** UI Phase 5 — AI Scan: หน้าสแกน (ผลที่ประตูลาน) + ประวัติสแกนที่รวม Admin/Owner */
class ScanPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(array_merge(
            ['role' => $role, 'force_password_reset' => false, 'email_verified_at' => now()],
            $role === 'owner' ? ['owner_status' => 'approved'] : [],
        ));
    }

    private function scanRecord(ParkingLot $lot, array $attrs = []): LicensePlateScan
    {
        return LicensePlateScan::create(array_merge([
            'user_id' => null, 'parking_lot_id' => $lot->id, 'license_plate' => 'กข 1234', 'plate_province' => 'กรุงเทพมหานคร',
            'brand' => 'Toyota', 'color' => 'ขาว', 'confidence' => 96, 'result' => LicensePlateScan::RESULT_PASSED,
            'is_suspicious' => false, 'source' => 'manual_upload', 'scan_time' => now(),
        ], $attrs));
    }

    public function test_scan_page_explains_the_gate_rules_in_thai(): void
    {
        ParkingLot::factory()->create();

        $this->actingAs($this->makeUser('user'))->get(route('user.scan.create'))->assertOk()
            ->assertSee('ระบบตัดสินใจที่ประตูลานอย่างไร')
            ->assertSee('แม่นยำเกิน 85%')
            ->assertSee('วิเคราะห์รูปรถ')
            ->assertDontSee('Car Detection')
            ->assertDontSee('ประวัติสแกน'); // User ไม่มีหน้าประวัติสแกน
    }

    public function test_walk_in_result_shows_gate_outcome_ai_reading_and_keeps_the_camera_lot(): void
    {
        config(['carscan.fake' => true, 'carscan.anthropic_api_key' => '']);
        Storage::fake('public');
        $admin = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available', 'slot_number' => 'A07']);

        $this->actingAs($admin)->from(route('admin.scan.create'))->followingRedirects()->post(route('admin.scan.store'), [
            'parking_lot_id' => $lot->id,
            'car_image' => UploadedFile::fake()->image('วอ 5555__กรุงเทพมหานคร__Honda__ดำ__96.png'),
        ])->assertOk()
            ->assertSee('เช็คอินอัตโนมัติสำเร็จ (Walk-in)')
            ->assertSee('ระบบจัดสรรช่อง')
            ->assertSee('A07')
            ->assertSee('ค่าที่ AI อ่านได้')
            ->assertSee('96.0%')
            ->assertSee('เกินเกณฑ์')
            ->assertSee('สแกนคันถัดไป')
            ->assertSee('<option value="'.$lot->id.'" selected', false);
    }

    public function test_low_accuracy_result_says_why_nothing_happened(): void
    {
        config(['carscan.fake' => true, 'carscan.anthropic_api_key' => '']);
        Storage::fake('public');
        $lot = ParkingLot::factory()->create(['owner_id' => null]);

        $this->actingAs($this->makeUser('admin'))->from(route('admin.scan.create'))->followingRedirects()->post(route('admin.scan.store'), [
            'parking_lot_id' => $lot->id,
            'car_image' => UploadedFile::fake()->image('ทด 1111__กรุงเทพมหานคร__Honda__ดำ__60.png'),
        ])->assertOk()
            ->assertSee('AI ไม่ผ่านเกณฑ์ความแม่นยำ')
            ->assertSee('ไม่เกินเกณฑ์')
            ->assertSee('แจ้งเจ้าของลานและผู้ดูแลระบบแล้ว')
            ->assertDontSee('Owner และ Admin');
    }

    public function test_history_is_one_view_for_both_roles_and_filters_blacklist_and_result(): void
    {
        $owner = $this->makeUser('owner');
        $ownerLot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $adminLot = ParkingLot::factory()->create(['owner_id' => null]);

        $clean = $this->scanRecord($adminLot, ['license_plate' => 'กข 1001']);
        $flagged = $this->scanRecord($adminLot, ['license_plate' => 'กข 1002', 'is_suspicious' => true]);
        $unreadable = $this->scanRecord($adminLot, ['license_plate' => '', 'result' => LicensePlateScan::RESULT_UNREADABLE]);
        $mine = $this->scanRecord($ownerLot, ['license_plate' => 'ขค 2002']);

        $admin = $this->makeUser('admin');
        $all = $this->actingAs($admin)->get(route('admin.scan.history'))->assertOk()->assertViewIs('scan.history')
            ->assertSee('ลานของผู้ดูแลระบบ')->assertSee('พบในบัญชีดำ')->assertDontSee('Scan History');
        $this->assertEqualsCanonicalizing([$clean->id, $flagged->id, $unreadable->id], $all->viewData('scans')->pluck('id')->all());

        $blacklist = $this->actingAs($admin)->get(route('admin.scan.history', ['result' => 'blacklist']));
        $this->assertSame([$flagged->id], $blacklist->viewData('scans')->pluck('id')->all());

        $failed = $this->actingAs($admin)->get(route('admin.scan.history', ['result' => 'unreadable']));
        $this->assertSame([$unreadable->id], $failed->viewData('scans')->pluck('id')->all());

        $ownerView = $this->actingAs($owner)->get(route('owner.scan.history'))->assertOk()->assertViewIs('scan.history')->assertSee('ลานของคุณ');
        $this->assertSame([$mine->id], $ownerView->viewData('scans')->pluck('id')->all());
    }
}
