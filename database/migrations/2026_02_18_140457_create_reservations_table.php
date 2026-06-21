<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('license_plate')->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('parking_lot_id')->constrained('parking_lots')->cascadeOnDelete();
            $table->foreignId('parking_slot_id')->nullable()->constrained('parking_slots')->nullOnDelete();
            $table->timestamp('reserve_start', 0);
            $table->timestamp('checked_in_at', 0)->nullable();
            $table->timestamp('completed_at', 0)->nullable();
            $table->decimal('reservation_fee', 8, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps(0);

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
