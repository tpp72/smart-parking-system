<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('license_plate_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('entry_exit_devices')->cascadeOnDelete();
            $table->string('license_plate');
            $table->string('image_path')->nullable();
            $table->timestamp('scan_time', 0);
            $table->timestamps(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_plate_scans');
    }
};
