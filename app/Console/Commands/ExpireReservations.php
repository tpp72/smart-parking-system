<?php

namespace App\Console\Commands;

use App\Models\ParkingSlot;
use App\Models\Reservation;
use App\Models\ReservationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire {--dry-run : Show how many rows would be updated without updating}';
    protected $description = 'Mark reservations as expired when reserve_start + grace period has passed without check-in';

    public function handle(): int
    {
        $now          = now();
        $graceMinutes = Reservation::gracePeriodMinutes();
        $expireAt     = $now->copy()->subMinutes($graceMinutes);

        // pending/confirmed ที่เลย grace period แล้วยังไม่ได้ check-in
        // (checked_in ไม่แตะ — รถเข้าแล้ว)
        $eligible = Reservation::whereIn('status', ['pending', 'confirmed'])
            ->where('reserve_start', '<=', $expireAt)
            ->get(['id', 'status', 'parking_slot_id', 'user_id']);

        $count = $eligible->count();

        if ($this->option('dry-run')) {
            $this->info("DRY RUN: would expire {$count} reservation(s).");
            return self::SUCCESS;
        }

        if ($count === 0) {
            $this->info('No reservations to expire.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($eligible, $now, $graceMinutes) {
            $ids = $eligible->pluck('id');

            // 1. Mark reservations expired
            Reservation::whereIn('id', $ids)->update([
                'status'     => 'expired',
                'updated_at' => $now,
            ]);

            // 2. Audit log for each reservation
            $logs = $eligible->map(fn($r) => [
                'reservation_id' => $r->id,
                'old_status'     => $r->status,
                'new_status'     => 'expired',
                'changed_by'     => null,
                'note'           => 'Auto-expired: เกินเวลาเช็คอิน ' . $graceMinutes . ' นาที',
                'created_at'     => $now,
                'updated_at'     => $now,
            ])->toArray();

            ReservationLog::insert($logs);

            // 3. Release parking slots that are still 'reserved' (never release 'occupied')
            $slotIds = $eligible->pluck('parking_slot_id')->filter()->unique()->values();
            if ($slotIds->isNotEmpty()) {
                ParkingSlot::whereIn('id', $slotIds)
                    ->where('status', 'reserved')
                    ->update(['status' => 'available', 'updated_at' => $now]);
            }
        });

        // 4. Notify users (best-effort, outside transaction)
        foreach ($eligible as $r) {
            if ($r->user_id) {
                notify_user(
                    $r->user_id,
                    'การจองหมดอายุ',
                    "การจอง #{$r->id} ของคุณหมดอายุแล้ว เนื่องจากไม่มีการเช็คอินภายในเวลาที่กำหนด ({$graceMinutes} นาที)"
                );
            }
        }

        $this->info("Expired {$count} reservation(s).");
        return self::SUCCESS;
    }
}
