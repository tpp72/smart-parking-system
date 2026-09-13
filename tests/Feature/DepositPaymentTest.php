<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Deposit Payment → Admin/Owner Mark as Paid → Reservation confirmed + Lock Slot */
class DepositPaymentTest extends TestCase
{
    use RefreshDatabase;

    private int $plateSeq = 1000;

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    /** จองผ่าน Flow จริง → Reservation (pending) + Deposit Payment (unpaid) */
    private function book(ParkingLot $lot, ?User $user = null): Reservation
    {
        return app(ReservationService::class)->create($user ?? $this->makeUser(), $lot, [
            'license_plate'  => 'กข ' . $this->plateSeq++,
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now()->addHours(2),
        ]);
    }

    private function lotWithSlot(?User $owner = null, float $rate = 40): array
    {
        $lot  = ParkingLot::factory()->create(['owner_id' => $owner?->id, 'hourly_rate' => $rate]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        return [$lot, $slot];
    }

    // ─── [1] Admin ยืนยันรับเงินมัดจำ → confirmed + Lock Slot ───────────────

    public function test_admin_mark_paid_confirms_reservation_and_locks_slot(): void
    {
        $admin = $this->makeUser('admin');
        [$lot, $slot] = $this->lotWithSlot(rate: 40);

        $reservation = $this->book($lot);
        $payment = $reservation->depositPayment;

        $this->assertSame(Payment::STATUS_UNPAID, $payment->payment_status);
        $this->assertEquals(40, (float) $payment->total_amount);
        $this->assertNull($reservation->parking_slot_id);

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->payment_status);
        $this->assertSame($admin->id, $payment->paid_by);
        $this->assertNotNull($payment->paid_at);

        $reservation->refresh();
        $this->assertSame('confirmed', $reservation->status);
        $this->assertSame($slot->id, $reservation->parking_slot_id);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'reserved']);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'pending',
            'new_status'     => 'confirmed',
            'changed_by'     => $admin->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reservation->user_id,
            'title'   => 'การจองได้รับการยืนยัน',
        ]);
        $this->assertDatabaseHas('admin_actions', [
            'action'     => 'payment.mark_paid',
            'actor_id'   => $admin->id,
            'actor_role' => 'admin',
        ]);
    }

    // ─── [2] Owner ยืนยันรับเงินมัดจำของลานตัวเอง ──────────────────────────

    public function test_owner_can_mark_paid_deposit_of_own_lot(): void
    {
        $owner = $this->makeUser('owner');
        [$lot, $slot] = $this->lotWithSlot($owner);

        $reservation = $this->book($lot);

        $this->actingAs($owner)
            ->post(route('owner.payments.mark-paid', $reservation->depositPayment))
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame($owner->id, $reservation->depositPayment->fresh()->paid_by);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'reserved']);
    }

    // ─── [3] Owner ยืนยันรับเงินของลาน Owner อื่นไม่ได้ ─────────────────────

    public function test_owner_cannot_mark_paid_deposit_of_another_owners_lot(): void
    {
        [$lot] = $this->lotWithSlot($this->makeUser('owner'));
        $reservation = $this->book($lot);

        $this->actingAs($this->makeUser('owner'))
            ->post(route('owner.payments.mark-paid', $reservation->depositPayment))
            ->assertForbidden();

        $this->assertSame(Payment::STATUS_UNPAID, $reservation->depositPayment->fresh()->payment_status);
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    // ─── [4] Admin ยืนยันรับเงินของลาน Owner ไม่ได้ ──────────────────────────

    public function test_admin_cannot_mark_paid_deposit_of_owner_lot(): void
    {
        [$lot] = $this->lotWithSlot($this->makeUser('owner'));
        $reservation = $this->book($lot);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertForbidden();

        $this->assertSame('pending', $reservation->fresh()->status);
    }

    // ─── [5] ป้องกันการยืนยันรับเงินซ้ำ ─────────────────────────────────────

    public function test_deposit_cannot_be_marked_paid_twice(): void
    {
        $admin = $this->makeUser('admin');
        [$lot] = $this->lotWithSlot();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $reservation = $this->book($lot);
        $payment = $reservation->depositPayment;

        $this->actingAs($admin)->post(route('admin.payments.mark-paid', $payment))->assertSessionHas('success');
        $firstPaidAt = $payment->fresh()->paid_at;

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $payment))
            ->assertSessionHasErrors('error');

        $this->assertEquals($firstPaidAt, $payment->fresh()->paid_at);
        $this->assertSame(1, ParkingSlot::where('status', 'reserved')->count());
    }

    // ─── [6] Deposit ที่ void ยืนยันรับเงินไม่ได้ ───────────────────────────

    public function test_void_deposit_cannot_be_marked_paid(): void
    {
        $user = $this->makeUser();
        [$lot] = $this->lotWithSlot();
        $reservation = $this->book($lot, $user);

        $this->actingAs($user)->post(route('user.reservations.cancel', $reservation));
        $this->assertSame(Payment::STATUS_VOID, $reservation->depositPayment->fresh()->payment_status);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertSessionHasErrors('error');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame(Payment::STATUS_VOID, $reservation->depositPayment->fresh()->payment_status);
    }

    // ─── [7] ลานเต็มขณะยืนยันรับเงิน → cancelled + void + แจ้งเตือน ─────────

    public function test_mark_paid_when_lot_is_full_cancels_reservation_and_voids_deposit(): void
    {
        $admin = $this->makeUser('admin');
        [$lot, $slot] = $this->lotWithSlot();

        $reservation = $this->book($lot);
        $slot->update(['status' => 'occupied']); // ลานเต็มก่อนยืนยันรับเงิน

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertSessionHasErrors('error');

        $payment = $reservation->depositPayment->fresh();
        $this->assertSame(Payment::STATUS_VOID, $payment->payment_status);
        $this->assertNull($payment->paid_by);
        $this->assertNull($payment->paid_at);

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'occupied']);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'pending',
            'new_status'     => 'cancelled',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reservation->user_id,
            'title'   => 'การจองถูกยกเลิก',
        ]);
    }

    // ─── [8] ยืนยันรับเงินของ Reservation ที่ไม่ใช่ pending ไม่ได้ ───────────

    public function test_mark_paid_rejected_when_reservation_is_no_longer_pending(): void
    {
        [$lot] = $this->lotWithSlot();
        $reservation = $this->book($lot);
        $reservation->update(['status' => 'expired']);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertSessionHasErrors('error');

        $this->assertSame(Payment::STATUS_UNPAID, $reservation->depositPayment->fresh()->payment_status);
        $this->assertSame('expired', $reservation->fresh()->status);
    }

    // ─── [9] Payments page แสดง Deposit ของลานในขอบเขตเท่านั้น ──────────────

    public function test_payments_page_lists_deposits_within_scope_only(): void
    {
        $owner = $this->makeUser('owner');
        [$ownLot] = $this->lotWithSlot($owner);
        [$otherLot] = $this->lotWithSlot($this->makeUser('owner'));

        $own = $this->book($ownLot);
        $other = $this->book($otherLot);

        $ids = $this->actingAs($owner)
            ->get(route('owner.payments.index', ['status' => 'unpaid']))
            ->assertOk()
            ->viewData('payments')
            ->pluck('id');

        $this->assertTrue($ids->contains($own->depositPayment->id));
        $this->assertFalse($ids->contains($other->depositPayment->id));
    }

    // ─── [10] ไม่มี Manual Confirm / Admin สร้าง-แก้ไข-ลบ Reservation ───────

    public function test_legacy_reservation_routes_are_removed(): void
    {
        foreach ([
            'admin.reservations.confirm',
            'owner.reservations.confirm',
            'admin.reservations.create',
            'admin.reservations.store',
            'admin.reservations.edit',
            'admin.reservations.update',
            'admin.reservations.destroy',
        ] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should not exist");
        }
    }
}
