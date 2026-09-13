<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit Log ของทุก Role (user / owner / admin / system)
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();
            // NULL เมื่อผู้กระทำคือระบบ หรือบัญชีถูกลบไปแล้ว
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Role ของผู้กระทำขณะทำรายการ
            $table->string('actor_role', 10);
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps(0);

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index(['actor_role', 'created_at']);
        });

        DB::statement("ALTER TABLE admin_actions ADD CONSTRAINT admin_actions_actor_role_check CHECK (actor_role IN ('user','owner','admin','system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
