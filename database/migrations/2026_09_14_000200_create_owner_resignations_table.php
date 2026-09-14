<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * คำร้องลาออกของ Owner — มีผลเมื่อ Admin อนุมัติเท่านั้น (project-plan.md §16, §16.1)
     */
    public function up(): void
    {
        Schema::create('owner_resignations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at', 0)->nullable();
            // สรุปผลเมื่ออนุมัติ: จำนวนการจองที่ยกเลิก / รถที่เช็คเอาท์ / ลานที่ลบ
            $table->json('result')->nullable();
            $table->timestamps(0);

            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE owner_resignations ADD CONSTRAINT owner_resignations_status_check CHECK (status IN ('pending','approved','rejected'))");

        // Owner มีคำร้องที่รอพิจารณาได้ครั้งละ 1 รายการ
        DB::statement("CREATE UNIQUE INDEX owner_resignations_pending_unique ON owner_resignations (user_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_resignations');
    }
};
