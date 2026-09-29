<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ชำระค่าจอดก่อนสแกนออก (project-plan.md §12.6)
 *
 * - parking_logs.checkout_requested_at : เวลาที่คนขับกด Check-out = เวลาที่ใช้ล็อกยอด · ชำระได้ภายใน 5 นาที
 *   เก็บแค่เวลา ไม่เก็บยอด เพราะคำนวณยอดเดิมซ้ำได้ด้วย calculate(..., checkout_requested_at)
 * - payments.prior_paid : ยอดค่าจอดที่ชำระไปแล้วในใบก่อนหน้าของการจอดครั้งเดียวกัน
 *   ใช้เมื่อชำระแล้วไม่สแกนออกภายใน 5 นาที เวลาถูกนับต่อ แล้วต้องชำระส่วนที่เกินเป็นใบใหม่
 *   ใบล่าสุดจึงอธิบายยอดทั้งหมดได้ในตัวเอง: parking_fee − มัดจำ − ส่วนลด − prior_paid = total_amount
 * - การจอดครั้งเดียวมียอดค่าจอดได้หลายใบแล้ว → ยกเลิก unique ของ parking_log_id
 *   แต่ยังให้มี "ยอดค้างชำระ" ได้ไม่เกิน 1 ใบต่อการจอด 1 ครั้ง
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_logs', function (Blueprint $table) {
            $table->timestamp('checkout_requested_at')->nullable()->after('check_out_time');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('prior_paid', 10, 2)->default(0)->after('reservation_discount');
            $table->dropUnique('payments_parking_log_id_unique');
            $table->index('parking_log_id');
        });

        DB::statement("CREATE UNIQUE INDEX payments_unpaid_checkout_per_log_unique ON payments (parking_log_id) WHERE type = 'checkout' AND payment_status = 'unpaid'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS payments_unpaid_checkout_per_log_unique');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['parking_log_id']);
            $table->unique('parking_log_id');
            $table->dropColumn('prior_paid');
        });

        Schema::table('parking_logs', function (Blueprint $table) {
            $table->dropColumn('checkout_requested_at');
        });
    }
};
