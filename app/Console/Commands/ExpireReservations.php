<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Console\Command;

/** Scheduler ทุก 1 นาที — project-plan.md §7.2, §23 */
class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--dry-run : แสดงจำนวนการจองที่จะหมดอายุโดยไม่แก้ไขข้อมูล}';

    protected $description = 'Expire การจองที่ไม่ Check-in ภายใน 1 ชั่วโมงหลังเวลาจอง — คืน Slot, void มัดจำที่ยังไม่ชำระ, แจ้ง User';

    public function handle(ReservationService $reservations): int
    {
        $overdue = Reservation::overdue()->orderBy('reserve_start')->orderBy('id')->get();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would expire {$overdue->count()} reservation(s).");
            return self::SUCCESS;
        }

        // แต่ละรายการ Lock และตรวจเงื่อนไขซ้ำใน Transaction ของตัวเอง (กันชนกับ Check-in / Mark as Paid)
        $expired = $overdue->filter(fn (Reservation $reservation) => $reservations->expire($reservation))->count();

        $this->info("Expired {$expired} reservation(s).");

        return self::SUCCESS;
    }
}
