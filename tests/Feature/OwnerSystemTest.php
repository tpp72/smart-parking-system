<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Notification;
use App\Models\OwnerResignation;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Phase 11 — Owner System: คำร้องลาออก, การลบลาน, รายได้ (project-plan.md §16, §16.1) */
class OwnerSystemTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function lotOf(User $owner, array $attrs = []): ParkingLot
    {
        return ParkingLot::factory()->create(array_merge(['owner_id' => $owner->id, 'hourly_rate' => 40], $attrs));
    }

    private function parkedIn(ParkingLot $lot, User $booker): Reservation
    {
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        $reservation = Reservation::factory()->checkedIn()->create([
            'user_id'         => $booker->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
        ]);

        ParkingLog::factory()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => $reservation->id,
            'license_plate'   => $reservation->license_plate,
            'plate_province'  => $reservation->plate_province,
            'check_in_time'   => now()->subHours(2),
            'check_out_time'  => null,
        ]);

        return $reservation;
    }

    private function submitResignation(User $owner, string $reason = 'ปิดกิจการลานจอด'): OwnerResignation
    {
        $this->actingAs($owner)->post(route('owner.resignation.store'), ['reason' => $reason])
            ->assertRedirect(route('owner.dashboard'));

        return OwnerResignation::where('user_id', $owner->id)->latest('id')->firstOrFail();
    }

    // ─── คำร้องลาออก: ยื่น ─────────────────────────────────────────────────────

    public function test_owner_submits_resignation_and_stays_owner_until_approved(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = $this->lotOf($owner);

        $resignation = $this->submitResignation($owner);

        $this->assertSame(OwnerResignation::STATUS_PENDING, $resignation->status);
        $this->assertSame('owner', $owner->fresh()->role);
        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);
        $this->assertTrue(Notification::where('user_id', $admin->id)->where('title', 'คำร้องลาออกของ Owner')->exists());
        $this->assertTrue(AdminAction::where('action', 'owner_resignation.submit')->where('actor_id', $owner->id)->where('actor_role', 'owner')->exists());

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk()->assertSee('คำร้องลาออกรอการพิจารณา');
    }

    public function test_owner_cannot_submit_second_pending_resignation_or_empty_reason(): void
    {
        $owner = $this->makeUser('owner');
        $this->submitResignation($owner);

        $this->actingAs($owner)->post(route('owner.resignation.store'), ['reason' => 'อีกครั้ง'])->assertSessionHasErrors('reason');
        $this->actingAs($owner)->post(route('owner.resignation.store'), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->assertSame(1, OwnerResignation::count());
    }

    public function test_non_owner_cannot_submit_and_legacy_self_demotion_is_removed(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->post(route('owner.resignation.store'), ['reason' => 'ไม่ใช่ Owner'])->assertForbidden();

        $this->assertSame(0, OwnerResignation::count());
        $this->assertFalse(Route::has('owner.demote-self'));
        $this->assertSame('user', $user->fresh()->role);
    }

    // ─── คำร้องลาออก: อนุมัติ ─────────────────────────────────────────────────

    public function test_admin_approval_cancels_bookings_checks_out_cars_deletes_lots_and_demotes_owner(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $booker = $this->makeUser('user');
        $lot = $this->lotOf($owner);

        $otherOwner = $this->makeUser('owner');
        $otherLot = $this->lotOf($otherOwner);
        $otherBooking = Reservation::factory()->confirmed()->create(['parking_lot_id' => $otherLot->id]);

        $pending = Reservation::factory()->create(['user_id' => $booker->id, 'parking_lot_id' => $lot->id, 'deposit_amount' => 40]);
        $deposit = Payment::create([
            'type'           => Payment::TYPE_DEPOSIT,
            'reservation_id' => $pending->id,
            'hourly_rate'    => 40,
            'total_amount'   => 40,
            'payment_status' => Payment::STATUS_UNPAID,
        ]);
        $confirmedSlot = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $lot->id]);
        $confirmed = Reservation::factory()->confirmed()->create([
            'user_id'         => $booker->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $confirmedSlot->id,
        ]);
        $parked = $this->parkedIn($lot, $booker);

        $resignation = $this->submitResignation($owner);

        $this->actingAs($admin)->get(route('admin.owner-resignations.index'))->assertOk()->assertSee($owner->name);

        $this->actingAs($admin)->post(route('admin.owner-resignations.approve', $resignation))
            ->assertRedirect(route('admin.owner-resignations.index'))
            ->assertSessionHas('success');

        $resignation->refresh();
        $this->assertSame(OwnerResignation::STATUS_APPROVED, $resignation->status);
        $this->assertSame($admin->id, $resignation->reviewed_by);
        $this->assertSame(['reservations_cancelled' => 2, 'cars_checked_out' => 1, 'lots_deleted' => 1], $resignation->result);

        $owner->refresh();
        $this->assertSame('user', $owner->role);
        $this->assertNull($owner->owner_status);

        // ลานและข้อมูลที่ผูกกับลานถูกลบ
        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);
        foreach ([$pending, $confirmed, $parked] as $reservation) {
            $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        }
        $this->assertDatabaseMissing('payments', ['id' => $deposit->id]);

        // ลานของ Owner อื่นไม่ได้รับผลกระทบ
        $this->assertDatabaseHas('parking_lots', ['id' => $otherLot->id]);
        $this->assertSame('confirmed', $otherBooking->fresh()->status);

        // ผู้จองได้รับแจ้งให้ติดต่อ Admin (ไม่มีสรุปยอดชำระของลานที่ถูกลบ)
        $messages = Notification::where('user_id', $booker->id)->pluck('message', 'title');
        $this->assertStringContainsString('กรุณาติดต่อ Admin', $messages['การจองถูกยกเลิก']);
        $this->assertStringContainsString('กรุณาติดต่อ Admin', $messages['รถของคุณถูกเช็คเอาท์โดยระบบ']);
        $this->assertFalse(Notification::where('user_id', $booker->id)->where('title', 'เช็คเอาท์เรียบร้อย')->exists());
        $this->assertTrue(Notification::where('user_id', $owner->id)->where('title', 'คำร้องลาออกได้รับการอนุมัติ')->exists());

        $this->assertTrue(AdminAction::where('action', 'owner_resignation.approve')->where('actor_id', $admin->id)->exists());
        $this->assertSame(2, AdminAction::where('action', 'reservation.cancel')->count());
        $this->assertTrue(AdminAction::where('action', 'parking_lot.delete')->exists());

        // อนุมัติซ้ำไม่ได้
        $this->actingAs($admin)->post(route('admin.owner-resignations.approve', $resignation))->assertSessionHasErrors('error');
    }

    // ─── คำร้องลาออก: ไม่อนุมัติ ───────────────────────────────────────────────

    public function test_admin_rejection_requires_reason_and_keeps_owner(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = $this->lotOf($owner);
        $resignation = $this->submitResignation($owner);

        $this->actingAs($admin)->post(route('admin.owner-resignations.reject', $resignation), ['rejection_reason' => 'สั้น'])
            ->assertSessionHasErrors('rejection_reason');
        $this->assertTrue($resignation->fresh()->isPending());

        $this->actingAs($admin)->post(route('admin.owner-resignations.reject', $resignation), ['rejection_reason' => 'ยังมีสัญญาเช่าลานกับลูกค้าอยู่'])
            ->assertSessionHas('success');

        $resignation->refresh();
        $this->assertSame(OwnerResignation::STATUS_REJECTED, $resignation->status);
        $this->assertSame('ยังมีสัญญาเช่าลานกับลูกค้าอยู่', $resignation->rejection_reason);
        $this->assertSame('owner', $owner->fresh()->role);
        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);
        $this->assertTrue(Notification::where('user_id', $owner->id)->where('title', 'คำร้องลาออกไม่ได้รับการอนุมัติ')->exists());
        $this->assertTrue(AdminAction::where('action', 'owner_resignation.reject')->exists());

        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk()->assertSee('ยังมีสัญญาเช่าลานกับลูกค้าอยู่');

        // ยื่นใหม่ได้หลังถูกปฏิเสธ
        $this->submitResignation($owner, 'ยื่นคำร้องใหม่');
        $this->assertSame(1, OwnerResignation::pending()->count());
    }

    public function test_only_admin_can_review_resignations(): void
    {
        $owner = $this->makeUser('owner');
        $resignation = $this->submitResignation($owner);

        $this->actingAs($owner)->get(route('admin.owner-resignations.index'))->assertRedirect(route('owner.dashboard'));
        $this->actingAs($owner)->post(route('admin.owner-resignations.approve', $resignation))->assertRedirect(route('owner.dashboard'));
        $this->actingAs($this->makeUser('user'))->post(route('admin.owner-resignations.approve', $resignation))->assertForbidden();

        $this->assertTrue($resignation->fresh()->isPending());
        $this->assertSame('owner', $owner->fresh()->role);
    }

    // ─── ลบลานจอด ─────────────────────────────────────────────────────────────

    public function test_owner_cannot_delete_lot_with_active_reservations(): void
    {
        $owner = $this->makeUser('owner');

        foreach (['pending', 'confirmed', 'checked_in'] as $status) {
            $lot = $this->lotOf($owner);
            Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => $status]);

            $this->actingAs($owner)->delete(route('owner.parking-lots.destroy', $lot))->assertSessionHasErrors('error');
            $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);
        }

        $finished = $this->lotOf($owner);
        Reservation::factory()->cancelled()->create(['parking_lot_id' => $finished->id]);
        Reservation::factory()->expired()->create(['parking_lot_id' => $finished->id]);

        $this->actingAs($owner)->delete(route('owner.parking-lots.destroy', $finished))->assertSessionHas('success');
        $this->assertDatabaseMissing('parking_lots', ['id' => $finished->id]);
    }

    // ─── รายได้ = เงินที่รับจริง ────────────────────────────────────────────────

    public function test_owner_revenue_counts_paid_deposits_and_checkouts_by_paid_at(): void
    {
        $owner = $this->makeUser('owner');
        $lot = $this->lotOf($owner);
        $otherLot = $this->lotOf($this->makeUser('owner'));

        $deposit = function (ParkingLot $lot, float $amount, string $status, $paidAt = null) {
            $reservation = Reservation::factory()->confirmed()->create(['parking_lot_id' => $lot->id]);
            Payment::create([
                'type'           => Payment::TYPE_DEPOSIT,
                'reservation_id' => $reservation->id,
                'hourly_rate'    => 40,
                'total_amount'   => $amount,
                'payment_status' => $status,
                'paid_at'        => $paidAt,
            ]);
        };

        $checkout = function (ParkingLot $lot, float $amount, string $status, $paidAt = null, $createdAt = null) {
            $reservation = Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'completed']);
            $log = ParkingLog::factory()->create([
                'parking_lot_id' => $lot->id,
                'reservation_id' => $reservation->id,
                'license_plate'  => $reservation->license_plate,
                'plate_province' => $reservation->plate_province,
                'check_in_time'  => now()->subHours(3),
                'check_out_time' => now()->subHour(),
            ]);
            $payment = Payment::create([
                'type'           => Payment::TYPE_CHECKOUT,
                'reservation_id' => $reservation->id,
                'parking_log_id' => $log->id,
                'hourly_rate'    => 40,
                'total_amount'   => $amount,
                'payment_status' => $status,
                'paid_at'        => $paidAt,
            ]);
            if ($createdAt) {
                $payment->forceFill(['created_at' => $createdAt])->save();
            }
        };

        $deposit($lot, 40, Payment::STATUS_PAID, now());
        $deposit($lot, 40, Payment::STATUS_VOID);
        $deposit($lot, 40, Payment::STATUS_UNPAID);
        $checkout($lot, 80, Payment::STATUS_PAID, now(), now()->subMonths(2));   // สร้างก่อน แต่รับเงินวันนี้
        $checkout($lot, 0, Payment::STATUS_PAID, now());                         // ยอด 0 ระบบปิดเอง — ไม่ใช่เงินที่รับ
        $checkout($lot, 30, Payment::STATUS_UNPAID);
        $checkout($lot, 500, Payment::STATUS_PAID, now()->subYears(2));          // นอกช่วงเวลา
        $deposit($otherLot, 999, Payment::STATUS_PAID, now());                   // ลานของ Owner อื่น

        $this->actingAs($owner)->get(route('owner.revenue.index', ['period' => 'today']))
            ->assertOk()
            ->assertViewHas('revenueTotal', 120.0)
            ->assertViewHas('depositRevenue', 40.0)
            ->assertViewHas('parkingRevenue', 80.0)
            ->assertViewHas('transactionCount', 2)
            ->assertViewHas('unpaidTotal', 30.0);

        $this->actingAs($owner)->get(route('owner.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['revenue_today'] === 120.0 && $stats['revenue_month'] === 120.0)
            ->assertViewHas('chartRevenueTrend', fn ($chart) => end($chart['datasets'][0]['data']) === 120.0);
    }
}
