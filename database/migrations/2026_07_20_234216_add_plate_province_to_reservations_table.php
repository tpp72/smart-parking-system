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
        Schema::table('reservations', function (Blueprint $table) {
            // จังหวัดของป้ายทะเบียนที่ผู้ใช้เลือกตอนจอง (แยกจาก license_plate ที่เป็นสตริงรวม
            // "เลขทะเบียน จังหวัด" เพื่อเทียบกับผลสแกน AI ได้ตรงๆ ไม่ต้อง parse สตริงทุกครั้ง)
            $table->string('plate_province', 60)->nullable()->after('license_plate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('plate_province');
        });
    }
};
