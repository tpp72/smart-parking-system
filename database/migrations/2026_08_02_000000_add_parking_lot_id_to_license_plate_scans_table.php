<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * แต่ละครั้งที่สแกน จำลองว่ากล้องติดอยู่ที่ลานจอดลานหนึ่งโดยเฉพาะ (ระบบจริงกล้องจะส่งข้อมูล
     * มาที่เว็บพร้อมระบุลานอยู่แล้ว) — เก็บว่าสแกนนี้มาจากลานไหน ใช้ทั้งเป็นปลายทาง walk-in
     * check-in และขอบเขตสิทธิ์ตามลานที่ผู้สแกนดูแล (unowned สำหรับ admin, ของตัวเองสำหรับ owner)
     */
    public function up(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->foreignId('parking_lot_id')->nullable()->after('vehicle_id')
                ->constrained('parking_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('license_plate_scans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parking_lot_id');
        });
    }
};
