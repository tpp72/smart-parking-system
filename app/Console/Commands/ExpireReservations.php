<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--dry-run : Show how many rows would be updated without updating}';
    protected $description = 'Mark reservations as expired when reserve_end has passed';

    public function handle(): int
    {
        $now = now();

        // ✅ ค่าเริ่มต้น: หมดเวลาแล้วให้ expired สำหรับ pending + confirmed
        // ถ้าคุณอยากให้ expired เฉพาะ pending ให้ลบ confirmed ออกจาก array นี้
        $eligibleStatuses = ['pending', 'confirmed'];

        $base = DB::table('reservations')
            ->whereIn('status', $eligibleStatuses)
            ->where('reserve_end', '<=', $now);

        $count = (clone $base)->count();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would update {$count} reservation(s) to expired.");
            return self::SUCCESS;
        }

        $updated = $base->update([
            'status' => 'expired',
            'updated_at' => $now,
        ]);

        $this->info("Updated {$updated} reservation(s) to expired.");
        return self::SUCCESS;
    }
}
