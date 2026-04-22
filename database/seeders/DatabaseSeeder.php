<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles and Terms
        $this->call([
            RolesTableSeeder::class,
            TermsAndConditionSeeder::class,
        ]);

        // 2. Admin User
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $admin->assignRole('admin');

        // 3. Trainer / Coach
        $trainer = User::updateOrCreate(
            ['email' => 'trainer@example.com'],
            [
                'name' => 'Trainer User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'professional',
                'profession_type' => 'trainer_coach',
            ]
        );
        $trainer->assignRole('professional');

        // 4. Nutritionist
        $nutritionist = User::updateOrCreate(
            ['email' => 'nutritionist@example.com'],
            [
                'name' => 'Nutritionist User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'professional',
                'profession_type' => 'nutritionist',
            ]
        );
        $nutritionist->assignRole('professional');

        // 5. Supplement Supplier
        $supplier = User::updateOrCreate(
            ['email' => 'supplier@example.com'],
            [
                'name' => 'Supplement Supplier',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'professional',
                'profession_type' => 'supplement_supplier',
            ]
        );
        $supplier->assignRole('professional');

        // 6. Individual User
        $individual = User::updateOrCreate(
            ['email' => 'individual@example.com'],
            [
                'name' => 'Individual User',
                'password' => Hash::make('Password123!'),
                'email_verified_at' => now(),
                'status' => 'active',
                'user_type' => 'individual',
                'profession_type' => null,
            ]
        );
        $individual->assignRole('individual');

        // 7. Plans Seeder
        $this->call([
            PlansSeeder::class,
        ]);
    }
}