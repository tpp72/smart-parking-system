<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\User;
use App\Services\CarScanService;
use App\Services\CheckInService;
use App\Services\CheckOutService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Notification — ใครได้รับแจ้งเตือนเหตุการณ์ไหน (project-plan.md §15.4) */
class NotificationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private int $plateSeq = 2000;

    private function makeUser(string $role = 'user'): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : []));
    }

    private function book(ParkingLot $lot, ?User $user = null, $reserveStart = null): Reservation
    {
        return app(ReservationService::class)->create($user ?? $this->makeUser(), $lot, [
            'license_plate'  => 'กข ' . $this->plateSeq++,
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => $reserveStart ?? now(),
        ]);
    }

    private function titles(User $user): array
    {
        return Notification::where('user_id', $user->id)->orderBy('id')->pluck('title')->all();
    }

    // ─── [1] บัญชีระบบ (Walkin User) ไม่ได้รับ Notification ──────────────────

    public function test_system_accounts_never_receive_notifications(): void
    {
        notify_user(User::walkin()->id, 'ทดสอบ', 'ข้อความ');
        $this->assertSame(0, Notification::where('user_id', User::walkin()->id)->count());

        $user = $this->makeUser();
        notify_user($user->id, 'ทดสอบ', 'ข้อความ');

        $this->assertFalse(Notification::where('user_id', $user->id)->firstOrFail()->is_read);
    }

    // ─── [2] ผู้ดูแลลาน = Owner ของลาน · ลานของ Admin = Admin ทุกคน ─────────────

    public function test_lot_managers_are_owner_or_all_admins_for_admin_lots(): void
    {
        $adminA = $this->makeUser('admin');
        $adminB = $this->makeUser('admin');
        $owner = $this->makeUser('owner');

        $this->assertSame([$owner->id], ParkingLot::factory()->create(['owner_id' => $owner->id])->managerIds()->all());
        $this->assertEqualsCanonicalizing([$adminA->id, $adminB->id], ParkingLot::factory()->create(['owner_id' => null])->managerIds()->all());
    }

    // ─── [3] เหตุการณ์การจองทั่วไปไม่แจ้งผู้ดูแลลาน (จองใหม่ / ยกเลิก / หมดอายุ / ค้างชำระ) ─

    public function test_lot_managers_are_not_notified_about_routine_booking_events(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id, 'hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'N001']);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'slot_number' => 'N002']);

        // จองใหม่ + User ยกเลิกเอง
        $user = $this->makeUser();
        $cancelled = $this->book($lot, $user, now()->addHour());
        $this->actingAs($user)->post(route('user.reservations.cancel', $cancelled));

        // การจองที่จะหมดอายุ
        $this->book($lot);

        // เข้าจอดแล้วออกโดยมียอดค้างชำระ
        $parked = $this->book($lot);
        app(ReservationService::class)->markDepositPaid($parked->depositPayment, $owner);
        app(CheckInService::class)->checkInReservation($parked->fresh(), $owner, allowEarly: true);
        $this->travel(3)->hours();
        app(CheckOutService::class)->checkOut($parked->fresh(), $owner);

        $this->artisan('reservations:expire')->assertSuccessful();

        $this->assertSame([], $this->titles($owner));
        $this->assertSame([], $this->titles($admin));
        $this->assertContains('ยกเลิกการจองเรียบร้อยแล้ว', $this->titles($user));
    }

    // ─── [4] Manual Check-in แจ้ง User เจ้าของการจอง ────────────────────────

    public function test_manual_check_in_notifies_booking_owner(): void
    {
        $owner = $this->makeUser('owner');
        $user = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $reservation = $this->book($lot, $user);
        app(ReservationService::class)->markDepositPaid($reservation->depositPayment, $owner);

        $this->actingAs($owner)->post(route('owner.reservations.check-in', $reservation))->assertSessionHas('success');

        $notification = Notification::where('user_id', $user->id)->where('title', 'เช็คอินสำเร็จ')->firstOrFail();
        $this->assertStringContainsString("#{$reservation->id}", $notification->message);
        $this->assertSame([], $this->titles($owner));
    }

    // ─── [5] รถมาก่อนเวลาจองในลานของ Admin → แจ้ง Admin ทุกคน ─────────────────

    public function test_early_arrival_at_admin_lot_notifies_every_admin(): void
    {
        Storage::fake('public');
        $adminA = $this->makeUser('admin');
        $adminB = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $booking = $this->book($lot, null, now()->addHours(2));
        app(ReservationService::class)->markDepositPaid($booking->depositPayment, $adminA);

        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn([
            'license_plate' => $booking->license_plate,
            'province'      => 'กรุงเทพมหานคร',
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 95,
        ]));

        $this->actingAs($this->makeUser())->post(route('user.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);

        $this->assertContains('รถมาก่อนเวลาจอง', $this->titles($adminA));
        $this->assertContains('รถมาก่อนเวลาจอง', $this->titles($adminB));
    }

    // ─── [6] หน้าแจ้งเตือน: นับยังไม่อ่านทั้งหมด · อ่านของผู้อื่นไม่ได้ ───────────

    public function test_notification_page_counts_all_unread_and_protects_other_users(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        foreach (range(1, 25) as $i) {
            notify_user($user->id, "เรื่องที่ {$i}", 'ข้อความ');
        }
        Notification::where('user_id', $user->id)->orderBy('id')->limit(3)->get()->each->update(['is_read' => true]);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertViewHas('unreadCount', 22);

        $foreign = Notification::create(['user_id' => $other->id, 'title' => 'ของผู้อื่น', 'message' => '-', 'is_read' => false]);
        $this->actingAs($user)->post(route('notifications.read', $foreign))->assertForbidden();

        $this->actingAs($user)->post(route('notifications.read-all'));

        $this->assertSame(0, Notification::where('user_id', $user->id)->unread()->count());
        $this->assertFalse($foreign->fresh()->is_read);
    }
}
