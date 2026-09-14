<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Expire การจองที่ไม่ Check-in ภายใน 1 ชั่วโมงหลังเวลาจอง (project-plan.md §23)
Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
