<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_plate_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->string('license_plate');
            $table->string('color', 60)->nullable();
            $table->string('brand', 60)->nullable();
            $table->float('confidence')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('source', 20)->default('manual_upload');
            $table->string('image_path')->nullable();
            $table->timestamp('scan_time', 0);
            $table->timestamps(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_plate_scans');
    }
};
