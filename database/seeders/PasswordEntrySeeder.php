<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\PasswordEntry;
use App\Models\User;
use App\Services\PasswordSecurityService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class PasswordEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $passwordService = new PasswordSecurityService();

        // Get admin and regular user
        $admin = User::where('email', 'admin@passwordmanager.com')->first();
        $user = User::where('email', 'user@passwordmanager.com')->first();

        if (!$admin || !$user) {
            $this->command->error('Admin or user not found. Please run DefaultDataSeeder first.');
            return;
        }

        // Sample password entries for admin
        $adminPasswords = [
            [
                'title' => 'GitHub Account',
                'username' => 'admin@passwordmanager.com',
                'password' => 'MySecureGitHub2024!',
                'url' => 'https://github.com',
                'notes' => 'Main GitHub account for development projects',
                'category' => 'Work',
                'is_favorite' => true,
            ],
            [
                'title' => 'AWS Console',
                'username' => 'admin',
                'password' => 'AWSSecure#2024$',
                'url' => 'https://aws.amazon.com',
                'notes' => 'AWS root account - handle with care',
                'category' => 'Work',
                'expires_at' => now()->addMonths(3),
            ],
            [
                'title' => 'Gmail Personal',
                'username' => 'admin.personal@gmail.com',
                'password' => 'Gmail@Strong123',
                'url' => 'https://gmail.com',
                'notes' => 'Personal email account',
                'category' => 'Personal',
            ],
            [
                'title' => 'Netflix',
                'username' => 'admin@passwordmanager.com',
                'password' => 'Netflix2024!',
                'url' => 'https://netflix.com',
                'notes' => 'Family Netflix account',
                'category' => 'Entertainment',
                'is_favorite' => true,
            ],
            [
                'title' => 'Bank Account',
                'username' => 'admin123',
                'password' => 'BankSecure@2024#',
                'url' => 'https://bank.com',
                'notes' => 'Primary bank account - very sensitive',
                'category' => 'Banking',
                'expires_at' => now()->addMonths(6),
            ],
        ];

        // Sample password entries for regular user
        $userPasswords = [
            [
                'title' => 'Facebook',
                'username' => 'user@passwordmanager.com',
                'password' => 'Facebook123!',
                'url' => 'https://facebook.com',
                'notes' => 'Social media account',
                'category' => 'Social Media',
            ],
            [
                'title' => 'LinkedIn',
                'username' => 'user@passwordmanager.com',
                'password' => 'LinkedIn@2024',
                'url' => 'https://linkedin.com',
                'notes' => 'Professional networking',
                'category' => 'Work',
                'is_favorite' => true,
            ],
            [
                'title' => 'Amazon Shopping',
                'username' => 'user@passwordmanager.com',
                'password' => 'Amazon#Shop2024',
                'url' => 'https://amazon.com',
                'notes' => 'Online shopping account',
                'category' => 'Shopping',
            ],
        ];

        // Create password entries for admin
        foreach ($adminPasswords as $passwordData) {
            $category = Category::where('user_id', $admin->id)
                ->where('name', $passwordData['category'])
                ->first();

            $password = $passwordData['password'];
            $strength = $passwordService->calculateStrength($password);

            PasswordEntry::create([
                'user_id' => $admin->id,
                'category_id' => $category?->id,
                'title' => $passwordData['title'],
                'username' => $passwordData['username'],
                'encrypted_password' => Crypt::encryptString($password),
                'url' => $passwordData['url'],
                'notes' => $passwordData['notes'],
                'is_favorite' => $passwordData['is_favorite'] ?? false,
                'password_strength' => $strength,
                'password_changed_at' => now(),
                'expires_at' => $passwordData['expires_at'] ?? null,
            ]);
        }

        // Create password entries for regular user
        foreach ($userPasswords as $passwordData) {
            $category = Category::where('user_id', $user->id)
                ->where('name', $passwordData['category'])
                ->first();

            $password = $passwordData['password'];
            $strength = $passwordService->calculateStrength($password);

            PasswordEntry::create([
                'user_id' => $user->id,
                'category_id' => $category?->id,
                'title' => $passwordData['title'],
                'username' => $passwordData['username'],
                'encrypted_password' => Crypt::encryptString($password),
                'url' => $passwordData['url'],
                'notes' => $passwordData['notes'],
                'is_favorite' => $passwordData['is_favorite'] ?? false,
                'password_strength' => $strength,
                'password_changed_at' => now(),
                'expires_at' => $passwordData['expires_at'] ?? null,
            ]);
        }

        $this->command->info('Sample password entries created successfully!');
    }
}
