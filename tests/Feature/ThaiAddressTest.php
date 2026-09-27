<?php

namespace Tests\Feature;

use App\Models\OwnerApplication;
use App\Models\ParkingLot;
use App\Models\User;
use App\Support\ThaiGeography;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ที่อยู่ของลานจอดเลือกเป็นชั้น จังหวัด → อำเภอ/เขต → ตำบล/แขวง
 * รหัสไปรษณีย์มาจากชุดข้อมูลฝั่งเซิร์ฟเวอร์ ไม่รับค่าที่ส่งมาจากเบราว์เซอร์
 */
class ThaiAddressTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role' => $role, 'force_password_reset' => false, 'email_verified_at' => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function application(array $overrides = []): array
    {
        return array_merge([
            'applicant_type'   => 'individual',
            'contact_name'     => 'ผู้สมัคร',
            'phone'            => '0800000000',
            'email'            => 'apply@example.com',
            'parking_lot_name' => 'ลานใหม่',
            'address'          => '123 ถ.สีลม',
            'province'         => 'กรุงเทพมหานคร',
            'district'         => 'บางรัก',
            'subdistrict'      => 'สีลม',
            'estimated_slots'  => 20,
        ], $overrides);
    }

    // ─── ชุดข้อมูล ─────────────────────────────────────────────────────────

    public function test_geography_data_is_complete_and_nested(): void
    {
        $this->assertFileExists(public_path(ThaiGeography::PATH));
        $this->assertCount(77, ThaiGeography::provinces());
        $this->assertContains('กรุงเทพมหานคร', ThaiGeography::provinces());
        $this->assertContains('บางรัก', ThaiGeography::districts('กรุงเทพมหานคร'));
        $this->assertContains('สีลม', ThaiGeography::subdistricts('กรุงเทพมหานคร', 'บางรัก'));
        $this->assertSame('10500', ThaiGeography::postalCode('กรุงเทพมหานคร', 'บางรัก', 'สีลม'));

        // จังหวัดในชุดข้อมูลต้องเป็นชุดเดียวกับรายการที่ใช้กับป้ายทะเบียน (ลำดับต่างกันได้)
        $this->assertEqualsCanonicalizing(config('thai_provinces'), ThaiGeography::provinces());
    }

    public function test_unknown_combinations_have_no_postal_code(): void
    {
        $this->assertNull(ThaiGeography::postalCode('กรุงเทพมหานคร', 'บางรัก', 'ไม่มีตำบลนี้'));
        $this->assertNull(ThaiGeography::postalCode('กรุงเทพมหานคร', 'ไม่มีอำเภอนี้', 'สีลม'));
        $this->assertNull(ThaiGeography::postalCode('ไม่มีจังหวัดนี้', 'บางรัก', 'สีลม'));
        $this->assertNull(ThaiGeography::postalCode(null, null, null));
        $this->assertFalse(ThaiGeography::isValidCombination('เชียงใหม่', 'บางรัก', 'สีลม'));
    }

    // ─── คำขอเปิดลานจอด ────────────────────────────────────────────────────

    public function test_application_stores_the_postal_code_from_the_dataset(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('owner.application.store'), $this->application())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('owner_applications', [
            'province'    => 'กรุงเทพมหานคร',
            'district'    => 'บางรัก',
            'subdistrict' => 'สีลม',
            'postal_code' => '10500',
        ]);
    }

    /** ส่งรหัสไปรษณีย์ปลอมมาจากเบราว์เซอร์ ต้องไม่ถูกใช้ */
    public function test_postal_code_sent_by_the_browser_is_ignored(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('owner.application.store'), $this->application(['postal_code' => '99999']))
            ->assertSessionHasNoErrors();

        $this->assertSame('10500', OwnerApplication::first()->postal_code);
    }

    public function test_address_across_different_provinces_is_rejected(): void
    {
        $user = $this->makeUser();

        // อำเภออยู่คนละจังหวัด
        $this->actingAs($user)->post(route('owner.application.store'),
            $this->application(['province' => 'เชียงใหม่']))
            ->assertSessionHasErrors('subdistrict');

        // ตำบลอยู่คนละอำเภอ
        $this->actingAs($user)->post(route('owner.application.store'),
            $this->application(['subdistrict' => 'ลุมพินี']))
            ->assertSessionHasErrors('subdistrict');

        // จังหวัดไม่มีอยู่จริง
        $this->actingAs($user)->post(route('owner.application.store'),
            $this->application(['province' => 'จังหวัดสมมติ']))
            ->assertSessionHasErrors(['province', 'subdistrict']);

        $this->assertSame(0, OwnerApplication::count());
    }

    public function test_application_requires_the_full_address(): void
    {
        $user = $this->makeUser();

        $payload = $this->application();
        unset($payload['subdistrict']);

        $this->actingAs($user)->post(route('owner.application.store'), $payload)
            ->assertSessionHasErrors('subdistrict');
    }

    // ─── ลานจอด (ที่อยู่ไม่บังคับ) ──────────────────────────────────────────

    public function test_lot_address_is_optional_but_must_be_complete_when_given(): void
    {
        $owner = $this->makeUser('owner');

        $base = ['name' => 'ลานทดสอบ', 'total_slots' => 10, 'hourly_rate' => 20];

        // ไม่กรอกที่อยู่เลย — ผ่าน
        $this->actingAs($owner)->post(route('owner.parking-lots.store'), $base)
            ->assertSessionHasNoErrors();

        // กรอกจังหวัดอย่างเดียว — ต้องกรอกให้ครบ
        $this->actingAs($owner)->post(route('owner.parking-lots.store'),
            $base + ['province' => 'กรุงเทพมหานคร'])
            ->assertSessionHasErrors(['district', 'subdistrict']);

        // ครบทั้ง 3 ชั้น — ได้รหัสไปรษณีย์อัตโนมัติ
        $this->actingAs($owner)->post(route('owner.parking-lots.store'),
            $base + ['province' => 'กรุงเทพมหานคร', 'district' => 'บางรัก', 'subdistrict' => 'สีลม'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('parking_lots', ['subdistrict' => 'สีลม', 'postal_code' => '10500']);
        $this->assertSame(2, ParkingLot::count());
    }

    public function test_admin_lot_address_follows_the_same_rules(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.parking-lots.store'), [
            'name' => 'ลานผู้ดูแล', 'total_slots' => 5, 'hourly_rate' => 15,
            'province' => 'ภูเก็ต', 'district' => 'เมืองภูเก็ต', 'subdistrict' => 'ตลาดใหญ่',
        ])->assertSessionHasNoErrors();

        $lot = ParkingLot::first();
        $this->assertSame('ตลาดใหญ่', $lot->subdistrict);
        $this->assertSame(ThaiGeography::postalCode('ภูเก็ต', 'เมืองภูเก็ต', 'ตลาดใหญ่'), $lot->postal_code);
    }

    // ─── หน้าจอ ────────────────────────────────────────────────────────────

    public function test_forms_render_the_cascading_address_fields(): void
    {
        $owner = $this->makeUser('owner');

        $this->actingAs($owner)->get(route('owner.parking-lots.create'))->assertOk()
            ->assertSee('อำเภอ / เขต')
            ->assertSee('ตำบล / แขวง')
            ->assertSee('รหัสไปรษณีย์')
            ->assertSee('spAddressSelect', false)
            ->assertSee('thai-geography.json', false);

        $this->actingAs($this->makeUser())->get(route('owner.application.create'))->assertOk()
            ->assertSee('ตำบล / แขวง')
            ->assertSee('รหัสไปรษณีย์');
    }

    public function test_saved_address_shows_with_subdistrict_and_postal_code(): void
    {
        $owner = $this->makeUser('owner');
        ParkingLot::factory()->create([
            'owner_id' => $owner->id, 'name' => 'ลานสีลม',
            'address' => '123 ถ.สีลม', 'subdistrict' => 'สีลม',
            'district' => 'บางรัก', 'province' => 'กรุงเทพมหานคร', 'postal_code' => '10500',
        ]);

        $this->actingAs($owner)->get(route('owner.parking-lots.index'))->assertOk()
            ->assertSee('123 ถ.สีลม สีลม บางรัก กรุงเทพมหานคร 10500');
    }
}
