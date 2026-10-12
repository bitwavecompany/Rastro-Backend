<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\UserPlan;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@rastro.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('admin123'),
                'role' => UserRole::Admin->value,
                'plan' => UserPlan::Premium->value,
            ]
        );

        $admin->accounts()->firstOrCreate(
            ['name' => 'Efectivo', 'is_default' => true],
            [
                'type' => AccountType::Cash,
                'initial_balance' => 0.00,
            ]
        );

        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'role' => UserRole::Client->value,
                'plan' => UserPlan::Free->value,
            ]
        );

        $testUser->accounts()->firstOrCreate(
            ['name' => 'Efectivo', 'is_default' => true],
            [
                'type' => AccountType::Cash,
                'initial_balance' => 0.00,
            ]
        );
    }
}
