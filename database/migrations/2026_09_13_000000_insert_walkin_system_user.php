<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * System User ที่ใช้เป็นเจ้าของ Reservation ของ Walk-in — role = user, is_system = true
     * ต้องมีอยู่เสมอใน Database (ไม่ใช่ข้อมูลตัวอย่างของ Seeder)
     */
    public function up(): void
    {
        DB::table('users')->insert([
            'name'                 => 'Walkin User',
            'email'                => 'walkin@system.local',
            'email_verified_at'    => null,
            'password'             => Hash::make(Str::random(64)),
            'role'                 => 'user',
            'owner_status'         => null,
            'force_password_reset' => false,
            'is_system'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('users')->where('email', 'walkin@system.local')->where('is_system', true)->delete();
    }
};
