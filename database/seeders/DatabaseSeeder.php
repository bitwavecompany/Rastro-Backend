<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@rastro.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);
    }
}