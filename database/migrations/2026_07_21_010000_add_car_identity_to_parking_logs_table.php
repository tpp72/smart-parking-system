<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ระบบจองตอนนี้ให้ผู้ใช้พิมพ์ทะเบียน/ยี่ห้อ/สีตอนจองได้เลย ไม่ต้องลงทะเบียนรถ (Vehicle) ล่วงหน้า
     * แต่ parking_logs.vehicle_id เดิมเป็น NOT NULL foreign key ทำให้ Check-In (ทั้งอัตโนมัติผ่าน AI scan
     * และ manual) ใช้ไม่ได้เลยถ้าไม่มี Vehicle ตรงกับทะเบียนนั้นอยู่ก่อน — ย้ายให้ parking_logs เก็บ
     * ทะเบียน/ยี่ห้อ/สีตรงๆ (เหมือนที่ reservations ทำอยู่แล้ว) วิธีนี้ vehicle_id กลายเป็นแค่ลิงก์เสริม
     * (ผูกกับ Vehicle ถ้ามีจริง) ไม่ใช่ตัวที่ต้องมีเสมอ
     */
    public function up(): void
    {
        Schema::table('parking_logs', function (Blueprint $table) {
            $table->string('license_plate', 30)->nullable()->after('vehicle_id');
            $table->string('brand', 60)->nullable()->after('license_plate');
            $table->string('color', 40)->nullable()->after('brand');
        });

        DB::statement('ALTER TABLE parking_logs ALTER COLUMN vehicle_id DROP NOT NULL');

        DB::statement('
            UPDATE parking_logs
            SET license_plate = vehicles.license_plate,
                brand = vehicles.brand,
                color = vehicles.color
            FROM vehicles
            WHERE parking_logs.vehicle_id = vehicles.id
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE parking_logs ALTER COLUMN vehicle_id SET NOT NULL');

        Schema::table('parking_logs', function (Blueprint $table) {
            $table->dropColumn(['license_plate', 'brand', 'color']);
        });
    }
};
