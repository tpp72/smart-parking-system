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
            // ยี่ห้อ/สีรถที่ผู้ใช้แจ้งไว้ตอนจอง — ใช้เทียบกับผลสแกน AI ตอน auto check-in
            $table->string('brand', 60)->nullable()->after('license_plate');
            $table->string('color', 40)->nullable()->after('brand');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['brand', 'color']);
        });
    }
};
