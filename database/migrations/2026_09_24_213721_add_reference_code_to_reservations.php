<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * รหัสอ้างอิงสำหรับรถที่เข้าแบบ Walk-in (project-plan.md §4.2)
 *
 * รถ Walk-in ไม่มีบัญชีผู้ใช้ผูกอยู่ คนขับจึงไม่มีทางดูสถานะรถของตัวเองได้
 * รหัสนี้ออกตอนรถเข้าลานและแสดงที่จอทางเข้า (ในระบบจำลองคือหน้าผลสแกน)
 * ใช้เป็นหลักฐานว่า "คนที่ถือรหัสอยู่กับรถคันนั้นจริง" — ถ้าใช้แค่ทะเบียนอย่างเดียว
 * ใครก็ตามที่เห็นป้ายทะเบียนจะตามดูได้ว่ารถคันนั้นจอดอยู่ลานไหน ช่องไหน
 *
 * nullable เพราะการจองล่วงหน้าไม่ต้องใช้ (เจ้าของบัญชีดูในหน้าหลักของตัวเองได้อยู่แล้ว)
 * และข้อมูลเดิมทั้งหมดไม่มีรหัส
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('reference_code', 6)->nullable()->unique()->after('is_walk_in');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropUnique(['reference_code']);
            $table->dropColumn('reference_code');
        });
    }
};
