<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\PasswordEntry;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SecurityStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        
        // Get user-specific stats
        $totalPasswords = PasswordEntry::where('user_id', $user->id)->count();
        $weakPasswords = PasswordEntry::where('user_id', $user->id)
            ->where('password_strength', '<=', 2)
            ->count();
        $expiredPasswords = PasswordEntry::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->count();
        $recentActivity = AuditLog::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $stats = [
            Stat::make('Total Passwords', $totalPasswords)
                ->description('Stored passwords')
                ->descriptionIcon('heroicon-m-key')
                ->color('primary'),

            Stat::make('Weak Passwords', $weakPasswords)
                ->description('Need strengthening')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($weakPasswords > 0 ? 'danger' : 'success'),

            Stat::make('Expired Passwords', $expiredPasswords)
                ->description('Need updating')
                ->descriptionIcon('heroicon-m-clock')
                ->color($expiredPasswords > 0 ? 'warning' : 'success'),

            Stat::make('Recent Activity', $recentActivity)
                ->description('Last 7 days')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info'),
        ];

        // Add admin-specific stats
        if ($user->isAdmin()) {
            $totalUsers = User::count();
            $activeUsers = User::where('is_active', true)->count();
            $criticalEvents = AuditLog::where('severity', 'critical')
                ->where('created_at', '>=', now()->subDays(7))
                ->count();

            $adminStats = [
                Stat::make('Total Users', $totalUsers)
                    ->description('Registered users')
                    ->descriptionIcon('heroicon-m-users')
                    ->color('primary'),

                Stat::make('Active Users', $activeUsers)
                    ->description('Currently active')
                    ->descriptionIcon('heroicon-m-user-plus')
                    ->color('success'),

                Stat::make('Critical Events', $criticalEvents)
                    ->description('Last 7 days')
                    ->descriptionIcon('heroicon-m-exclamation-triangle')
                    ->color($criticalEvents > 0 ? 'danger' : 'success'),
            ];

            $stats = array_merge($stats, $adminStats);
        }

        return $stats;
    }

    protected function getColumns(): int
    {
        return auth()->user()->isAdmin() ? 4 : 2;
    }
}
