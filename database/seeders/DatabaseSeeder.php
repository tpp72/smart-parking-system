<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name'               => 'Admin User',
            'email'              => 'admin@demo.com',
            'password'           => \Illuminate\Support\Facades\Hash::make('password'),
            'role'               => 'admin',
            'owner_status'       => null,
            'email_verified_at'  => now(),
        ]);

        User::factory()->create([
            'name'               => 'Owner User',
            'email'              => 'owner@demo.com',
            'password'           => \Illuminate\Support\Facades\Hash::make('password'),
            'role'               => 'owner',
            'owner_status'       => 'approved',
            'email_verified_at'  => now(),
        ]);

        User::factory()->create([
            'name'               => 'Normal User',
            'email'              => 'user@demo.com',
            'password'           => \Illuminate\Support\Facades\Hash::make('password'),
            'role'               => 'user',
            'owner_status'       => null,
            'email_verified_at'  => now(),
        ]);
    }
}
