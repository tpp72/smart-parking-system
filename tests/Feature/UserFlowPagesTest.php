<?php

namespace Tests\Feature;

use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 4 — User flow: หน้าหลัก / จอง / การจองของฉัน / แก้ไขข้อมูลรถ / ประวัติการจอด */
class UserFlowPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private ParkingLot $lot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'user', 'force_password_reset' => false]);
        $this->lot = ParkingLot::factory()->create(['owner_id' => null, 'hourly_rate' => 40, 'reservations_enabled' => true]);
    }

    private function booking(string $status, array $attrs = []): Reservation
    {
        $slot = in_array($status, ['confirmed', 'checked_in'], true)
            ? ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => $status === 'confirmed' ? 'reserved' : 'occupied'])
            : null;

        $reservation = Reservation::factory()->create(array_merge([
            'user_id' => $this->user->id, 'parking_lot_id' => $this->lot->id, 'parking_slot_id' => $slot?->id,
            'status' => $status, 'deposit_amount' => 40, 'reservation_fee' => 40,
        ], $attrs));

        Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $reservation->id, 'hourly_rate' => 40, 'total_amount' => 40,
            'payment_status' => $status === 'pending' ? Payment::STATUS_UNPAID : Payment::STATUS_PAID,
            'paid_at' => $status === 'pending' ? null : now()->subHours(4),
        ]);

        if ($status === 'checked_in') {
            ParkingLog::factory()->create([
                'parking_lot_id' => $this->lot->id, 'parking_slot_id' => $slot->id, 'reservation_id' => $reservation->id,
                'license_plate' => $reservation->license_plate, 'plate_province' => $reservation->plate_province,
                'check_in_time' => now()->subMinutes(150), 'check_out_time' => null, 'hourly_rate' => 40,
            ]);
        }

        return $reservation;
    }

    public function test_dashboard_shows_every_unfinished_booking_as_a_ticket_in_thai(): void
    {
        $pending = $this->booking('pending', ['reserve_start' => now()->addHours(3)]);
        $confirmed = $this->booking('confirmed', ['reserve_start' => now()->addMinutes(30)]);
        $parked = $this->booking('checked_in', ['reserve_start' => now()->subHours(3)]);
        $this->booking('completed');

        $response = $this->actingAs($this->user)->get(route('user.dashboard'))->assertOk();

        // กำลังจอดขึ้นก่อน แล้วเรียงตามเวลาเริ่มจอง
        $this->assertSame([$parked->id, $confirmed->id, $pending->id], $response->viewData('tickets')->pluck('id')->all());

        $response->assertSee('การจองที่ยังไม่จบ')
            ->assertSee('รอเจ้าหน้าที่ยืนยันรับเงิน')   // pending (มุมมองผู้ใช้)
            ->assertSee('รับมัดจำแล้ว')                  // ตรามัดจำของใบที่ยืนยันแล้ว
            ->assertSee('ยอดที่ต้องชำระถ้าออกตอนนี้')   // 150 นาที → 3 ชม. × 40 = 120 − มัดจำ 40 − ส่วนลด 40
            ->assertSee('฿40.00', false)
            ->assertDontSee('>checked_in<', false)
            ->assertDontSee('>pending<', false)
            ->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_reservations_page_splits_active_and_finished_tabs(): void
    {
        $active = $this->booking('confirmed');
        $done = $this->booking('cancelled');

        $activeTab = $this->actingAs($this->user)->get(route('user.reservations.index'))->assertOk();
        $this->assertSame([$active->id], $activeTab->viewData('reservations')->pluck('id')->all());
        $this->assertSame(['active' => 1, 'done' => 1], $activeTab->viewData('counts'));
        $activeTab->assertSee('data-confirm-tone="danger"', false)
            ->assertSee('มัดจำจะไม่ได้รับคืน');

        $doneTab = $this->actingAs($this->user)->get(route('user.reservations.index', ['tab' => 'done']))->assertOk();
        $this->assertSame([$done->id], $doneTab->viewData('reservations')->pluck('id')->all());
        $doneTab->assertSee('ยกเลิก')->assertDontSee(route('user.reservations.edit', $done));
    }

    public function test_booking_form_shows_deposit_and_discount_rules_and_caps_one_day_ahead(): void
    {
        ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => 'available']);

        $this->actingAs($this->user)->get(route('user.reservations.create'))->assertOk()
            ->assertSee('สรุปการจอง')
            ->assertSee('ส่วนลดการจอง')
            ->assertSee('data-max="'.now()->addDay()->subMinute()->format('Y-m-d\TH:i').'"', false)
            ->assertDontSee('(Parking Lot)')
            ->assertDontSee('(Reserve Start)')
            ->assertDontSee('tracking-widest');
    }

    public function test_duplicate_plate_error_is_shown_under_the_plate_field(): void
    {
        $existing = $this->booking('pending');
        ParkingSlot::factory()->create(['parking_lot_id' => $this->lot->id, 'status' => 'available']);

        $this->actingAs($this->user)->from(route('user.reservations.create'))->post(route('user.reservations.store'), [
            'plate_number' => $existing->license_plate, 'plate_province' => $existing->plate_province,
            'brand' => 'Toyota', 'color' => config('car_colors')[0],
            'parking_lot_id' => $this->lot->id, 'reserve_start' => now()->addHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('license_plate');

        $this->actingAs($this->user)->get(route('user.reservations.create'))
            ->assertSee('id="plate_number-error"', false)
            ->assertSee('ป้ายทะเบียนนี้มีการจองที่ยังดำเนินการอยู่');
    }

    public function test_editing_after_check_in_explains_in_thai_without_raw_status(): void
    {
        $parked = $this->booking('checked_in');

        $this->actingAs($this->user)->get(route('user.reservations.edit', $parked))
            ->assertRedirect(route('user.reservations.index'))
            ->assertSessionHasErrors(['error' => 'แก้ไขข้อมูลรถได้เฉพาะก่อน Check-in — การจองนี้เช็คอินแล้ว']);
    }

    public function test_parking_history_is_a_thai_receipt(): void
    {
        $reservation = $this->booking('completed');
        $log = ParkingLog::factory()->create([
            'parking_lot_id' => $this->lot->id, 'reservation_id' => $reservation->id,
            'license_plate' => $reservation->license_plate, 'plate_province' => $reservation->plate_province,
            'check_in_time' => now()->subHours(3), 'check_out_time' => now()->subMinutes(20), 'hourly_rate' => 40,
        ]);
        Payment::create([
            'type' => Payment::TYPE_CHECKOUT, 'reservation_id' => $reservation->id, 'parking_log_id' => $log->id,
            'total_hours' => 3, 'hourly_rate' => 40, 'parking_fee' => 120, 'deposit_deduction' => 40,
            'reservation_discount' => 40, 'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID,
        ]);

        $this->actingAs($this->user)->get(route('user.parking-logs.index'))->assertOk()
            ->assertSee('หักมัดจำ')
            ->assertSee('ส่วนลดการจอง')
            ->assertSee('ยอดชำระ')
            ->assertSee('รอเจ้าหน้าที่ยืนยันรับเงิน')
            ->assertDontSee('Parking History')
            ->assertDontSee('>active<', false);
    }
}
