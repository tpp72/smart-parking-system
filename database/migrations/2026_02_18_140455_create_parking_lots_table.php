<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_lots', function (Blueprint $table) {
            $table->id();
            // NULL = ลานของ Admin (ใช้ร่วมกันทุก Admin) · ลบเจ้าของแล้วลานถูกลบตาม
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('location')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('landmark')->nullable();
            $table->integer('total_slots');
            $table->decimal('hourly_rate', 8, 2);
            // ลานไม่มีสถานะเปิด/ปิดลาน — มีเฉพาะเปิด/ปิดรับ Reservation
            $table->boolean('reservations_enabled')->default(true);
            $table->timestamps(0);

            $table->index('owner_id');
        });

        DB::statement("ALTER TABLE parking_lots ADD CONSTRAINT parking_lots_amounts_check CHECK (hourly_rate >= 0 AND total_slots >= 0)");
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_lots');
    }
};
