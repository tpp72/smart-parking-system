<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 3 — Auth / Welcome / Profile / Notifications */
class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'user', array $attrs = []): User
    {
        return User::factory()->create(array_merge([
            'role'                 => $role,
            'force_password_reset' => false,
        ], $role === 'owner' ? ['owner_status' => 'approved'] : [], $attrs));
    }

    // ─── ภาษาไทยทั้งระบบ ──────────────────────────────────────────────────────

    public function test_validation_and_login_failure_messages_are_thai(): void
    {
        $this->from(route('login'))->post(route('login'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'กรุณากรอกอีเมล', 'password' => 'กรุณากรอกรหัสผ่าน']);

        $user = $this->makeUser();
        $this->from(route('login'))->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง']);
    }

    public function test_auth_pages_render_thai_titles_without_breeze_english(): void
    {
        foreach ([
            'login' => 'เข้าสู่ระบบ',
            'register' => 'สมัครสมาชิก',
            'password.request' => 'ลืมรหัสผ่าน',
        ] as $route => $title) {
            $this->get(route($route))->assertOk()->assertSee("<h1 class=\"text-h1 text-fg\">{$title}</h1>", false)
                ->assertDontSee('Forgot your password')->assertDontSee('Remember me');
        }
    }

    public function test_demo_accounts_are_hidden_outside_local_environment(): void
    {
        $this->get(route('login'))->assertOk()->assertDontSee('admin@demo.com');
    }

    public function test_welcome_page_tells_the_one_car_flow_in_thai(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('เส้นทางของรถ 1 คัน')
            ->assertSee('แม่นยำเกิน 85%')
            ->assertSee('ภายใน 60 นาทีหลังเวลาเริ่ม')
            ->assertDontSee('Real-time')
            ->assertDontSee('Get Started');
    }

    public function test_notifications_page_is_thai_and_marks_unread_state_in_text(): void
    {
        $user = $this->makeUser();
        notify_user($user->id, 'การจองได้รับการยืนยัน', 'การจอง #1 ยืนยันแล้ว');

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()
            ->assertSee('ยังไม่อ่าน:')
            ->assertSee('ทำเครื่องหมายว่าอ่านทั้งหมด')
            ->assertSee('ที่แล้ว')
            ->assertDontSee('Mark all read')
            ->assertDontSee('ago');
    }

    // ─── เมนูจำกัดระหว่างต้องตั้งรหัสผ่านใหม่ / ยังไม่ยืนยันอีเมล ─────────────

    public function test_shell_menu_is_limited_until_password_reset_or_email_verified(): void
    {
        $forced = $this->makeUser('admin', ['force_password_reset' => true]);
        $this->actingAs($forced)->get(route('profile.edit'))->assertOk()
            ->assertSee('ตั้งรหัสผ่านใหม่ก่อนจึงจะใช้เมนูอื่นได้')
            ->assertDontSee('aria-label="เมนูด่วน"', false)
            ->assertDontSee(route('admin.payments.index'));

        $unverified = User::factory()->unverified()->create(['role' => 'user']);
        $this->actingAs($unverified)->get(route('profile.edit'))->assertOk()
            ->assertSee('ยืนยันอีเมลก่อนจึงจะใช้เมนูอื่นได้')
            ->assertDontSee(route('user.reservations.create'));
    }

    // ─── ลบบัญชีตัวเอง ────────────────────────────────────────────────────────

    public function test_user_deleting_account_cancels_every_booking_not_yet_checked_in(): void
    {
        $user = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => null]);

        $pending = Reservation::factory()->create(['user_id' => $user->id, 'parking_lot_id' => $lot->id, 'status' => 'pending']);
        $deposit = Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $pending->id, 'hourly_rate' => 40,
            'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID,
        ]);
        $slot = ParkingSlot::factory()->reserved()->create(['parking_lot_id' => $lot->id]);
        Reservation::factory()->confirmed()->create(['user_id' => $user->id, 'parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('ระบบยกเลิกการจองที่ยังไม่ Check-in ทั้งหมด')
            ->assertSee('<span class="num font-semibold text-fg">2</span>', false);

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        // ช่องจอดที่ล็อกไว้ถูกคืน · มัดจำที่ยังไม่ชำระถูก void ก่อนการจองถูกลบตามบัญชี
        $this->assertSame('available', $slot->fresh()->status);
        $this->assertDatabaseHas('admin_actions', ['action' => 'profile.delete']);
        $this->assertDatabaseMissing('payments', ['id' => $deposit->id, 'payment_status' => Payment::STATUS_UNPAID]);
    }

    public function test_user_with_a_parked_car_cannot_delete_account(): void
    {
        $user = $this->makeUser();
        $lot = ParkingLot::factory()->create(['owner_id' => null]);
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'occupied']);
        Reservation::factory()->checkedIn()->create(['user_id' => $user->id, 'parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id]);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertSee('ยังมีรถของคุณจอดอยู่ในลาน')
            ->assertDontSee('confirm-user-deletion');

        $this->actingAs($user)->from(route('profile.edit'))->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrorsIn('userDeletion', 'account');

        $this->assertNotNull($user->fresh());
        $this->assertAuthenticated();
    }

    public function test_owner_and_admin_cannot_delete_their_own_account(): void
    {
        $owner = $this->makeUser('owner');
        $this->actingAs($owner)->get(route('profile.edit'))->assertOk()
            ->assertSee('เจ้าของลานต้องยื่นคำร้องลาออก')
            ->assertSee(route('owner.dashboard').'#owner-resignation');
        $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertNotNull($owner->fresh());

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeletion', 'account');
        $this->assertNotNull($admin->fresh());
    }
}
