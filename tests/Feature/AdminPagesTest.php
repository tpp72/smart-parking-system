<?php

namespace Tests\Feature;

use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\SuspiciousVehicle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 8 — หน้าของผู้ดูแลระบบ */
class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'force_password_reset' => false]);
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => 'owner', 'owner_status' => 'approved', 'force_password_reset' => false]);
    }

    public function test_dashboard_labels_every_number_with_scope_and_lists_every_lot(): void
    {
        $lots = ParkingLot::factory()->count(10)->create(['owner_id' => null]);
        $booking = Reservation::factory()->create(['parking_lot_id' => $lots[0]->id, 'status' => 'pending']);
        Payment::create(['type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $booking->id, 'hourly_rate' => 40, 'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID]);
        SuspiciousVehicle::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();

        // ไม่จำกัด 9 ลานแบบเดิม · มัดจำค้างนับรวม ไม่ผูกช่วงเวลา · งานของ Admin นับลานของ Admin
        $this->assertCount(10, $response->viewData('lotsOverview'));
        $this->assertSame(['count' => 1, 'amount' => 40.0], $response->viewData('stats')['unpaid_deposits']);
        $this->assertSame(1, $response->viewData('tasks')['payments']['count']);

        $response->assertSee('รวมทุกลาน (10 ลาน)')
            ->assertSee('งานที่รอผู้ดูแลระบบ')
            ->assertSee('ไม่ขึ้นกับช่วงเวลา')
            ->assertSee('ทั้งระบบ ไม่ขึ้นกับลานที่เลือก')
            ->assertSee('#dashboard-numbers', false)
            ->assertDontSee('Active Vehicles')
            ->assertDontSee('Unpaid Bills')
            ->assertDontSee('Live Parking');

        // ช่วงเวลาที่ไม่รู้จัก → วันนี้ · ลานที่ไม่มีอยู่ → ทุกลาน
        $fallback = $this->actingAs($this->admin)->get(route('admin.dashboard', ['range' => 'year', 'lot_id' => 99999]))->assertOk();
        $this->assertSame('today', $fallback->viewData('range'));
        $this->assertNull($fallback->viewData('lot'));
    }

    public function test_admin_reservations_share_owner_view_with_cancel_confirm_and_export(): void
    {
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        $booking = Reservation::factory()->confirmed()->create(['parking_lot_id' => $lot->id, 'reserve_start' => now()->addDay()]);

        $this->actingAs($this->admin)->get(route('admin.reservations.index'))->assertOk()
            ->assertViewIs('staff.reservations')
            ->assertSee('การจองในลานของผู้ดูแลระบบ')
            ->assertSee(route('admin.reservations.cancel', $booking), false)
            ->assertSee('data-confirm-title="ยกเลิกการจองนี้?"', false)
            ->assertSee(route('admin.exports.reservations'), false)
            ->assertDontSee('onsubmit="return confirm', false);

        $this->actingAs($this->admin)->get(route('admin.payments.index'))->assertOk()->assertViewIs('staff.payments');
        $this->actingAs($this->admin)->get(route('admin.parking-logs.index'))->assertOk()->assertViewIs('staff.parking-logs');
        $this->actingAs($this->admin)->get(route('admin.reservation-logs.index'))->assertOk()->assertViewIs('staff.reservation-logs');

        // Owner ใช้หน้าเดียวกันแต่ไม่มีปุ่มยกเลิกและ CSV
        $owner = $this->owner();
        ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner)->get(route('owner.reservations.index'))->assertOk()
            ->assertViewIs('staff.reservations')
            ->assertDontSee('ส่งออก CSV');
    }

    public function test_user_edit_shows_deletion_impact_and_protects_own_role(): void
    {
        $user = User::factory()->create(['role' => 'user', 'force_password_reset' => true]);
        Reservation::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk()
            ->assertSee('ต้องเปลี่ยนรหัสผ่าน')
            ->assertSee('ผู้ดูแลระบบ')
            ->assertDontSee('force reset');

        $this->actingAs($this->admin)->get(route('admin.users.edit', $user))->assertOk()
            ->assertViewHas('impact', ['bookings' => 2, 'parked' => 0])
            ->assertSee('data-confirm-title="ลบบัญชีถาวร?"', false)
            ->assertSee('สุ่มรหัส');

        // บัญชีตัวเอง: เปลี่ยนบทบาทและลบไม่ได้ (ส่ง role เดิมเป็น hidden)
        $this->actingAs($this->admin)->get(route('admin.users.edit', $this->admin))->assertOk()
            ->assertSee('<input type="hidden" name="role" value="admin">', false)
            ->assertSee('ลบบัญชีของตัวเองไม่ได้')
            ->assertDontSee('data-confirm-title="ลบบัญชีถาวร?"', false);
    }

    public function test_blacklist_is_thai_and_uses_confirm_dialogs(): void
    {
        SuspiciousVehicle::factory()->create(['license_plate' => 'กข 1234', 'level' => 'high', 'is_active' => true]);
        SuspiciousVehicle::factory()->create(['license_plate' => 'คง 5678', 'level' => 'low', 'is_active' => false]);

        $this->actingAs($this->admin)->get(route('admin.suspicious-vehicles.index'))->assertOk()
            ->assertSee('ความเสี่ยงสูง')
            ->assertSee('ระงับ')
            ->assertSee('เปิดใช้งาน')
            ->assertSee('data-confirm-title="ลบออกจากบัญชีดำ?"', false)
            ->assertDontSee('Suspicious Vehicle Blacklist')
            ->assertDontSee('onsubmit="return confirm', false);

        $this->actingAs($this->admin)->get(route('admin.suspicious-vehicles.create'))->assertOk()
            ->assertSee('ระดับความเสี่ยง')
            ->assertDontSee('Danger Zone')
            ->assertDontSee('(Medium)');
    }

    public function test_owner_application_review_and_resignation_impact(): void
    {
        $applicant = User::factory()->create(['role' => 'user']);
        $application = OwnerApplication::create([
            'user_id' => $applicant->id, 'applicant_type' => 'individual', 'business_name' => 'ลานคุณสมชาย', 'contact_name' => 'สมชาย',
            'phone' => '0800000000', 'email' => 'somchai@example.com', 'parking_lot_name' => 'ลานหน้าบ้าน', 'district' => 'บางรัก',
            'province' => 'กรุงเทพมหานคร', 'estimated_slots' => 12, 'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->get(route('admin.owner-applications.index'))->assertOk()
            ->assertViewHas('status', '')
            ->assertSee('ลานคุณสมชาย')
            ->assertSee('พิจารณา');

        $this->actingAs($this->admin)->get(route('admin.owner-applications.show', $application))->assertOk()
            ->assertSee('data-confirm-title="อนุมัติคำขอนี้?"', false)
            ->assertSee('เหตุผลที่ไม่อนุมัติ')
            ->assertSee('ไม่ได้แนบเอกสาร')
            ->assertDontSee('onclick="return confirm', false);

        $owner = $this->owner();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'confirmed']);
        OwnerResignation::create(['user_id' => $owner->id, 'reason' => 'ปิดกิจการ', 'status' => OwnerResignation::STATUS_PENDING]);

        $this->actingAs($this->admin)->get(route('admin.owner-resignations.index'))->assertOk()
            ->assertSee('ถ้าอนุมัติตอนนี้')
            ->assertSee('ลานจอดที่จะถูกลบ')
            ->assertSee('data-confirm-title="อนุมัติคำร้องลาออก?"', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_audit_log_shows_thai_action_labels_and_readable_meta(): void
    {
        $this->actingAs($this->admin)->post(route('admin.suspicious-vehicles.store'), [
            'license_plate' => 'กข 9999', 'plate_province' => 'กรุงเทพมหานคร', 'level' => 'high', 'is_active' => '1',
        ])->assertRedirect();

        $this->actingAs($this->admin)->get(route('admin.admin-actions.index'))->assertOk()
            ->assertSee('เพิ่มทะเบียนในบัญชีดำ')
            ->assertSee('suspicious_vehicle.create')          // รหัสเดิมยังแสดงตัวเล็ก
            ->assertSee('ระดับความเสี่ยง:')
            ->assertSee('สูง')
            ->assertSee('ระบบ')
            ->assertDontSee('{&quot;license_plate', false);

        $this->actingAs($this->admin)->get(route('admin.exports.index'))->assertOk()
            ->assertSee('ส่งออก CSV')
            ->assertSee('รายงานรายได้รายวัน')
            ->assertDontSee('Export CSV');
    }
}
