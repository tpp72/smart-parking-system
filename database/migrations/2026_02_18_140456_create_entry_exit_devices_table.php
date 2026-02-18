<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('entry_exit_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->string('device_type'); // gate/camera/scanner
            $table->string('location');
            $table->string('status')->default('online'); // online/offline
            $table->timestamps(0);
        });

        DB::statement("ALTER TABLE entry_exit_devices
  ADD CONSTRAINT entry_exit_devices_device_type_check
  CHECK (device_type IN ('gate','camera','scanner'))");

        DB::statement("ALTER TABLE entry_exit_devices
  ADD CONSTRAINT entry_exit_devices_status_check
  CHECK (status IN ('online','offline'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_exit_devices');
    }
};
