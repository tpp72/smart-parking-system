<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            // จังหวัดที่ AI อ่านได้จากป้ายทะเบียน (แยกจาก license_plate ที่เป็นแค่เลขทะเบียน)
            $table->string('province', 60)->nullable()->after('license_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->dropColumn('province');
        });
    }
};
