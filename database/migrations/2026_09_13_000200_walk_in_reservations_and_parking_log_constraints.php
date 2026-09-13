<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto Check-in + Walk-in (project-plan.md §10.5–10.10, §11)
     * - reservations.is_walk_in: Walk-in เป็น Reservation จริงของ Walkin User และไม่ถูกนับในกฎ
     *   1 Active Reservation ต่อ ทะเบียน + จังหวัด (รถที่มีการจองค้างอยู่ลานอื่น / ยัง pending เข้าเป็น Walk-in ได้)
     * - Walk-in ต้องไม่มี Deposit และไม่มีส่วนลด reservation_fee
     * - ทุก Parking Log ต้องมาจาก Reservation และมีจังหวัด (เลิก Walk-in แบบ Parking Log อย่างเดียว)
     * - ผล Scan ที่อ่านจังหวัดไม่ได้ = อ่านทะเบียนไม่ได้
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('is_walk_in')->default(false)->after('user_id');
        });

        DB::statement('UPDATE reservations SET is_walk_in = true WHERE user_id IN (SELECT id FROM users WHERE is_system = true)');
        DB::statement('ALTER TABLE reservations ADD CONSTRAINT reservations_walk_in_amounts_check CHECK (NOT is_walk_in OR (deposit_amount = 0 AND reservation_fee = 0))');

        DB::statement('DROP INDEX reservations_active_plate_unique');
        DB::statement("CREATE UNIQUE INDEX reservations_active_plate_unique ON reservations (license_plate, plate_province) WHERE status IN ('pending','confirmed','checked_in') AND NOT is_walk_in");

        DB::statement('ALTER TABLE parking_logs ALTER COLUMN reservation_id SET NOT NULL');
        DB::statement('ALTER TABLE parking_logs ALTER COLUMN plate_province SET NOT NULL');
        DB::statement('DROP INDEX parking_logs_open_plate_unique');
        DB::statement('CREATE UNIQUE INDEX parking_logs_open_plate_unique ON parking_logs (license_plate, plate_province) WHERE check_out_time IS NULL');

        DB::statement("UPDATE license_plate_scans SET result = 'unreadable' WHERE plate_province IS NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX parking_logs_open_plate_unique');
        DB::statement("CREATE UNIQUE INDEX parking_logs_open_plate_unique ON parking_logs (license_plate, COALESCE(plate_province, '')) WHERE check_out_time IS NULL");
        DB::statement('ALTER TABLE parking_logs ALTER COLUMN plate_province DROP NOT NULL');
        DB::statement('ALTER TABLE parking_logs ALTER COLUMN reservation_id DROP NOT NULL');

        DB::statement('DROP INDEX reservations_active_plate_unique');
        DB::statement("CREATE UNIQUE INDEX reservations_active_plate_unique ON reservations (license_plate, plate_province) WHERE status IN ('pending','confirmed','checked_in')");
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_walk_in_amounts_check');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('is_walk_in');
        });
    }
};
