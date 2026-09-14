<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Reservation Expiration — ไม่ Check-in ภายใน 1 ชั่วโมงหลัง reserve_start → expired (project-plan.md §7.2, §23) */
class ExpireReservationsTest extends TestCase
{
    use RefreshDatabase;

    private const ONE_HOUR = 3600;

    private int $plateSeq = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        // ตรึงเวลาที่ต้นนาที เพื่อทดสอบขอบเวลาได้ละเอียดระดับวินาที
        $this->travelTo(now()->startOfMinute());
    }

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ]);
    }

    /** จองผ่าน Flow จริง เวลาจอง = ตอนนี้ (pending + Deposit unpaid) · $confirm = ยืนยันรับเงินและ Lock Slot */
    private function book(bool $confirm = false): Reservation
    {
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'available']);

        $reservation = app(ReservationService::class)->create($this->makeUser(), $lot, [
            'license_plate'  => 'กข ' . $this->plateSeq++,
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now(),
        ]);

        if ($confirm) {
            app(ReservationService::class)->markDepositPaid($reservation->depositPayment, $this->makeUser('admin'));
        }

        return $reservation->fresh();
    }

    private function expire(): void
    {
        $this->artisan('reservations:expire')->assertSuccessful();
    }

    // ─── [1] ช่วง Check-in = 1 ชั่วโมง ──────────────────────────────────────

    public function test_check_in_window_is_one_hour(): void
    {
        $this->assertSame(60, Reservation::gracePeriodMinutes());
    }

    // ─── [2] ขอบเวลา: ครบ 60 นาทีพอดียังไม่หมดอายุ และยังเช็คอินได้ ─────────

    public function test_reservation_is_not_expired_at_exactly_one_hour(): void
    {
        $reservation = $this->book(confirm: true);

        $this->travel(self::ONE_HOUR)->seconds();
        $this->expire();

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertTrue(Reservation::checkable()->whereKey($reservation->id)->exists());
        $this->assertFalse(Reservation::overdue()->whereKey($reservation->id)->exists());
    }

    // ─── [3] ขอบเวลา: เกิน 60 นาที 1 วินาที → expired และเช็คอินไม่ได้ ─────

    public function test_reservation_expires_one_second_after_one_hour(): void
    {
        $reservation = $this->book(confirm: true);

        $this->travel(self::ONE_HOUR + 1)->seconds();

        $this->assertFalse(Reservation::checkable()->whereKey($reservation->id)->exists());
        $this->assertTrue(Reservation::overdue()->whereKey($reservation->id)->exists());

        $this->expire();

        $this->assertSame('expired', $reservation->fresh()->status);
    }

    // ─── [4] การจองที่ยังไม่ถึงเวลา / เพิ่งเริ่ม ไม่หมดอายุ ─────────────────────

    public function test_future_and_recent_reservations_are_not_expired(): void
    {
        $recent = $this->book();
        $future = Reservation::factory()->confirmed()->create(['reserve_start' => now()->addHours(3)]);

        $this->travel(59)->minutes();
        $this->expire();

        $this->assertSame('pending', $recent->fresh()->status);
        $this->assertSame('confirmed', $future->fresh()->status);
    }

    // ─── [5] confirmed หมดอายุ → คืน Slot · มัดจำที่ชำระแล้วไม่คืน · Log · แจ้ง User ─

    public function test_confirmed_expiry_releases_locked_slot_and_keeps_paid_deposit(): void
    {
        $reservation = $this->book(confirm: true);
        $slotId = $reservation->parking_slot_id;
        $this->assertDatabaseHas('parking_slots', ['id' => $slotId, 'status' => 'reserved']);

        $this->travel(self::ONE_HOUR + 1)->seconds();
        $this->expire();

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertDatabaseHas('parking_slots', ['id' => $slotId, 'status' => 'available']);
        $this->assertSame(Payment::STATUS_PAID, $reservation->depositPayment->fresh()->payment_status);

        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'confirmed',
            'new_status'     => 'expired',
            'changed_by'     => null,
        ]);

        $notification = Notification::where('user_id', $reservation->user_id)->where('title', 'การจองหมดอายุ')->firstOrFail();
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
        $this->assertStringContainsString('ไม่คืนเงินมัดจำ', $notification->message);
    }

    // ─── [6] pending หมดอายุ → Deposit ที่ยังไม่ชำระเป็น void ───────────────────

    public function test_pending_expiry_voids_unpaid_deposit(): void
    {
        $reservation = $this->book();

        $this->travel(self::ONE_HOUR + 1)->seconds();
        $this->expire();

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(Payment::STATUS_VOID, $reservation->depositPayment->fresh()->payment_status);
        $this->assertDatabaseHas('reservation_logs', [
            'reservation_id' => $reservation->id,
            'old_status'     => 'pending',
            'new_status'     => 'expired',
        ]);

        $notification = Notification::where('user_id', $reservation->user_id)->where('title', 'การจองหมดอายุ')->firstOrFail();
        $this->assertStringContainsString('ยกเลิกรายการเงินมัดจำ', $notification->message);
    }

    // ─── [7] ช่องที่ occupied ไม่ถูกคืน ──────────────────────────────────────

    public function test_occupied_slot_is_never_released(): void
    {
        $lot  = ParkingLot::factory()->create();
        $slot = ParkingSlot::factory()->occupied()->create(['parking_lot_id' => $lot->id]);

        Reservation::factory()->confirmed()->create([
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
            'reserve_start'   => now()->subHours(2),
        ]);

        $this->expire();

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'occupied']);
    }

    // ─── [8] สถานะที่ไม่ใช่ pending / confirmed และ Walk-in ไม่ถูกแตะ ─────────

    public function test_non_expirable_reservations_are_untouched(): void
    {
        $past = ['reserve_start' => now()->subHours(2)];

        $reservations = collect(['checked_in', 'completed', 'cancelled', 'expired'])
            ->map(fn (string $status) => Reservation::factory()->create($past + ['status' => $status]))
            ->push(Reservation::factory()->walkIn()->create($past));

        $this->expire();

        foreach ($reservations as $reservation) {
            $this->assertSame($reservation->status, $reservation->fresh()->status);
        }
        $this->assertDatabaseCount('reservation_logs', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    // ─── [9] การจองที่หมดอายุแล้วเช็คอินไม่ได้ ───────────────────────────────

    public function test_expired_reservation_cannot_be_checked_in(): void
    {
        $reservation = $this->book(confirm: true);

        $this->travel(self::ONE_HOUR + 1)->seconds();
        $this->expire();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.reservations.check-in', $reservation))
            ->assertSessionHasErrors('error');

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertDatabaseCount('parking_logs', 0);
    }

    // ─── [10] Mark as Paid หลังเลยช่วง Check-in (Scheduler ยังไม่ทำงาน) → Expire แทน ─

    public function test_mark_paid_after_check_in_window_expires_instead_of_confirming(): void
    {
        $reservation = $this->book();
        $this->travel(self::ONE_HOUR + 1)->seconds();

        $this->actingAs($this->makeUser('admin'))
            ->post(route('admin.payments.mark-paid', $reservation->depositPayment))
            ->assertSessionHasErrors('error');

        $reservation->refresh();
        $this->assertSame('expired', $reservation->status);
        $this->assertNull($reservation->parking_slot_id);
        $this->assertSame(0, ParkingSlot::where('status', 'reserved')->count());

        $payment = $reservation->depositPayment->fresh();
        $this->assertSame(Payment::STATUS_VOID, $payment->payment_status);
        $this->assertNull($payment->paid_by);

        $this->assertTrue(Notification::where('user_id', $reservation->user_id)->where('title', 'การจองหมดอายุ')->exists());
    }

    // ─── [11] รันซ้ำไม่ประมวลผลซ้ำ ───────────────────────────────────────────

    public function test_command_is_idempotent(): void
    {
        $reservation = $this->book();
        $this->travel(self::ONE_HOUR + 1)->seconds();

        $this->expire();
        $this->expire();

        $this->assertSame(1, $reservation->logs()->where('new_status', 'expired')->count());
        $this->assertSame(1, Notification::where('user_id', $reservation->user_id)->count());
    }

    // ─── [12] dry-run ไม่แก้ไขข้อมูล ────────────────────────────────────────

    public function test_dry_run_does_not_change_anything(): void
    {
        $reservation = $this->book();
        $this->travel(self::ONE_HOUR + 1)->seconds();

        $this->artisan('reservations:expire --dry-run')
            ->expectsOutputToContain('would expire 1 reservation(s)')
            ->assertSuccessful();

        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertSame(Payment::STATUS_UNPAID, $reservation->depositPayment->fresh()->payment_status);
        $this->assertSame(0, $reservation->logs()->where('new_status', 'expired')->count());
        $this->assertDatabaseCount('notifications', 0);
    }

    // ─── [13] ไม่มีรายการให้ Expire ───────────────────────────────────────────

    public function test_command_succeeds_when_nothing_to_expire(): void
    {
        $this->artisan('reservations:expire')
            ->expectsOutputToContain('Expired 0 reservation(s)')
            ->assertSuccessful();
    }

    // ─── [14] Scheduler เรียกคำสั่งทุก 1 นาที ─────────────────────────────────

    public function test_scheduler_runs_expire_command_every_minute(): void
    {
        Artisan::call('schedule:list'); // โหลด routes/console.php

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'reservations:expire'));

        $this->assertNotNull($event, 'reservations:expire is not scheduled');
        $this->assertSame('* * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }
}
