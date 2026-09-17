<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\LicensePlateScan;
use App\Models\Notification;
use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Phase 12 — Admin System (project-plan.md §5.1, §5.1.1, §17.1) */
class AdminSystemTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user', array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : [], $attrs));
    }

    private function confirmedWithSlot(ParkingLot $lot, User $booker): array
    {
        $slot = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $lot->id]);
        $reservation = Reservation::factory()->confirmed()->create([
            'user_id'         => $booker->id,
            'parking_lot_id'  => $lot->id,
            'parking_slot_id' => $slot->id,
        ]);

        return [$reservation, $slot];
    }

    private function parked(ParkingLot $lot, User $booker): array
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

        return [$reservation, $slot];
    }

    private function userPayload(User $user, array $overrides = []): array
    {
        return array_merge(['name' => $user->name, 'email' => $user->email, 'role' => $user->role], $overrides);
    }

    // ─── Dashboard ทั้งระบบ ────────────────────────────────────────────────────

    public function test_admin_dashboard_covers_every_lot_while_management_pages_stay_admin_only(): void
    {
        $admin = $this->makeUser('admin');
        $adminLot = ParkingLot::factory()->create(['owner_id' => null]);
        $ownerLot = ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id]);

        ParkingSlot::factory()->count(2)->create(['parking_lot_id' => $adminLot->id, 'status' => 'available']);
        [$parked] = $this->parked($ownerLot, $this->makeUser());

        $booking = Reservation::factory()->confirmed()->create(['parking_lot_id' => $ownerLot->id]);
        Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $booking->id, 'hourly_rate' => 40,
            'total_amount' => 40, 'payment_status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);

        $done = Reservation::factory()->create(['parking_lot_id' => $adminLot->id, 'status' => 'completed']);
        $log = ParkingLog::factory()->create([
            'parking_lot_id' => $adminLot->id, 'reservation_id' => $done->id,
            'license_plate' => $done->license_plate, 'plate_province' => $done->plate_province,
            'check_in_time' => now()->subHours(3), 'check_out_time' => now()->subHour(),
        ]);
        Payment::create([
            'type' => Payment::TYPE_CHECKOUT, 'reservation_id' => $done->id, 'parking_log_id' => $log->id, 'hourly_rate' => 30,
            'total_amount' => 60, 'payment_status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);

        foreach ([['passed', false, 'กข 1111'], ['low_accuracy', true, 'กข 2222'], ['unreadable', false, null]] as [$result, $suspicious, $plate]) {
            LicensePlateScan::forceCreate([
                'parking_lot_id' => $ownerLot->id, 'license_plate' => $plate, 'plate_province' => $plate ? 'กรุงเทพมหานคร' : null,
                'confidence' => $result === 'passed' ? 95 : 60, 'result' => $result, 'is_suspicious' => $suspicious, 'scan_time' => now(),
            ]);
        }

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(2, $stats['lots_total']);
        $this->assertSame(1, $stats['admin_lots_total']);
        $this->assertSame(3, $stats['slots_total']);
        $this->assertSame(1, $stats['active_now']);
        $this->assertSame(100.0, $stats['revenue_paid']);
        $this->assertSame(40.0, $stats['revenue_deposit']);
        $this->assertSame(60.0, $stats['revenue_parking']);
        $this->assertSame([3, 1, 2, 1], [$stats['scans_total'], $stats['scans_passed'], $stats['scans_failed'], $stats['scans_suspicious']]);

        $statusData = $response->viewData('reservationStatus');
        $this->assertSame([1, 1, 1], [$statusData['confirmed'], $statusData['checked_in'], $statusData['completed']]);
        $this->assertCount(2, $response->viewData('lotsOverview'));

        // เลือกลานเดียว: ตัวเลขเหลือเฉพาะลานนั้น แต่บัญชีดำยังเป็นของทั้งระบบ
        $scoped = $this->actingAs($admin)->get(route('admin.dashboard', ['lot_id' => $adminLot->id, 'range' => '7d']))->assertOk();
        $this->assertSame([2, 0, 60.0], [$scoped->viewData('stats')['slots_total'], $scoped->viewData('stats')['active_now'], $scoped->viewData('stats')['revenue_paid']]);
        $scoped->assertSee('กำลังดู')->assertSee('ดูรวมทุกลาน')->assertSee('ทั้งระบบ ไม่ขึ้นกับลานที่เลือก');

        // หน้าจัดการยังเห็นเฉพาะลานของ Admin
        $listed = $this->actingAs($admin)->get(route('admin.reservations.index'))->viewData('reservations')->pluck('id');
        $this->assertTrue($listed->contains($done->id));
        $this->assertFalse($listed->contains($parked->id));
        $this->assertFalse($listed->contains($booking->id));
    }

    // ─── Role: เป็น Owner ต้องผ่านคำขอสมัคร ──────────────────────────────────────

    public function test_admin_cannot_create_or_promote_an_owner_directly(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'ตั้งเป็น Owner', 'email' => 'owner-direct@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'owner',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'owner-direct@example.com']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Admin ใหม่', 'email' => 'admin-new@example.com',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'role' => 'admin',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'admin-new@example.com', 'role' => 'admin', 'owner_status' => null]);

        $user = $this->makeUser();
        $this->actingAs($admin)->patch(route('admin.users.update', $user), $this->userPayload($user, ['role' => 'owner']))
            ->assertSessionHasErrors('role');
        $this->assertSame('user', $user->fresh()->role);
    }

    // ─── Role: ปลด Owner = ปิดลานแบบเดียวกับลาออก ───────────────────────────────

    public function test_admin_demoting_an_owner_closes_their_lots(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $booker = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $this->confirmedWithSlot($lot, $booker);
        $this->parked($lot, $booker);
        $resignation = OwnerResignation::create(['user_id' => $owner->id, 'reason' => 'ขอลาออก', 'status' => OwnerResignation::STATUS_PENDING]);

        $this->actingAs($admin)->patch(route('admin.users.update', $owner), $this->userPayload($owner, ['role' => 'user']))
            ->assertSessionHasErrors('demotion_reason');
        $this->assertSame('owner', $owner->fresh()->role);
        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);

        $this->actingAs($admin)->patch(route('admin.users.update', $owner), $this->userPayload($owner, ['role' => 'user', 'demotion_reason' => 'ละเมิดข้อตกลง']))
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertSame('user', $owner->role);
        $this->assertNull($owner->owner_status);
        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);

        $messages = Notification::where('user_id', $booker->id)->pluck('message', 'title');
        $this->assertStringContainsString('กรุณาติดต่อผู้ดูแลระบบ', $messages['การจองถูกยกเลิก']);
        $this->assertStringContainsString('กรุณาติดต่อผู้ดูแลระบบ', $messages['รถของคุณถูกเช็คเอาท์โดยระบบ']);
        $this->assertTrue(Notification::where('user_id', $owner->id)->where('title', 'บัญชีของคุณถูกปลดจากการเป็นเจ้าของลานจอด')->exists());

        $audit = AdminAction::where('action', 'user.demote_owner')->firstOrFail();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('ละเมิดข้อตกลง', $audit->meta['reason']);
        $this->assertSame(1, $audit->meta['lots_deleted']);

        $this->assertSame(OwnerResignation::STATUS_APPROVED, $resignation->fresh()->status);
    }

    // ─── ลบผู้ใช้: เคลียร์การจองและช่องจอดก่อน ──────────────────────────────────

    public function test_deleting_a_booker_releases_their_slots_first(): void
    {
        $admin = $this->makeUser('admin');
        $booker = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        [, $reservedSlot] = $this->confirmedWithSlot($lot, $booker);
        [, $occupiedSlot] = $this->parked($lot, $booker);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $booker))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $booker->id]);
        $this->assertSame('available', $reservedSlot->fresh()->status);
        $this->assertSame('available', $occupiedSlot->fresh()->status);

        $audit = AdminAction::where('action', 'user.delete')->firstOrFail();
        $this->assertSame([1, 1], [$audit->meta['reservations_cancelled'], $audit->meta['cars_checked_out']]);
    }

    public function test_deleting_an_owner_closes_their_lots_first(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $booker = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id]);
        $this->confirmedWithSlot($lot, $booker);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $owner))->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $owner->id]);
        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);
        $this->assertStringContainsString('กรุณาติดต่อผู้ดูแลระบบ',
            Notification::where('user_id', $booker->id)->where('title', 'การจองถูกยกเลิก')->value('message'));

        // ลบตัวเองไม่ได้
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertSessionHasErrors('error');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ─── บัญชีระบบ ───────────────────────────────────────────────────────────

    public function test_system_account_is_hidden_and_cannot_be_managed(): void
    {
        $admin = $this->makeUser('admin');
        $walkin = User::walkin();

        $this->assertFalse($this->actingAs($admin)->get(route('admin.users.index'))->viewData('users')->pluck('id')->contains($walkin->id));
        $this->actingAs($admin)->get(route('admin.users.edit', $walkin))->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.users.update', $walkin), $this->userPayload($walkin, ['role' => 'admin']))->assertNotFound();
        $this->actingAs($admin)->patch(route('admin.users.force-reset', $walkin), ['temporary_password' => 'Temp@12345'])->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.users.destroy', $walkin))->assertNotFound();

        $walkin->refresh();
        $this->assertSame('user', $walkin->role);
        $this->assertTrue($walkin->is_system);
    }

    // ─── ลานของ Admin ─────────────────────────────────────────────────────────

    public function test_admin_lot_cannot_be_assigned_to_an_owner_or_deleted_with_active_reservations(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');

        $this->actingAs($admin)->post(route('admin.parking-lots.store'), [
            'name' => 'ลานกลาง Admin', 'total_slots' => 10, 'hourly_rate' => 30, 'owner_id' => $owner->id,
        ])->assertRedirect(route('admin.parking-lots.index'));
        $this->assertDatabaseHas('parking_lots', ['name' => 'ลานกลาง Admin', 'owner_id' => null]);

        foreach (['pending', 'confirmed', 'checked_in'] as $status) {
            $lot = ParkingLot::factory()->create(['owner_id' => null]);
            Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => $status]);

            $this->actingAs($admin)->delete(route('admin.parking-lots.destroy', $lot))->assertSessionHasErrors('error');
            $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);
        }

        $finished = ParkingLot::factory()->create(['owner_id' => null]);
        Reservation::factory()->create(['parking_lot_id' => $finished->id, 'status' => 'completed']);

        $this->actingAs($admin)->delete(route('admin.parking-lots.destroy', $finished))->assertSessionHas('success');
        $this->assertDatabaseMissing('parking_lots', ['id' => $finished->id]);
    }

    // ─── คำขอสมัคร Owner ──────────────────────────────────────────────────────

    public function test_owner_application_can_only_promote_a_user_account(): void
    {
        $admin = $this->makeUser('admin');

        $apply = fn (User $user) => OwnerApplication::create([
            'user_id' => $user->id, 'applicant_type' => 'individual', 'contact_name' => $user->name,
            'phone' => '081-234-5678', 'email' => $user->email, 'parking_lot_name' => 'ลานทดสอบ',
            'district' => 'บางรัก', 'province' => 'กรุงเทพมหานคร', 'estimated_slots' => 10, 'status' => 'pending',
        ]);

        $promotedToAdmin = $this->makeUser('admin', ['owner_status' => 'pending']);
        $blocked = $apply($promotedToAdmin);

        $this->actingAs($admin)->post(route('admin.owner-applications.approve', $blocked))->assertSessionHasErrors('error');
        $this->assertSame('pending', $blocked->fresh()->status);
        $this->assertSame('admin', $promotedToAdmin->fresh()->role);

        $applicant = $this->makeUser('user', ['owner_status' => 'pending']);
        $application = $apply($applicant);

        $this->actingAs($admin)->post(route('admin.owner-applications.approve', $application))->assertSessionHas('success');
        $this->assertSame('approved', $application->fresh()->status);
        $this->assertSame('owner', $applicant->fresh()->role);
    }
}
