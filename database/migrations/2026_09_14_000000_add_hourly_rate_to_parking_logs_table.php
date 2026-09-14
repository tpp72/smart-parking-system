<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checkout (project-plan.md §12.2, §12.5)
     * ค่าจอดใช้ hourly_rate ณ ตอน Check-in — การแก้อัตราของลานระหว่างรถจอดอยู่ต้องไม่กระทบค่าจอดของรถคันนั้น
     */
    public function up(): void
    {
        Schema::table('parking_logs', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('color');
        });

        DB::statement('UPDATE parking_logs SET hourly_rate = lot.hourly_rate FROM parking_lots lot WHERE lot.id = parking_logs.parking_lot_id');
        DB::statement('ALTER TABLE parking_logs ALTER COLUMN hourly_rate SET NOT NULL');
        DB::statement('ALTER TABLE parking_logs ADD CONSTRAINT parking_logs_hourly_rate_check CHECK (hourly_rate >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parking_logs DROP CONSTRAINT IF EXISTS parking_logs_hourly_rate_check');

        Schema::table('parking_logs', function (Blueprint $table) {
            $table->dropColumn('hourly_rate');
        });
    }
};
