<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // deposit: ผูกกับ Reservation ก่อน Check-in (ยังไม่มี Parking Log)
            // checkout: ผูกกับ Parking Log หลัง Check-out
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->cascadeOnDelete();
            $table->foreignId('parking_log_id')->nullable()->unique()->constrained('parking_logs')->cascadeOnDelete();
            $table->string('type', 20);

            // snapshot อัตราค่าจอด ณ เวลาสร้างรายการ
            $table->decimal('hourly_rate', 8, 2);
            $table->decimal('total_hours', 8, 2)->nullable();
            $table->decimal('parking_fee', 10, 2)->default(0);
            // ยอด Deposit ที่นำมาหักตอน Checkout
            $table->decimal('deposit_deduction', 10, 2)->default(0);
            // ส่วนลด reservation_fee ที่นำมาหักตอน Checkout
            $table->decimal('reservation_discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);

            $table->string('payment_status', 20)->default('unpaid');
            // ผู้ยืนยันรับเงิน (Mark as Paid) และเวลา — paid_by NULL = ระบบ (ยอด 0)
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at', 0)->nullable();
            $table->timestamps(0);

            $table->index(['payment_status', 'created_at']);
            $table->index(['reservation_id', 'type']);
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_type_check CHECK (type IN ('deposit','checkout'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (payment_status IN ('unpaid','paid','void'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_amounts_check CHECK (parking_fee >= 0 AND deposit_deduction >= 0 AND reservation_discount >= 0 AND total_amount >= 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_deposit_shape_check CHECK (type <> 'deposit' OR (reservation_id IS NOT NULL AND parking_log_id IS NULL))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_checkout_shape_check CHECK (type <> 'checkout' OR parking_log_id IS NOT NULL)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_paid_at_check CHECK (payment_status <> 'paid' OR paid_at IS NOT NULL)");

        // 1 Reservation มี Deposit Payment ได้ 1 รายการ
        DB::statement("CREATE UNIQUE INDEX payments_deposit_per_reservation_unique ON payments (reservation_id) WHERE type = 'deposit'");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
