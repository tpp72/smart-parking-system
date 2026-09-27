<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Audit Log เหตุการณ์ Authentication (project-plan.md §18.1) — ไม่บันทึก Login ที่ไม่สำเร็จ
        Event::listen(Registered::class, fn (Registered $event) => audit_by($event->user, 'auth.register', $event->user));
        Event::listen(Login::class, fn (Login $event) => audit_by($event->user, 'auth.login', $event->user, ['remember' => $event->remember]));
        Event::listen(Logout::class, function (Logout $event) {
            if ($event->user && ! $event->user->is_system) {
                audit_by($event->user, 'auth.logout', $event->user);
            }
        });
        Event::listen(Verified::class, fn (Verified $event) => audit_by($event->user, 'auth.email_verified', $event->user));
        Event::listen(PasswordReset::class, fn (PasswordReset $event) => audit_by($event->user, 'auth.password_reset', $event->user));

        $this->embedBrandIconInMail();
    }

    /**
     * ฝังไอคอนของระบบไปกับอีเมลแทนการลิงก์ไปที่ URL
     *
     * ผู้รับเปิดอีเมลจากเครื่องอื่น และ Gmail/Outlook ดึงรูปผ่านเซิร์ฟเวอร์ของตัวเอง
     * ถ้าเว็บรันบน localhost หรืออยู่หลัง firewall เซิร์ฟเวอร์เหล่านั้นเข้าไม่ถึง ผู้รับจะเห็นรูปเสีย
     * จึงแนบไฟล์ไปกับอีเมลแล้วชี้ src เป็น cid: แทน — ใช้ได้ทุกที่ไม่ต้องรอ deploy
     *
     * ทำตรงจุดส่งเพราะอีเมลของ Notification ถูก render เป็น HTML ก่อนถึง Mailer
     * ในไฟล์ view จึงไม่มีตัวแปร $message ให้เรียก embed() ได้
     */
    private function embedBrandIconInMail(): void
    {
        Event::listen(MessageSending::class, function (MessageSending $event) {
            $logo = config('brand.logo_email');
            $file = $logo ? public_path($logo) : null;
            $html = $event->message->getHtmlBody();

            if (! $logo || ! $file || ! is_file($file) || ! is_string($html)) {
                return;
            }

            $url = url($logo);

            if (! str_contains($html, $url)) {
                return;
            }

            $event->message->embedFromPath($file, 'brand-icon');
            $event->message->html(str_replace($url, 'cid:brand-icon', $html));
        });
    }
}
