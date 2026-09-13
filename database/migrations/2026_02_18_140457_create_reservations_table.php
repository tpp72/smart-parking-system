<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            // Walk-in ใช้ user_id ของ System User "Walkin User"
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            // ระบบเป็นผู้จัดสรร Slot
            $table->foreignId('parking_slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();

            // ข้อมูลรถแบบ Plate-based: license_plate = เลขทะเบียนเท่านั้น (ไม่รวมจังหวัด)
            $table->string('license_plate', 20);
            $table->string('plate_province', 60);
            $table->string('brand', 60)->nullable();
            $table->string('color', 40)->nullable();

            $table->timestamp('reserve_start', 0);
            $table->timestamp('checked_in_at', 0)->nullable();
            $table->timestamp('completed_at', 0)->nullable();

            // Deposit = เงินมัดจำจริง (hourly_rate × 1, Walk-in = 0) · reservation_fee = ส่วนลด — คนละรายการ
            $table->decimal('deposit_amount', 10, 2)->default(0);
            $table->decimal('reservation_fee', 10, 2)->default(0);

            $table->string('status', 20)->default('pending');
            $table->timestamps(0);

            $table->index('status');
            $table->index(['parking_lot_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('reserve_start');
            $table->index(['license_plate', 'plate_province']);
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending','confirmed','checked_in','completed','cancelled','expired'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_amounts_check CHECK (deposit_amount >= 0 AND reservation_fee >= 0)");

        // 1 Active Reservation ต่อ ทะเบียน + จังหวัด
        DB::statement("CREATE UNIQUE INDEX reservations_active_plate_unique ON reservations (license_plate, plate_province) WHERE status IN ('pending','confirmed','checked_in')");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
