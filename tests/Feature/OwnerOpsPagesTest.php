<?php

namespace Tests\Feature;

use App\Models\OwnerApplication;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 6 — หน้างานเจ้าของลาน + คำขอเป็นเจ้าของลาน */
class OwnerOpsPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['role' => 'owner', 'owner_status' => 'approved', 'force_password_reset' => false]);
        $this->lot = ParkingLot::factory()->create(['owner_id' => $this->owner->id, 'hourly_rate' => 40]);
    }

    /** รถจอดอยู่ 150 นาที ที่อัตรา 40 · มัดจำ 40 ชำระแล้ว · ส่วนลด 40 → ค่าจอด 120 − 40 − 40 = 40 */
    private function parkedBooking(): Reservation
    {
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => 'occupied']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'parking_lot_id' => $this->lot->id, 'parking_slot_id' => $slot->id,
            'deposit_amount' => 40, 'reservation_fee' => 40, 'reserve_start' => now()->subHours(3),
        ]);
        Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $reservation->id, 'hourly_rate' => 40,
            'total_amount' => 40, 'payment_status' => Payment::STATUS_PAID, 'paid_at' => now()->subHours(4),
        ]);
        ParkingLog::factory()->create([
            'parking_lot_id' => $this->lot->id, 'parking_slot_id' => $slot->id, 'reservation_id' => $reservation->id,
            'license_plate' => $reservation->license_plate, 'plate_province' => $reservation->plate_province,
            'check_in_time' => now()->subMinutes(150), 'check_out_time' => null, 'hourly_rate' => 40,
        ]);

        return $reservation;
    }

    public function test_dashboard_leads_with_work_to_do_and_has_no_english_chart_labels(): void
    {
        $pending = Reservation::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => 'pending', 'reserve_start' => now()->addHours(2)]);
        Payment::create(['type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $pending->id, 'hourly_rate' => 40, 'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID]);
        $this->parkedBooking();

        $this->actingAs($this->owner)->get(route('owner.dashboard'))->assertOk()
            ->assertViewHas('tasks', fn ($tasks) => $tasks['deposits']['count'] === 1 && $tasks['deposits']['amount'] === 40.0)
            ->assertSee('ยืนยันรับเงินมัดจำ')
            ->assertSee('ลานของคุณตอนนี้')
            ->assertSee('รถที่จอดอยู่')
            ->assertSee('กำลังจะมาถึง')
            ->assertDontSee('Owner Dashboard')
            ->assertDontSee('Analytics')
            ->assertDontSee('cdn.jsdelivr.net/npm/chart.js', false);
    }

    public function test_manual_checkout_estimate_deducts_deposit_and_discount(): void
    {
        $reservation = $this->parkedBooking();

        $response = $this->actingAs($this->owner)->get(route('owner.reservations.index'))->assertOk();
        $estimate = $response->viewData('estimates')[$reservation->id];
        $this->assertSame([3, 120.0, 40.0, 40.0, 40.0],
            [$estimate['total_hours'], $estimate['parking_fee'], $estimate['deposit_deduction'], $estimate['reservation_discount'], $estimate['total_amount']]);
        $response->assertSee('checkout-confirm')->assertSee('ยอดที่ต้องเก็บ (ประมาณ)')
            ->assertSee('<option value="checked_in"', false)
            ->assertDontSee('>checked_in<', false);

        $logs = $this->actingAs($this->owner)->get(route('owner.parking-logs.index'))->assertOk();
        $this->assertSame(40.0, $logs->viewData('estimates')->first()['total_amount']);
    }

    public function test_payments_use_the_system_confirm_dialog(): void
    {
        $reservation = Reservation::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => 'pending']);
        Payment::create(['type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $reservation->id, 'hourly_rate' => 40, 'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID]);

        $this->actingAs($this->owner)->get(route('owner.payments.index'))->assertOk()
            ->assertSee('data-payment-type="deposit"', false)
            ->assertSee('data-confirm-title="ยืนยันรับเงินมัดจำ?"', false)
            ->assertDontSee('onsubmit="return confirm', false)
            ->assertDontSee('Payments —');
    }

    public function test_reservation_log_shows_thai_statuses_and_role(): void
    {
        $reservation = Reservation::factory()->confirmed()->create(['parking_lot_id' => $this->lot->id]);
        ReservationLog::create(['reservation_id' => $reservation->id, 'old_status' => 'pending', 'new_status' => 'confirmed', 'changed_by' => $this->owner->id, 'note' => 'ยืนยันรับเงินมัดจำ']);

        $this->actingAs($this->owner)->get(route('owner.reservation-logs.index'))->assertOk()
            ->assertSee('ยืนยันแล้ว')
            ->assertSee('เจ้าของลาน')
            ->assertDontSee('>confirmed<', false)
            ->assertDontSee('Reservation Log ของลาน');
    }

    public function test_user_submitting_an_application_lands_on_the_status_page_not_a_forbidden_owner_page(): void
    {
        $user = User::factory()->create(['role' => 'user', 'force_password_reset' => false]);

        $this->actingAs($user)->post(route('owner.application.store'), [
            'applicant_type' => 'individual', 'contact_name' => 'ผู้สมัคร', 'phone' => '0800000000', 'email' => 'apply@example.com',
            'parking_lot_name' => 'ลานใหม่', 'district' => 'บางรัก', 'province' => 'กรุงเทพมหานคร', 'estimated_slots' => 20,
        ])->assertRedirect(route('owner.application.show'));

        $this->actingAs($user)->get(route('owner.application.show'))->assertOk()
            ->assertSee('รอพิจารณา')
            ->assertSee('ผู้ดูแลระบบพิจารณา');
    }

    public function test_rejected_application_can_be_resubmitted_from_the_edit_form(): void
    {
        $user = User::factory()->create(['role' => 'user', 'owner_status' => 'rejected', 'force_password_reset' => false]);
        OwnerApplication::create([
            'user_id' => $user->id, 'applicant_type' => 'company', 'business_name' => 'บริษัท ก จำกัด', 'contact_name' => 'ผู้สมัคร',
            'phone' => '0800000000', 'email' => 'apply@example.com', 'parking_lot_name' => 'ลานเก่า', 'district' => 'บางรัก',
            'province' => 'กรุงเทพมหานคร', 'estimated_slots' => 20, 'status' => 'rejected', 'rejection_reason' => 'เอกสารไม่ครบ',
        ]);

        // ฟอร์มแก้ไขต้องส่งประเภทผู้สมัคร (เดิมไม่มีช่องนี้ ทำให้ส่งใหม่ไม่ผ่าน validation)
        $this->actingAs($user)->get(route('owner.application.edit'))->assertOk()
            ->assertSee('name="applicant_type" value="company"', false)
            ->assertSee('เอกสารไม่ครบ');

        $this->actingAs($user)->put(route('owner.application.update'), [
            'applicant_type' => 'company', 'business_name' => 'บริษัท ก จำกัด', 'contact_name' => 'ผู้สมัคร', 'phone' => '0800000000',
            'email' => 'apply@example.com', 'parking_lot_name' => 'ลานเก่า', 'district' => 'บางรัก', 'province' => 'กรุงเทพมหานคร', 'estimated_slots' => 25,
        ])->assertSessionHasNoErrors()->assertRedirect(route('owner.application.show'));

        $this->assertSame('pending', OwnerApplication::where('user_id', $user->id)->value('status'));
    }
}
