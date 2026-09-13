<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_lot_id')
                ->constrained('parking_lots')
                ->cascadeOnDelete();
            $table->string('slot_number');
            $table->string('status')->default('available');
            $table->timestamps(0);

            $table->unique(['parking_lot_id', 'slot_number']);
            $table->index(['parking_lot_id', 'status']);
        });

        DB::statement("ALTER TABLE parking_slots ADD CONSTRAINT parking_slots_status_check CHECK (status IN ('available','reserved','occupied'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_slots');
    }
};
