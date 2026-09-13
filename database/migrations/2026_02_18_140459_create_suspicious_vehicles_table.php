<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Blacklist — ตรวจด้วย ทะเบียน + จังหวัด
        Schema::create('suspicious_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 20);
            $table->string('plate_province', 60);
            $table->string('reason')->nullable();
            $table->string('level', 10)->default('medium');
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps(0);

            $table->unique(['license_plate', 'plate_province']);
            $table->index('is_active');
        });

        DB::statement("ALTER TABLE suspicious_vehicles ADD CONSTRAINT suspicious_vehicles_level_check CHECK (level IN ('low','medium','high'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('suspicious_vehicles');
    }
};
