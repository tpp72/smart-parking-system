<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_plate_scans', function (Blueprint $table) {
            $table->id();
            // ผู้อัปโหลดภาพ (จำลองกล้อง)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // ลานที่กล้องติดตั้ง
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            // NULL = AI อ่านทะเบียนไม่ได้
            $table->string('license_plate', 20)->nullable();
            $table->string('plate_province', 60)->nullable();
            $table->string('brand', 60)->nullable();
            $table->string('color', 60)->nullable();
            // Accuracy 0–100
            $table->decimal('confidence', 5, 2)->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('source', 20)->default('manual_upload');
            $table->string('image_path')->nullable();
            $table->timestamp('scan_time', 0);
            $table->timestamps(0);

            $table->index(['parking_lot_id', 'scan_time']);
            $table->index(['license_plate', 'plate_province']);
        });

        DB::statement("ALTER TABLE license_plate_scans ADD CONSTRAINT license_plate_scans_confidence_check CHECK (confidence IS NULL OR (confidence >= 0 AND confidence <= 100))");
    }

    public function down(): void
    {
        Schema::dropIfExists('license_plate_scans');
    }
};
