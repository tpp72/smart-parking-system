<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use App\Models\User;
use App\Services\CarScanService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Logging & Audit — ทุก Role + ระบบ · Payment Log · Authentication · หน้า Audit Log / Reservation Log */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private int $plateSeq = 1000;

    private function makeUser(string $role = 'user', array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : [], $attrs));
    }

    /** ต้องมี Audit Log ของ action นี้ โดยผู้กระทำนี้ (null = ระบบ) */
    private function assertAudited(string $action, ?User $actor, array $where = []): AdminAction
    {
        $entry = AdminAction::where('action', $action)
            ->where('actor_role', $actor?->role ?? 'system')
            ->where('actor_id', $actor?->id)
            ->where($where)
            ->latest('id')
            ->first();

        $this->assertNotNull($entry, sprintf(
            'ไม่พบ Audit Log %s โดย %s — ที่บันทึกไว้: %s',
            $action,
            $actor?->role ?? 'system',
            AdminAction::orderBy('id')->get(['action', 'actor_role'])->map(fn ($a) => "{$a->action}({$a->actor_role})")->implode(', ')
        ));

        return $entry;
    }

    private function book(ParkingLot $lot, ?User $user = null): Reservation
    {
        return app(ReservationService::class)->create($user ?? $this->makeUser(), $lot, [
            'license_plate'  => 'กข ' . $this->plateSeq++,
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now(),
        ]);
    }

    private function fakeAi(array $overrides = []): void
    {
        $detected = array_merge([
            'license_plate' => 'กข 1234',
            'province'      => 'กรุงเทพมหานคร',
            'brand'         => 'Toyota',
            'color'         => 'ขาว',
            'confidence'    => 95,
        ], $overrides);

        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn($detected));
    }

    private function scan(ParkingLot $lot): void
    {
        $this->actingAs($this->makeUser())->post(route('user.scan.store'), [
            'car_image'      => UploadedFile::fake()->image('car.jpg'),
            'parking_lot_id' => $lot->id,
        ]);
    }

    // ─── [1] Authentication ─────────────────────────────────────────────────

    public function test_authentication_events_are_audited(): void
    {
        $this->post('/register', [
            'name'                  => 'ผู้ใช้ใหม่',
            'email'                 => 'new@example.com',
            'password'              => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertAudited('auth.register', $user);
        $this->assertAudited('auth.login', $user);

        $this->post('/logout');
        $this->assertAudited('auth.logout', $user);

        // Login ที่ไม่สำเร็จไม่ถูกบันทึก
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'wrong-password']);
        $this->assertSame(1, AdminAction::where('action', 'auth.login')->count());

        $this->post('/login', ['email' => 'new@example.com', 'password' => 'password-123']);
        $this->assertSame(2, AdminAction::where('action', 'auth.login')->count());
        $this->assertNotNull($this->assertAudited('auth.login', $user)->ip_address);

        $this->actingAs($user)->put('/password', [
            'current_password'      => 'password-123',
            'password'              => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]);
        $this->assertAudited('auth.password_change', $user);
    }

    // ─── [2] Profile ────────────────────────────────────────────────────────

    public function test_profile_update_is_audited_with_changes(): void
    {
        $user = $this->makeUser('user', ['name' => 'ชื่อเดิม']);

        $this->actingAs($user)->patch('/profile', ['name' => 'ชื่อใหม่', 'email' => $user->email]);

        $entry = $this->assertAudited('profile.update', $user, ['subject_id' => $user->id]);
        $this->assertSame(['from' => 'ชื่อเดิม', 'to' => 'ชื่อใหม่'], $entry->meta['changes']['name']);
        $this->assertArrayNotHasKey('email', $entry->meta['changes']);
    }

    // ─── [3] User: การจอง + Payment Log ──────────────────────────────────────

    public function test_user_reservation_actions_and_deposit_payment_log_are_audited(): void
    {
        $user = $this->makeUser();
        $lot = ParkingLot::factory()->create(['hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        $this->actingAs($user)->post(route('user.reservations.store'), [
            'plate_number'   => 'กข 1234',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'parking_lot_id' => $lot->id,
            'reserve_start'  => now()->addHour()->format('Y-m-d H:i'),
        ])->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();
        $deposit = $reservation->depositPayment;

        $this->assertAudited('reservation.create', $user, ['subject_id' => $reservation->id]);
        $this->assertAudited('payment.deposit_created', $user, ['subject_id' => $deposit->id]);

        $this->actingAs($user)->patch(route('user.reservations.update-plate', $reservation), [
            'plate_number'   => 'กข 5678',
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
        ]);
        $this->assertAudited('reservation.update_vehicle', $user, ['subject_id' => $reservation->id]);

        $this->actingAs($user)->post(route('user.reservations.cancel', $reservation));
        $this->assertAudited('reservation.cancel', $user, ['subject_id' => $reservation->id]);
        $this->assertSame('reservation_cancelled', $this->assertAudited('payment.void', $user, ['subject_id' => $deposit->id])->meta['reason']);
    }

    // ─── [4] Owner: จัดการลาน / ช่องจอด ──────────────────────────────────────

    public function test_owner_lot_and_slot_management_is_audited(): void
    {
        $owner = $this->makeUser('owner');
        $lotFields = ['name' => 'ลานทดสอบ', 'total_slots' => 10, 'reservations_enabled' => 1];

        $this->actingAs($owner)->post(route('owner.parking-lots.store'), $lotFields + ['hourly_rate' => 30]);
        $lot = ParkingLot::where('owner_id', $owner->id)->firstOrFail();
        $this->assertAudited('parking_lot.create', $owner, ['subject_id' => $lot->id]);

        $this->actingAs($owner)->patch(route('owner.parking-lots.update', $lot), $lotFields + ['hourly_rate' => 50]);
        $update = $this->assertAudited('parking_lot.update', $owner, ['subject_id' => $lot->id]);
        $this->assertEquals(50, $update->meta['changes']['hourly_rate']['to']);

        $this->actingAs($owner)->post(route('owner.parking-slots.store'), ['parking_lot_id' => $lot->id, 'slot_number' => 'A001']);
        $slot = ParkingSlot::where('slot_number', 'A001')->firstOrFail();
        $this->assertAudited('parking_slot.create', $owner, ['subject_id' => $slot->id]);

        $this->actingAs($owner)->post(route('owner.parking-slots.bulk.store'), [
            'parking_lot_id' => $lot->id, 'mode' => 'range', 'prefix' => 'B', 'start' => 1, 'end' => 3,
        ]);
        $this->assertSame(3, $this->assertAudited('parking_slot.bulk_create', $owner, ['subject_id' => $lot->id])->meta['count']);

        $this->actingAs($owner)->delete(route('owner.parking-slots.destroy', $slot));
        $this->assertAudited('parking_slot.delete', $owner, ['subject_id' => $slot->id]);

        $this->actingAs($owner)->delete(route('owner.parking-lots.destroy', $lot));
        $this->assertAudited('parking_lot.delete', $owner, ['subject_id' => $lot->id]);
    }

    // ─── [5] Owner: Mark as Paid · Manual Check-in / Check-out · Payment Log ───

    public function test_owner_payment_and_manual_check_in_out_are_audited(): void
    {
        $owner = $this->makeUser('owner');
        $lot = ParkingLot::factory()->create(['owner_id' => $owner->id, 'hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->book($lot);

        $this->actingAs($owner)->post(route('owner.payments.mark-paid', $reservation->depositPayment))->assertSessionHas('success');
        $this->assertAudited('payment.mark_paid', $owner, ['subject_id' => $reservation->depositPayment->id]);
        $this->assertAudited('reservation.confirm', $owner, ['subject_id' => $reservation->id]);

        $this->actingAs($owner)->post(route('owner.reservations.check-in', $reservation))->assertSessionHas('success');
        $this->assertSame('manual', $this->assertAudited('reservation.check_in', $owner, ['subject_id' => $reservation->id])->meta['mode']);

        $this->travel(3)->hours();

        $this->actingAs($owner)->post(route('owner.reservations.check-out', $reservation))->assertSessionHas('success');
        $this->assertAudited('reservation.check_out', $owner, ['subject_id' => $reservation->id]);

        $checkout = $reservation->payments()->where('type', 'checkout')->firstOrFail();
        $created = $this->assertAudited('payment.checkout_created', $owner, ['subject_id' => $checkout->id]);
        $this->assertEquals(40, $created->meta['total_amount']);

        $this->actingAs($owner)->post(route('owner.payments.mark-paid', $checkout))->assertSessionHas('success');
        $this->assertAudited('payment.mark_paid', $owner, ['subject_id' => $checkout->id]);
    }

    // ─── [6] System: Walk-in · Auto Check-out · AI ผิดปกติ ────────────────────

    public function test_scan_system_events_are_audited_as_system(): void
    {
        Storage::fake('public');
        $lot = ParkingLot::factory()->create(['hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);

        // Controller ถูกเก็บไว้หลัง Request แรก จึงกำหนดผล AI ของทุกครั้งที่สแกนไว้ใน Mock เดียว (ตามลำดับ)
        $car = ['license_plate' => 'กข 1234', 'province' => 'กรุงเทพมหานคร', 'brand' => 'Toyota', 'color' => 'ขาว', 'confidence' => 95];
        $this->partialMock(CarScanService::class, fn ($mock) => $mock->shouldReceive('detect')->andReturn(
            $car,                                   // เข้า → Walk-in
            $car,                                   // ออก → Auto Check-out
            ['confidence' => 50] + $car,            // Accuracy ไม่ผ่านเกณฑ์
            ['license_plate' => ''] + $car,         // อ่านทะเบียนไม่ได้
        ));

        $this->scan($lot);

        $walkIn = Reservation::where('is_walk_in', true)->firstOrFail();
        $this->assertAudited('reservation.walk_in', null, ['subject_id' => $walkIn->id]);

        $this->travel(1)->hours();
        $this->scan($lot);

        $this->assertSame('auto', $this->assertAudited('reservation.check_out', null, ['subject_id' => $walkIn->id])->meta['mode']);
        $this->assertAudited('payment.checkout_created', null);

        $this->scan($lot);
        $this->assertAudited('ai_scan.low_accuracy', null, ['subject_type' => 'LicensePlateScan']);

        $this->scan($lot);
        $this->assertAudited('ai_scan.unreadable', null, ['subject_type' => 'LicensePlateScan']);
    }

    // ─── [7] System: Auto Check-in ของการจอง ──────────────────────────────────

    public function test_auto_check_in_of_booking_is_audited_as_system(): void
    {
        Storage::fake('public');
        $lot = ParkingLot::factory()->create(['hourly_rate' => 40]);
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->book($lot);
        app(ReservationService::class)->markDepositPaid($reservation->depositPayment, $this->makeUser('admin'));

        $this->fakeAi(['license_plate' => $reservation->license_plate]);
        $this->scan($lot);

        $this->assertSame('auto', $this->assertAudited('reservation.check_in', null, ['subject_id' => $reservation->id])->meta['mode']);
    }

    // ─── [8] System: Expire + Deposit void ────────────────────────────────────

    public function test_expiry_is_audited_as_system(): void
    {
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->book($lot);

        $this->travel(61)->minutes();
        $this->artisan('reservations:expire')->assertSuccessful();

        $this->assertAudited('reservation.expire', null, ['subject_id' => $reservation->id]);
        $this->assertSame('reservation_expired', $this->assertAudited('payment.void', null, ['subject_id' => $reservation->depositPayment->id])->meta['reason']);
    }

    // ─── [9] การจัดการอื่น ๆ · ไม่บันทึกการเปิดดูหน้า ─────────────────────────

    public function test_owner_application_and_admin_management_are_audited_but_page_views_are_not(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post(route('owner.application.store'), [
            'applicant_type'   => 'individual',
            'contact_name'     => 'สมชาย ใจดี',
            'phone'            => '0812345678',
            'email'            => 'somchai@example.com',
            'parking_lot_name' => 'ลานสมชาย',
            'district'         => 'บางรัก',
            'province'         => 'กรุงเทพมหานคร',
            'estimated_slots'  => 10,
        ]);
        $this->assertAudited('owner_application.submit', $user);

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->post(route('admin.parking-lots.store'), [
            'name' => 'ลานของ Admin', 'total_slots' => 5, 'hourly_rate' => 20, 'reservations_enabled' => 1,
        ]);
        $this->assertAudited('parking_lot.create', $admin);

        $this->actingAs($admin)->get(route('admin.suspicious-vehicles.index'))->assertOk();
        $this->assertFalse(AdminAction::where('action', 'like', '%.index')->exists());
    }

    // ─── [10] หน้า Audit Log ของ Admin: ทั้งระบบ · กรอง Role · ค้นหา · CSV ─────

    public function test_admin_audit_log_page_covers_every_role_and_filters(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner', ['email' => 'owner-audit@example.com']);

        audit_by($owner, 'parking_lot.update', null, ['license_plate' => 'ฆฒ 9999']);
        audit_by(null, 'reservation.expire');

        $all = $this->actingAs($admin)->get(route('admin.admin-actions.index'))->assertOk()->viewData('rows');
        $this->assertEqualsCanonicalizing(['owner', 'system'], collect($all->items())->pluck('actor_role')->all());

        $system = $this->actingAs($admin)->get(route('admin.admin-actions.index', ['actor_role' => 'system']))->viewData('rows');
        $this->assertSame(['reservation.expire'], collect($system->items())->pluck('action')->all());

        $byEmail = $this->actingAs($admin)->get(route('admin.admin-actions.index', ['q' => 'owner-audit@example.com']))->viewData('rows');
        $this->assertSame(['parking_lot.update'], collect($byEmail->items())->pluck('action')->all());

        $byThaiMeta = $this->actingAs($admin)->get(route('admin.admin-actions.index', ['q' => 'ฆฒ 9999']))->viewData('rows');
        $this->assertSame(['parking_lot.update'], collect($byThaiMeta->items())->pluck('action')->all());

        $csv = $this->actingAs($admin)->get(route('admin.admin-actions.export', ['actor_role' => 'system']))->assertOk()->streamedContent();
        $this->assertStringContainsString('reservation.expire', $csv);
        $this->assertStringContainsString('ระบบ', $csv);
        $this->assertStringNotContainsString('parking_lot.update', $csv);

        $this->assertNotSame(200, $this->actingAs($owner)->get(route('admin.admin-actions.index'))->status());
    }

    // ─── [11] Reservation Log: Admin ทั้งระบบ (รวมระบบ) · Owner เฉพาะลานตัวเอง ──

    public function test_reservation_log_pages_include_system_entries_and_respect_owner_scope(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');

        $own = Reservation::factory()->create(['parking_lot_id' => ParkingLot::factory()->create(['owner_id' => $owner->id])->id]);
        $other = Reservation::factory()->create(['parking_lot_id' => ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id])->id]);

        $systemLog = ReservationLog::create(['reservation_id' => $own->id, 'old_status' => 'confirmed', 'new_status' => 'expired', 'changed_by' => null, 'note' => 'Auto-expired ทดสอบ']);
        $otherLog = ReservationLog::create(['reservation_id' => $other->id, 'old_status' => 'pending', 'new_status' => 'cancelled', 'changed_by' => $admin->id, 'note' => 'Admin ยกเลิก']);

        $adminIds = $this->actingAs($admin)->get(route('admin.reservation-logs.index'))->assertOk()->viewData('logs')->pluck('id');
        $this->assertTrue($adminIds->contains($systemLog->id));
        $this->assertTrue($adminIds->contains($otherLog->id));

        $systemOnly = $this->actingAs($admin)->get(route('admin.reservation-logs.index', ['changed_by' => 'system']))->viewData('logs')->pluck('id');
        $this->assertSame([$systemLog->id], $systemOnly->all());

        $ownerIds = $this->actingAs($owner)->get(route('owner.reservation-logs.index'))->assertOk()->viewData('logs')->pluck('id');
        $this->assertTrue($ownerIds->contains($systemLog->id));
        $this->assertFalse($ownerIds->contains($otherLog->id));
        $this->assertFalse(Route::has('owner.reservation-logs.export'));

        $csv = $this->actingAs($admin)->get(route('admin.reservation-logs.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Auto-expired ทดสอบ', $csv);
        $this->assertStringContainsString('ระบบ', $csv);
    }
}
