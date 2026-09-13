<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignId('parking_slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            // 1 Reservation มีการจอดจริงได้ 1 รายการ
            $table->foreignId('reservation_id')->nullable()->unique()->constrained('reservations')->cascadeOnDelete();

            $table->string('license_plate', 20);
            $table->string('plate_province', 60)->nullable();
            $table->string('brand', 60)->nullable();
            $table->string('color', 40)->nullable();

            $table->timestamp('check_in_time', 0);
            $table->timestamp('check_out_time', 0)->nullable();
            $table->timestamps(0);

            $table->index(['parking_lot_id', 'check_out_time']);
            $table->index(['license_plate', 'plate_province']);
        });

        DB::statement("ALTER TABLE parking_logs ADD CONSTRAINT parking_logs_time_check CHECK (check_out_time IS NULL OR check_out_time >= check_in_time)");

        // รถคันเดียวกัน (ทะเบียน + จังหวัด) มีการจอดที่ยังไม่ Check-out ได้ครั้งละ 1 รายการ
        DB::statement("CREATE UNIQUE INDEX parking_logs_open_plate_unique ON parking_logs (license_plate, COALESCE(plate_province, '')) WHERE check_out_time IS NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_logs');
    }
};
