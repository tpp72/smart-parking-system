<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('applicant_type')->default('individual'); // individual | company
            $table->string('business_name')->nullable();
            $table->string('contact_name');
            $table->string('phone', 20);
            $table->string('email');
            $table->string('parking_lot_name');
            $table->string('address')->nullable();
            $table->string('district')->nullable();
            $table->string('province')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('estimated_slots')->default(0);
            $table->string('document_path')->nullable(); // uploaded image/doc
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_applications');
    }
};
