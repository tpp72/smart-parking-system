<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at', 0)->nullable();
            $table->string('password');
            $table->string('role')->default('user');
            $table->string('owner_status')->nullable();
            $table->boolean('force_password_reset')->default(false);
            $table->rememberToken();
            $table->timestamps(0);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user','owner','admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_owner_status_check CHECK (owner_status IS NULL OR owner_status IN ('pending','approved','rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
