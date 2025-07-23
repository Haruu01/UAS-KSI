<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SharedPasswordResource\Pages;
use App\Filament\Resources\SharedPasswordResource\RelationManagers;
use App\Models\AuditLog;
use App\Models\PasswordEntry;
use App\Models\SharedPassword;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SharedPasswordResource extends Resource
{
    protected static ?string $model = SharedPassword::class;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'Password Management';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Shared Password';

    protected static ?string $pluralModelLabel = 'Shared Passwords';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Share Password')
                    ->schema([
                        Forms\Components\Select::make('password_entry_id')
                            ->label('Password to Share')
                            ->options(function () {
                                return PasswordEntry::where('user_id', auth()->id())
                                    ->with('category')
                                    ->get()
                                    ->mapWithKeys(function ($entry) {
                                        $category = $entry->category ? " ({$entry->category->name})" : '';
                                        return [$entry->id => $entry->title . $category];
                                    });
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('shared_with_user_id')
                            ->label('Share With User')
                            ->options(function () {
                                return User::where('id', '!=', auth()->id())
                                    ->where('is_active', true)
                                    ->pluck('name', 'id');
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('permission')
                            ->options([
                                'read' => 'Read Only',
                                'write' => 'Read & Write',
                            ])
                            ->default('read')
                            ->required()
                            ->helperText('Read Only: User can view the password. Read & Write: User can also edit the password.'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->helperText('Leave empty for permanent sharing')
                            ->minDate(now()),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive shares cannot be accessed'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('passwordEntry.title')
                    ->label('Password')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sharedWith.name')
                    ->label('Shared With')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('permission')
                    ->colors([
                        'success' => 'read',
                        'warning' => 'write',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$state) return 'Never';
                        return $record->isExpired() ? 'Expired' : $state->diffForHumans();
                    }),

                Tables\Columns\TextColumn::make('last_accessed_at')
                    ->label('Last Accessed')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Shared At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('permission')
                    ->options([
                        'read' => 'Read Only',
                        'write' => 'Read & Write',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->action(function (SharedPassword $record) {
                        $record->update(['is_active' => false]);

                        AuditLog::log(
                            'password_share_revoked',
                            $record,
                            ['is_active' => true],
                            ['is_active' => false],
                            'medium',
                            "Password share revoked for '{$record->passwordEntry->title}'"
                        );
                    })
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->is_active),

                Tables\Actions\EditAction::make()
                    ->before(function (SharedPassword $record) {
                        AuditLog::log('password_share_edit_started', $record);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (SharedPassword $record) {
                        AuditLog::log(
                            'password_share_deleted',
                            $record,
                            $record->toArray(),
                            [],
                            'medium',
                            "Password share deleted for '{$record->passwordEntry->title}'"
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                AuditLog::log('password_share_bulk_delete', $record);
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('shared_by_user_id', auth()->id())
            ->with(['passwordEntry', 'sharedWith']);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSharedPasswords::route('/'),
            'create' => Pages\CreateSharedPassword::route('/create'),
            'edit' => Pages\EditSharedPassword::route('/{record}/edit'),
        ];
    }
}
