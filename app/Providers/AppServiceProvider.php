<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
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
    }
}
