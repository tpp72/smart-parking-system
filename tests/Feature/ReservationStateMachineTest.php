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

/** State machine ของ Reservation + การยกเลิกโดย Admin + ห้าม Hard Delete */
class ReservationStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function bookOn(ParkingLot $lot, string $plate = 'กข 1111'): Reservation
    {
        return app(ReservationService::class)->create($this->makeUser(), $lot, [
            'license_plate'  => $plate,
            'plate_province' => 'ชลบุรี',
            'brand'          => 'Honda',
            'color'          => 'ดำ',
            'reserve_start'  => now()->addHours(3),
        ]);
    }

    // ─── [1] Transition ที่อนุญาต ─────────────────────────────────────────

    public function test_allowed_transitions_follow_the_state_machine(): void
    {
        $expected = [
            'pending'    => ['confirmed' => true, 'cancelled' => true, 'expired' => true, 'checked_in' => false, 'completed' => false],
            'confirmed'  => ['checked_in' => true, 'cancelled' => true, 'expired' => true, 'pending' => false, 'completed' => false],
            'checked_in' => ['completed' => true, 'cancelled' => false, 'expired' => false, 'confirmed' => false],
            'completed'  => ['cancelled' => false, 'pending' => false],
            'cancelled'  => ['confirmed' => false, 'pending' => false],
            'expired'    => ['confirmed' => false, 'checked_in' => false],
        ];

        foreach ($expected as $from => $targets) {
            $reservation = new Reservation(['status' => $from]);
            foreach ($targets as $to => $allowed) {
                $this->assertSame($allowed, $reservation->canTransitionTo($to), "{$from} → {$to}");
            }
        }
    }

    // ─── [2] Deposit = hourly_rate × 1 ────────────────────────────────────

    public function test_deposit_equals_one_hour_of_lot_rate(): void
    {
        $lot = ParkingLot::factory()->create(['hourly_rate' => 35]);

        $this->assertSame(35.0, Reservation::depositFor($lot));
    }

    // ─── [3] Admin ยกเลิก pending → Deposit void ──────────────────────────

    public function test_admin_can_cancel_pending_reservation_and_voids_unpaid_deposit(): void
    {
        $admin = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create();
        $reservation = $this->bookOn($lot);

        $this->actingAs($admin)
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertSame(Payment::STATUS_VOID, $reservation->depositPayment->fresh()->payment_status);
        $this->assertDatabaseHas('notifications', ['user_id' => $reservation->user_id, 'title' => 'การจองถูกยกเลิก']);
        $this->assertDatabaseHas('admin_actions', ['action' => 'reservation.cancel', 'actor_role' => 'admin']);
    }

    // ─── [4] Admin ยกเลิก confirmed → คืน Slot, Deposit ไม่คืน ─────────────

    public function test_admin_cancel_confirmed_releases_slot_and_keeps_paid_deposit(): void
    {
        $admin = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->bookOn($lot);

        app(ReservationService::class)->markDepositPaid($reservation->depositPayment, $admin);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'reserved']);

        $this->actingAs($admin)->post(route('admin.reservations.cancel', $reservation));

        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
        $this->assertSame(Payment::STATUS_PAID, $reservation->depositPayment->fresh()->payment_status);
    }

    // ─── [5] ยกเลิกหลัง Check-in ไม่ได้ ───────────────────────────────────

    public function test_admin_cannot_cancel_checked_in_reservation(): void
    {
        $lot = ParkingLot::factory()->create();
        $reservation = Reservation::factory()->checkedIn()->create(['parking_lot_id' => $lot->id]);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertSessionHasErrors('error');

        $this->assertSame('checked_in', $reservation->fresh()->status);
    }

    // ─── [6] Admin ยกเลิกการจองในลานของ Owner ไม่ได้ ─────────────────────────

    public function test_admin_cannot_cancel_reservation_of_owner_lot(): void
    {
        $lot = ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id]);
        $reservation = $this->bookOn($lot);

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertForbidden();

        $this->assertSame('pending', $reservation->fresh()->status);
    }

    // ─── [7] ห้าม Hard Delete Reservation ─────────────────────────────────

    public function test_reservation_cannot_be_hard_deleted_through_the_web(): void
    {
        $lot = ParkingLot::factory()->create();
        $reservation = $this->bookOn($lot);

        $response = $this->actingAs($this->makeUser('admin'))
            ->delete('/admin/reservations/' . $reservation->id);

        $this->assertContains($response->status(), [404, 405]);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
        $this->assertFalse(Route::has('admin.reservations.destroy'));
    }

    // ─── [8] Auto Check-in ไม่รับรถที่มาก่อนเวลาจอง (Manual Check-in รับได้) ─

    public function test_reservation_is_not_checkable_before_reserve_start(): void
    {
        $early = Reservation::factory()->confirmed()->create(['reserve_start' => now()->addMinutes(3)]);
        $onTime = Reservation::factory()->confirmed()->create(['reserve_start' => now()->subMinute()]);

        $checkable = Reservation::checkable()->pluck('id');

        $this->assertFalse($checkable->contains($early->id));
        $this->assertTrue($checkable->contains($onTime->id));
    }
}
