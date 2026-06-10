<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('owner_status')->nullable()->after('role');
        });

        // Add CHECK constraint for allowed values
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_owner_status_check CHECK (owner_status IS NULL OR owner_status IN ('pending', 'approved', 'rejected'))");

        // Existing owners are already approved — keep them functional
        DB::statement("UPDATE users SET owner_status = 'approved' WHERE role = 'owner'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_owner_status_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('owner_status');
        });
    }
};
