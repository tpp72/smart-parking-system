<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingLog;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    // ─── helpers ────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create([
            'role'                 => 'admin',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function regularUser(): User
    {
        return User::factory()->create([
            'role'                 => 'user',
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    private function pendingReservation(User $user): Reservation
    {
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        return app(ReservationService::class)->create($user, $lot, [
            'license_plate'  => 'กข 3333',
            'plate_province' => 'ภูเก็ต',
            'brand'          => 'Mazda',
            'color'          => 'แดง',
            'reserve_start'  => now()->addHour(),
        ]);
    }

    // ─── [1] ยืนยันรับเงินมัดจำ → แจ้ง User ────────────────────────────────

    public function test_deposit_mark_paid_sends_confirmation_notification_to_user(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        $reservation = $this->pendingReservation($user);

        $this->actingAs($admin)
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertRedirect();

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('การจองได้รับการยืนยัน', $notification->title);
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
    }

    // ─── [2] Admin ยกเลิก → แจ้ง User ─────────────────────────────────────

    public function test_admin_cancel_sends_notification_to_user(): void
    {
        $admin       = $this->admin();
        $user        = $this->regularUser();
        $reservation = $this->pendingReservation($user);

        $this->actingAs($admin)
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertRedirect();

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification, 'Cancellation notification was not sent');
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
    }

    // ─── [3] check-in sends notification ────────────────────────────────────

    public function test_check_in_sends_notification_when_reservation_exists(): void
    {
        $admin = $this->admin();
        $user  = $this->regularUser();
        $lot   = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        // Reservation within check-in window
        $reservation = Reservation::factory()->create([
            'user_id'        => $user->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subMinutes(5),
            'status'         => 'confirmed',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reservations.check-in', $reservation))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
    }

    // ─── [4] check-out sends notification to reservation owner ──────────────

    public function test_check_out_sends_notification_to_reservation_owner(): void
    {
        $admin = $this->admin();
        $user  = $this->regularUser();
        $lot   = ParkingLot::factory()->create(['hourly_rate' => 40]);
        $slot  = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);

        $reservation = Reservation::factory()->checkedIn()->create([
            'user_id'        => $user->id,
            'parking_lot_id' => $lot->id,
        ]);

        $log = ParkingLog::factory()->create([
            'license_plate'   => $reservation->license_plate,
            'plate_province'  => $reservation->plate_province,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reservation_id'  => $reservation->id,
            'check_in_time'   => now()->subHours(2),
            'check_out_time'  => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.parking-logs.check-out', $log))
            ->assertRedirect();

        $notification = Notification::where('user_id', $user->id)->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString($reservation->license_plate, $notification->message);
    }

    // ─── [5] expiry command sends notification ───────────────────────────────

    public function test_expire_command_sends_notification(): void
    {
        $user = $this->regularUser();
        $lot  = ParkingLot::factory()->create();

        Reservation::factory()->create([
            'user_id'        => $user->id,
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->subHours(2),
            'status'         => 'confirmed',
        ]);

        $this->artisan('reservations:expire')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
        ]);
    }
}
