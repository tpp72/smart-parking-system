<?php

namespace Tests\Feature;

use App\Models\OwnerApplication;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Authentication & Authorization (project-plan.md §19) */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private int $plateSeq = 3000;

    private function makeUser(string $role = 'user', array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
            'email_verified_at'    => now(),
        ], $role === 'owner' ? ['owner_status' => 'approved'] : [], $attrs));
    }

    private function book(ParkingLot $lot, User $user)
    {
        return app(ReservationService::class)->create($user, $lot, [
            'license_plate'  => 'กข ' . $this->plateSeq++,
            'plate_province' => 'กรุงเทพมหานคร',
            'brand'          => 'Toyota',
            'color'          => 'ขาว',
            'reserve_start'  => now()->addHour(),
        ]);
    }

    // ─── [1] ต้องยืนยัน Email ก่อนใช้งานระบบหลัก ─────────────────────────────

    public function test_unverified_users_cannot_use_the_main_system(): void
    {
        foreach (['user' => 'user.dashboard', 'owner' => 'owner.dashboard', 'admin' => 'admin.dashboard'] as $role => $route) {
            $user = $this->makeUser($role, ['email_verified_at' => null]);

            $this->actingAs($user)->get(route($route))->assertRedirect(route('verification.notice'));
        }

        $this->actingAs($user)->get(route('verification.notice'))->assertOk();
    }

    public function test_registration_sends_verification_email_and_blocks_until_verified(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name'                  => 'ผู้ใช้ใหม่',
            'email'                 => 'verify-me@example.com',
            'password'              => 'password-123',
            'password_confirmation' => 'password-123',
        ]);

        $user = User::where('email', 'verify-me@example.com')->firstOrFail();

        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('user.dashboard'))->assertRedirect(route('verification.notice'));
    }

    // ─── [2] Force Password Reset ครอบคลุมทุก Role รวม Admin ──────────────────

    public function test_force_password_reset_applies_to_every_role_including_admin(): void
    {
        foreach (['user' => 'user.dashboard', 'owner' => 'owner.dashboard', 'admin' => 'admin.dashboard'] as $role => $route) {
            $user = $this->makeUser($role, ['force_password_reset' => true]);

            $this->actingAs($user)->get(route($route))->assertRedirect(route('profile.edit'));
        }

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    }

    // ─── [3] Walkin User: Login / รีเซ็ตรหัสผ่าน / Session ไม่ได้ ─────────────────

    public function test_walkin_system_user_cannot_log_in_reset_password_or_keep_a_session(): void
    {
        $walkin = User::walkin();
        $walkin->forceFill(['password' => Hash::make('known-password'), 'email_verified_at' => now()])->save();

        $this->post('/login', ['email' => $walkin->email, 'password' => 'known-password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        Notification::fake();
        $this->post('/forgot-password', ['email' => $walkin->email])->assertSessionHasErrors('email');
        Notification::assertNothingSent();

        $this->actingAs($walkin)->get(route('user.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // ─── [4] Route ของ Role อื่นเรียกใช้ไม่ได้ ─────────────────────────────────

    public function test_role_areas_are_protected_from_other_roles(): void
    {
        $user = $this->makeUser();
        $owner = $this->makeUser('owner');
        $admin = $this->makeUser('admin');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('owner.parking-lots.index'))->assertForbidden();

        $this->actingAs($owner)->get(route('admin.users.index'))->assertRedirect(route('owner.dashboard'));
        $this->actingAs($owner)->get(route('user.reservations.index'))->assertRedirect(route('owner.dashboard'));

        $this->actingAs($admin)->get(route('owner.parking-lots.index'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('user.reservations.index'))->assertRedirect(route('admin.dashboard'));

        // Owner ที่ยังไม่ได้รับอนุมัติใช้เครื่องมือจัดการลานไม่ได้
        $this->actingAs($this->makeUser('owner', ['owner_status' => 'rejected']))
            ->get(route('owner.parking-lots.index'))
            ->assertRedirect(route('owner.dashboard'));

        auth()->logout();
        $this->post(route('admin.parking-lots.store'))->assertRedirect(route('login'));
    }

    // ─── [5] Owner เข้าถึงข้อมูลลานของ Owner รายอื่นไม่ได้ ─────────────────────

    public function test_owner_cannot_touch_another_owners_lot_data(): void
    {
        $owner = $this->makeUser('owner');
        $lot = ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->book($lot, $this->makeUser());

        $this->actingAs($owner)->get(route('owner.parking-lots.edit', $lot))->assertForbidden();
        $this->actingAs($owner)->patch(route('owner.parking-lots.update', $lot), ['name' => 'ยึดลาน', 'total_slots' => 1, 'hourly_rate' => 1])->assertForbidden();
        $this->actingAs($owner)->delete(route('owner.parking-lots.destroy', $lot))->assertForbidden();
        $this->actingAs($owner)->get(route('owner.parking-slots.edit', $slot))->assertForbidden();
        $this->actingAs($owner)->delete(route('owner.parking-slots.destroy', $slot))->assertForbidden();
        $this->actingAs($owner)->post(route('owner.parking-slots.store'), ['parking_lot_id' => $lot->id, 'slot_number' => 'Z999'])->assertForbidden();
        $this->actingAs($owner)->post(route('owner.payments.mark-paid', $reservation->depositPayment))->assertForbidden();
        $this->actingAs($owner)->post(route('owner.reservations.check-in', $reservation))->assertForbidden();

        $this->assertFalse($this->actingAs($owner)->get(route('owner.reservations.index'))->viewData('reservations')->pluck('id')->contains($reservation->id));
        $this->assertFalse($this->actingAs($owner)->get(route('owner.payments.index', ['status' => 'all']))->viewData('payments')->pluck('id')->contains($reservation->depositPayment->id));

        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id, 'name' => $lot->name]);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
        $this->assertSame(Payment::STATUS_UNPAID, $reservation->depositPayment->fresh()->payment_status);
    }

    // ─── [6] Admin จัดการลานที่มี Owner ไม่ได้ (Lot Ownership แยกจาก Owner) ───────

    public function test_admin_cannot_manage_lots_owned_by_an_owner(): void
    {
        $admin = $this->makeUser('admin');
        $lot = ParkingLot::factory()->create(['owner_id' => $this->makeUser('owner')->id]);

        $this->actingAs($admin)->get(route('admin.parking-lots.edit', $lot))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.parking-lots.destroy', $lot))->assertForbidden();

        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id]);
    }

    // ─── [7] User เข้าถึงการจองของผู้อื่นไม่ได้ ─────────────────────────────────

    public function test_user_cannot_access_another_users_reservation(): void
    {
        $user = $this->makeUser();
        $lot = ParkingLot::factory()->create();
        ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = $this->book($lot, $this->makeUser());

        $this->actingAs($user)->get(route('user.reservations.edit', $reservation))->assertForbidden();
        $this->actingAs($user)->patch(route('user.reservations.update-plate', $reservation), [
            'plate_number' => 'ยึด 1', 'plate_province' => 'กรุงเทพมหานคร', 'brand' => 'Toyota', 'color' => 'ขาว',
        ])->assertForbidden();
        $this->actingAs($user)->post(route('user.reservations.cancel', $reservation))->assertForbidden();

        $this->assertSame('pending', $reservation->fresh()->status);
        $this->assertFalse($this->actingAs($user)->get(route('user.reservations.index'))->viewData('reservations')->pluck('id')->contains($reservation->id));
    }

    // ─── [8] เอกสารคำขอเป็น Owner เป็นไฟล์ส่วนตัว ─────────────────────────────

    public function test_owner_application_document_is_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $applicant = $this->makeUser();

        $this->actingAs($applicant)->post(route('owner.application.store'), [
            'applicant_type'   => 'individual',
            'contact_name'     => 'สมชาย ใจดี',
            'phone'            => '0812345678',
            'email'            => 'somchai@example.com',
            'parking_lot_name' => 'ลานสมชาย',
            'district'         => 'บางรัก',
            'province'         => 'กรุงเทพมหานคร',
            'estimated_slots'  => 10,
            'document'         => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $application = OwnerApplication::firstOrFail();

        Storage::disk('local')->assertExists($application->document_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->actingAs($applicant)->get(route('owner-applications.document', $application))->assertOk();
        $this->actingAs($this->makeUser('admin'))->get(route('owner-applications.document', $application))->assertOk();
        $this->actingAs($this->makeUser())->get(route('owner-applications.document', $application))->assertForbidden();

        auth()->logout();
        $this->get(route('owner-applications.document', $application))->assertRedirect(route('login'));
    }

    // ─── [9] ไม่มี Role/Permission Model และ Middleware ซ้ำซ้อน ──────────────────

    public function test_legacy_role_permission_models_and_duplicate_middleware_are_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Models/Role.php'));
        $this->assertFileDoesNotExist(app_path('Models/Permission.php'));
        $this->assertFileDoesNotExist(app_path('Http/Middleware/AdminMiddleware.php'));
        $this->assertFileDoesNotExist(app_path('Http/Middleware/OwnerMiddleware.php'));
    }
}
