<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use App\Models\PasswordEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SecurityAlertsWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Security Events';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AuditLog::query()
                    ->where('severity', 'high')
                    ->orWhere('severity', 'critical')
                    ->orWhere('action', 'like', '%password%')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, H:i')
                    ->sortable(),
                    
                Tables\Columns\BadgeColumn::make('severity')
                    ->colors([
                        'success' => 'low',
                        'info' => 'medium',
                        'warning' => 'high',
                        'danger' => 'critical',
                    ]),
                    
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn ($record) => match($record->severity) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        default => 'success'
                    }),
                    
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->default('System'),
                    
                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                    
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.adminn.resources.audit-logs.view', $record))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false);
    }
    
    public static function canView(): bool
    {
        return auth()->user()?->isAdmin();
    }
}
