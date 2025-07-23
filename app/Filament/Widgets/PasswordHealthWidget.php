<?php

namespace App\Filament\Widgets;

use App\Models\PasswordEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PasswordHealthWidget extends BaseWidget
{
    protected static ?string $heading = 'Password Health Check';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PasswordEntry::query()
                    ->where('user_id', auth()->id())
                    ->where(function ($query) {
                        $query->where('password_strength', '<=', 2) // Weak passwords
                              ->orWhere('expires_at', '<', now()) // Expired passwords
                              ->orWhere('expires_at', '<=', now()->addDays(30)); // Expiring soon
                    })
                    ->with(['category'])
                    ->latest('password_changed_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? '#6B7280'),
                    
                Tables\Columns\ViewColumn::make('password_strength')
                    ->label('Strength')
                    ->view('filament.tables.password-strength'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Issue')
                    ->getStateUsing(function ($record) {
                        if ($record->expires_at && $record->expires_at->isPast()) {
                            return 'Expired';
                        }
                        if ($record->expires_at && $record->expires_at <= now()->addDays(30)) {
                            return 'Expiring Soon';
                        }
                        if ($record->password_strength <= 2) {
                            return 'Weak Password';
                        }
                        return 'OK';
                    })
                    ->colors([
                        'danger' => 'Expired',
                        'warning' => 'Expiring Soon',
                        'danger' => 'Weak Password',
                        'success' => 'OK',
                    ]),
                    
                Tables\Columns\TextColumn::make('password_changed_at')
                    ->label('Last Changed')
                    ->since()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->color(fn ($record) => $record->expires_at && $record->expires_at->isPast() ? 'danger' : 'success')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('issue_type')
                    ->label('Issue Type')
                    ->options([
                        'weak' => 'Weak Passwords',
                        'expired' => 'Expired',
                        'expiring' => 'Expiring Soon',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'weak') {
                            return $query->where('password_strength', '<=', 2);
                        }
                        if ($data['value'] === 'expired') {
                            return $query->where('expires_at', '<', now());
                        }
                        if ($data['value'] === 'expiring') {
                            return $query->where('expires_at', '<=', now()->addDays(30))
                                        ->where('expires_at', '>=', now());
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('fix')
                    ->label('Fix')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->url(fn ($record) => route('filament.adminn.resources.password-entries.edit', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Great! No password issues found')
            ->emptyStateDescription('All your passwords are strong and up to date.')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->paginated([5, 10, 25]);
    }
}
