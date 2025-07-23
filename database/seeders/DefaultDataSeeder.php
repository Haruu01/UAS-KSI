<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::where('email', 'admin@passwordmanager.com')->first();
        if (!$admin) {
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@passwordmanager.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        } else {
            // Update existing user to ensure admin role
            $admin->update([
                'role' => 'admin',
                'is_active' => true,
            ]);
        }

        // Create regular user for testing
        $user = User::firstOrCreate(
            ['email' => 'user@passwordmanager.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('user123'),
                'role' => 'user',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create default categories for admin
        $defaultCategories = [
            [
                'name' => 'Work',
                'color' => '#3B82F6',
                'description' => 'Work-related passwords and accounts',
                'is_default' => true,
            ],
            [
                'name' => 'Personal',
                'color' => '#10B981',
                'description' => 'Personal accounts and services',
                'is_default' => true,
            ],
            [
                'name' => 'Social Media',
                'color' => '#8B5CF6',
                'description' => 'Social media platforms',
                'is_default' => true,
            ],
            [
                'name' => 'Banking',
                'color' => '#F59E0B',
                'description' => 'Banking and financial services',
                'is_default' => true,
            ],
            [
                'name' => 'Shopping',
                'color' => '#EF4444',
                'description' => 'E-commerce and shopping sites',
                'is_default' => true,
            ],
            [
                'name' => 'Entertainment',
                'color' => '#EC4899',
                'description' => 'Streaming services and entertainment',
                'is_default' => true,
            ],
        ];

        foreach ($defaultCategories as $categoryData) {
            // Create for admin user
            Category::firstOrCreate(
                [
                    'user_id' => $admin->id,
                    'name' => $categoryData['name']
                ],
                array_merge($categoryData, ['user_id' => $admin->id])
            );

            // Create for regular user
            Category::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $categoryData['name']
                ],
                array_merge($categoryData, ['user_id' => $user->id])
            );
        }

        $this->command->info('Default data seeded successfully!');
        $this->command->info('Admin User: admin@passwordmanager.com / admin123');
        $this->command->info('Regular User: user@passwordmanager.com / user123');
    }
}
