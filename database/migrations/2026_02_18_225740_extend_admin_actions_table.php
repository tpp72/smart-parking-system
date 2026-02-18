<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admin_actions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action');                 // เช่น user.force_reset, reservation.cancel
            $table->string('subject_type')->nullable(); // เช่น User, Reservation
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->json('meta')->nullable();          // before/after/extra data
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();

            // Indexes
            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['admin_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('admin_actions', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['admin_id', 'created_at']);

            $table->dropConstrainedForeignId('admin_id');

            $table->dropColumn([
                'action',
                'subject_type',
                'subject_id',
                'meta',
                'ip_address',
                'user_agent',
            ]);
        });
    }
};
