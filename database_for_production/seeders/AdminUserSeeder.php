<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@livestream.vps'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => UserRole::ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'role' => UserRole::USER,
                'email_verified_at' => now(),
            ]
        );
    }
}
