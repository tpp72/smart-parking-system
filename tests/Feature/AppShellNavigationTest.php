<?php

namespace Tests\Feature;

use App\Models\OwnerApplication;
use App\Models\OwnerResignation;
use App\Models\ParkingLog;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** UI Phase 2 — App Shell: Hybrid Navigation (docs/PRODUCT.md) */
class AppShellNavigationTest extends TestCase
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

    /** ทุกลิงก์ในเมนู (sidebar + บัญชี + แจ้งเตือน) ต้องเปิดได้จริงด้วยสิทธิ์ของบทบาทนั้น */
    private function assertEveryMenuLinkOpens(User $user): array
    {
        $this->actingAs($user);
        $this->get('/'); // ให้ request()->routeIs() มี route ปัจจุบัน
        $nav = Navigation::for($user);

        $items = $nav['staff']
            ? collect($nav['groups'])->flatMap(fn ($group) => $group['items'])
            : collect([...$nav['top'], $nav['primary']]);
        $items = $items->merge($nav['account'])->push($nav['notifications']);

        foreach ($items as $item) {
            $this->get(strtok($item['href'], '#'))->assertOk();
        }

        return $items->pluck('label')->all();
    }

    public function test_admin_menu_reaches_every_admin_page_in_the_agreed_groups(): void
    {
        $admin = $this->makeUser('admin');
        $labels = $this->assertEveryMenuLinkOpens($admin);

        $nav = Navigation::for($admin);
        $this->assertSame(['ปฏิบัติการ', 'ลานจอด', 'การเงิน', 'ผู้ใช้และความปลอดภัย', 'รายงาน'], array_column($nav['groups'], 'label'));
        foreach (['ลานจอด', 'ช่องจอด', 'ผู้ใช้', 'คำขอเป็นเจ้าของลาน', 'คำร้องลาออก', 'บัญชีดำ', 'Audit Log', 'ส่งออก CSV', 'ประวัติสแกน'] as $label) {
            $this->assertContains($label, $labels);
        }
        $this->assertSame(['ภาพรวม', 'การจอง', 'ชำระเงิน', 'AI สแกน', 'เมนู'], array_column($nav['bottom'], 'label'));
    }

    public function test_owner_menu_has_owner_pages_but_no_export_or_user_management(): void
    {
        $owner = $this->makeUser('owner');
        $labels = $this->assertEveryMenuLinkOpens($owner);

        foreach (['AI สแกน', 'ประวัติสแกน', 'รายได้', 'Log การจอง', 'สถานะเจ้าของลาน', 'ลาออกจากการเป็นเจ้าของลาน'] as $label) {
            $this->assertContains($label, $labels);
        }
        foreach (['ส่งออก CSV', 'ผู้ใช้', 'Audit Log', 'บัญชีดำ'] as $label) {
            $this->assertNotContains($label, $labels);
        }
    }

    public function test_user_gets_five_bottom_tabs_and_account_holds_the_remaining_pages(): void
    {
        $user = $this->makeUser();
        $this->assertEveryMenuLinkOpens($user);

        $nav = Navigation::for($user);
        $this->assertSame(['หน้าหลัก', 'จอง', 'การจอง', 'แจ้งเตือน', 'บัญชี'], array_column($nav['bottom'], 'label'));
        $this->assertSame(['โปรไฟล์', 'ประวัติการจอด', 'AI สแกน', 'สมัครเป็นเจ้าของลาน'], array_column($nav['account'], 'label'));

        OwnerApplication::create([
            'user_id' => $user->id, 'contact_name' => $user->name, 'phone' => '0800000000',
            'email' => $user->email, 'parking_lot_name' => 'ลานทดสอบ', 'status' => 'pending',
        ]);
        $this->assertContains('คำขอเป็นเจ้าของลาน', array_column(Navigation::for($user)['account'], 'label'));
    }

    public function test_finance_badge_counts_unpaid_deposits_and_checkouts_within_role_scope(): void
    {
        $admin = $this->makeUser('admin');
        $owner = $this->makeUser('owner');
        $adminLot = ParkingLot::factory()->create(['owner_id' => null]);
        $ownerLot = ParkingLot::factory()->create(['owner_id' => $owner->id]);

        $this->unpaidDeposit($adminLot);
        $this->unpaidCheckout($adminLot);
        $this->unpaidCheckout($ownerLot);

        $this->actingAs($admin);
        $this->assertSame(2, $this->badge(Navigation::for($admin), 'payments'));
        $this->assertSame(1, $this->badge(Navigation::for($owner), 'payments'));
    }

    public function test_admin_badges_show_pending_owner_applications_and_resignations(): void
    {
        $admin = $this->makeUser('admin');
        $applicant = $this->makeUser();
        OwnerApplication::create([
            'user_id' => $applicant->id, 'contact_name' => $applicant->name, 'phone' => '0800000000',
            'email' => $applicant->email, 'parking_lot_name' => 'ลานใหม่', 'status' => 'pending',
        ]);
        OwnerResignation::create([
            'user_id' => $this->makeUser('owner')->id, 'reason' => 'ปิดกิจการ', 'status' => OwnerResignation::STATUS_PENDING,
        ]);

        $this->actingAs($admin);
        $nav = Navigation::for($admin);
        $this->assertSame(1, $this->badge($nav, 'owner-applications'));
        $this->assertSame(1, $this->badge($nav, 'owner-resignations'));
        // สองรายการนี้ไม่อยู่ในแถบล่าง → ปุ่ม "เมนู" แสดงผลรวมแทน
        $this->assertSame(2, collect($nav['bottom'])->firstWhere('key', 'menu')['badge']);
    }

    public function test_shell_renders_landmarks_skip_link_and_marks_current_page(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-label="เมนูหลัก"', false)
            ->assertSee('aria-label="เมนูด่วน"', false)
            ->assertSee('aria-label="ตำแหน่งปัจจุบัน"', false)
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Quick Access');

        $this->actingAs($this->makeUser())
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('aria-label="ตำแหน่งปัจจุบัน"', false)
            ->assertSee('sp-has-bottom-bar', false);
    }

    private function badge(array $nav, string $key): int
    {
        return collect($nav['groups'])->flatMap(fn ($group) => $group['items'])->firstWhere('key', $key)['badge'];
    }

    private function unpaidDeposit(ParkingLot $lot): void
    {
        $reservation = Reservation::factory()->create(['parking_lot_id' => $lot->id, 'status' => 'pending']);
        Payment::create([
            'type' => Payment::TYPE_DEPOSIT, 'reservation_id' => $reservation->id, 'hourly_rate' => 40,
            'total_amount' => 40, 'payment_status' => Payment::STATUS_UNPAID,
        ]);
    }

    private function unpaidCheckout(ParkingLot $lot): void
    {
        $slot = ParkingSlot::factory()->create(['parking_lot_id' => $lot->id]);
        $reservation = Reservation::factory()->walkIn()->create(['parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id, 'status' => 'completed']);
        $log = ParkingLog::factory()->create([
            'parking_lot_id' => $lot->id, 'parking_slot_id' => $slot->id, 'reservation_id' => $reservation->id,
            'license_plate' => $reservation->license_plate, 'plate_province' => $reservation->plate_province,
            'check_in_time' => now()->subHours(2), 'check_out_time' => now(),
        ]);
        Payment::create([
            'type' => Payment::TYPE_CHECKOUT, 'reservation_id' => $reservation->id, 'parking_log_id' => $log->id,
            'hourly_rate' => 30, 'total_amount' => 60, 'payment_status' => Payment::STATUS_UNPAID,
        ]);
    }
}
