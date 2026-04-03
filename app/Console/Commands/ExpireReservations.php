<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--dry-run : Show how many rows would be updated without updating}';
    protected $description = 'Mark reservations as expired when reserve_start + 1 hour has passed without check-in';

    public function handle(): int
    {
        $now = now();

        // หมดเวลา: ผ่านเวลาเช็คอิน + 1 ชั่วโมงแล้ว ยังไม่ได้เช็คอิน
        $expireAt = $now->copy()->subHour();

        $eligibleIds = Reservation::whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '<=', $expireAt)
            ->pluck('id');

        $count = $eligibleIds->count();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would update {$count} reservation(s) to expired.");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No reservations to expire.');
            return self::SUCCESS;
        }

        Reservation::whereIn('id', $eligibleIds)->update([
            'status'     => 'expired',
            'updated_at' => $now,
        ]);

        // บันทึก ReservationLog สำหรับทุกรายการที่ expire
        $logs = $eligibleIds->map(fn($id) => [
            'reservation_id' => $id,
            'old_status'     => 'pending',
            'new_status'     => 'expired',
            'changed_by'     => null,
            'note'           => 'Auto-expired: เกินเวลาเช็คอิน 1 ชั่วโมง',
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->toArray();

        ReservationLog::insert($logs);

        $this->info("Updated {$count} reservation(s) to expired.");
        return self::SUCCESS;
    }
}
