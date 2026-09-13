<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ผลการตรวจของ AI Scan (project-plan.md §10.3–10.4)
     * passed = อ่านทะเบียนได้และ Accuracy > 85% · low_accuracy = Accuracy ไม่เกิน 85% · unreadable = อ่านทะเบียนไม่ได้
     */
    public function up(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->string('result', 20)->nullable()->after('confidence');
        });

        DB::statement("
            UPDATE license_plate_scans
            SET result = CASE
                WHEN license_plate IS NULL THEN 'unreadable'
                WHEN confidence > 85 THEN 'passed'
                ELSE 'low_accuracy'
            END
        ");

        DB::statement('ALTER TABLE license_plate_scans ALTER COLUMN result SET NOT NULL');
        DB::statement("ALTER TABLE license_plate_scans ADD CONSTRAINT license_plate_scans_result_check CHECK (result IN ('passed','low_accuracy','unreadable'))");

        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->index(['result', 'scan_time']);
        });
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE license_plate_scans DROP CONSTRAINT IF EXISTS license_plate_scans_result_check');

        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->dropIndex(['result', 'scan_time']);
            $table->dropColumn('result');
        });
    }
};
