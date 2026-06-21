<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('location')->nullable();
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->string('landmark')->nullable();
            $table->integer('total_slots');
            $table->decimal('hourly_rate', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->boolean('reservations_enabled')->default(true);
            $table->timestamps(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_lots');
    }
};
