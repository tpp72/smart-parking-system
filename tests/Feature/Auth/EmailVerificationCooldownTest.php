<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** ส่งลิงก์ยืนยันอีเมลได้ครั้งละ 1 ครั้งต่อ 60 วินาที — นับรวมทุกช่องทาง ทั้งตอนสมัครและตอนกดส่งใหม่ */
class EmailVerificationCooldownTest extends TestCase
{
    use RefreshDatabase;

    private function resend(User $user)
    {
        return $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.send'));
    }

    public function test_resending_within_60_seconds_is_refused(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->resend($user)->assertSessionHas('status', 'verification-link-sent');
        $this->travel(30)->seconds();
        $this->resend($user)->assertSessionHas('verification-cooldown', fn ($seconds) => $seconds > 0 && $seconds <= 30);

        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_link_can_be_sent_again_after_60_seconds(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->resend($user);
        $this->travel(61)->seconds();
        $this->resend($user)->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    /** ลิงก์แรกส่งตอนสมัคร — กดส่งใหม่ทันทีหลังสมัครต้องถูกกันด้วย ไม่ใช่แค่การกดซ้ำ */
    public function test_cooldown_starts_from_registration(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name'                  => 'ผู้ใช้ใหม่',
            'email'                 => 'new@example.com',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'new@example.com')->firstOrFail();
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);

        $this->resend($user)->assertSessionHas('verification-cooldown');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    /** ปุ่มบอกเวลาที่เหลือ และกดไม่ได้ระหว่างรอ */
    public function test_verify_page_shows_the_countdown_and_disables_the_button(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();

        $page = $this->actingAs($user)->get(route('verification.notice'))->assertOk()
            ->assertSee('ส่งอีกครั้งได้ใน')
            ->assertSee('disabled', false);
        // อาจได้ 59 ถ้าเรนเดอร์คร่อมวินาที
        $this->assertMatchesRegularExpression('/x-data="\{ left: (59|60) \}"/', $page->getContent());

        $this->travel(61)->seconds();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk()
            ->assertSee('x-data="{ left: 0 }"', false);
    }

    /** cooldown แยกตามผู้ใช้ — คนหนึ่งเพิ่งส่ง ไม่กระทบอีกคน */
    public function test_cooldown_is_per_user(): void
    {
        Notification::fake();
        $first = User::factory()->unverified()->create();
        $second = User::factory()->unverified()->create();

        $this->resend($first);
        $this->resend($second)->assertSessionHas('status', 'verification-link-sent');
    }
}
