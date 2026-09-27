<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ทะเบียนรถที่ผู้ใช้ผูกไว้กับบัญชีตัวเอง (project-plan.md §4.0.3)
 *
 * ไม่ใช่การกลับไปเป็น Vehicle Entity แบบเดิมที่ถูกตัดทิ้ง — ระบบยังเป็น Plate-based
 * การจองยังกรอกทะเบียนตอนจองเหมือนเดิม ไม่ต้องลงทะเบียนรถก่อน
 * ตารางนี้ทำหน้าที่เดียวคือ "รถคันนี้เป็นของบัญชีไหน" เพื่อให้รถที่เข้าแบบ Walk-in
 * ผูกกับบัญชีเจ้าของได้อัตโนมัติ และเจ้าของได้รับการแจ้งเตือน
 *
 * การผูกต้องพิสูจน์ด้วยรหัสอ้างอิงที่ได้จากจอทางเข้าลาน — ป้ายทะเบียนเห็นได้จากภายนอก
 * ถ้าให้ผูกได้โดยไม่มีหลักฐาน ใครก็อ้างว่ารถคันไหนเป็นของตัวเองก็ได้
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('license_plate', 20);
            $table->string('plate_province', 60);
            $table->timestamps();

            // 1 ทะเบียน (ในจังหวัดนั้น) ผูกได้กับบัญชีเดียวเท่านั้น
            $table->unique(['license_plate', 'plate_province']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_vehicles');
    }
};
