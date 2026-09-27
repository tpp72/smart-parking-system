<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ที่อยู่ของลานจอดเลือกเป็นชั้นตามเขตการปกครองไทย: จังหวัด → อำเภอ/เขต → ตำบล/แขวง
 *
 * เดิมมีแค่ `district` ช่องเดียวที่รวม "แขวง/ตำบล/เขต/อำเภอ" ไว้ด้วยกันและพิมพ์เอง
 * รหัสไปรษณีย์ผูกกับตำบล/แขวง จึงต้องแยกอีกชั้นก่อนถึงจะเติมรหัสให้อัตโนมัติได้
 * คอลัมน์ใหม่เป็น nullable เพื่อไม่ให้ข้อมูลเดิมที่ยังไม่มีตำบลพัง
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['parking_lots', 'owner_applications'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('subdistrict', 100)->nullable()->after('district');
                $t->string('postal_code', 5)->nullable()->after('subdistrict');
            });
        }
    }

    public function down(): void
    {
        foreach (['parking_lots', 'owner_applications'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['subdistrict', 'postal_code']);
            });
        }
    }
};
