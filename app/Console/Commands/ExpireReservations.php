<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--dry-run : Show how many rows would be updated without updating}';
    protected $description = 'Mark reservations as expired when reserve_start + grace period has passed without check-in';

    public function handle(): int
    {
        $now      = now();
        $expireAt = $now->copy()->subMinutes(Reservation::GRACE_PERIOD_MINUTES);

        // หมดเวลา: pending/confirmed ที่เลย grace period แล้วยังไม่ได้ check-in
        // (checked_in ไม่แตะ — รถเข้าแล้ว)
        $eligible = Reservation::whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '<=', $expireAt)
            ->get(['id', 'status']);

        $count = $eligible->count();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would expire {$count} reservation(s).");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No reservations to expire.');
            return self::SUCCESS;
        }

        Reservation::whereIn('id', $eligible->pluck('id'))->update([
            'status'     => 'expired',
            'updated_at' => $now,
        ]);

        $logs = $eligible->map(fn($r) => [
            'reservation_id' => $r->id,
            'old_status'     => $r->status, // ใช้สถานะจริง ไม่ hardcode
            'new_status'     => 'expired',
            'changed_by'     => null,
            'note'           => 'Auto-expired: เกินเวลาเช็คอิน ' . Reservation::GRACE_PERIOD_MINUTES . ' นาที',
            'created_at'     => $now,
            'updated_at'     => $now,
        ])->toArray();

        ReservationLog::insert($logs);

        $this->info("Expired {$count} reservation(s).");
        return self::SUCCESS;
    }
}
