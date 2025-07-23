<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PasswordEntryResource\Pages;
use App\Filament\Resources\PasswordEntryResource\RelationManagers;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\PasswordEntry;
use App\Services\PasswordSecurityService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Crypt;

class PasswordEntryResource extends Resource
{
    protected static ?string $model = PasswordEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Password Management';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Password';

    protected static ?string $pluralModelLabel = 'Passwords';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Password Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true),

                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(fn () => Category::where('user_id', auth()->id())->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\ColorPicker::make('color')
                                    ->default('#3B82F6'),
                                Forms\Components\Textarea::make('description')
                                    ->maxLength(500),
                            ])
                            ->createOptionUsing(function (array $data) {
                                $data['user_id'] = auth()->id();
                                return Category::create($data)->id;
                            }),

                        Forms\Components\TextInput::make('username')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('url')
                            ->label('Website URL')
                            ->url()
                            ->maxLength(255)
                            ->suffixIcon('heroicon-m-globe-alt'),
                    ])->columns(2),

                Forms\Components\Section::make('Password & Security')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $service = new PasswordSecurityService();
                                            $strength = $service->calculateStrength($state);
                                            $set('password_strength', $strength);
                                        }
                                    })
                                    ->columnSpan(2),

                                Forms\Components\Actions::make([
                                    Forms\Components\Actions\Action::make('generate')
                                        ->label('Generate')
                                        ->icon('heroicon-m-arrow-path')
                                        ->action(function (Forms\Set $set) {
                                            $service = new PasswordSecurityService();
                                            $password = $service->generatePassword([
                                                'length' => 16,
                                                'uppercase' => true,
                                                'lowercase' => true,
                                                'numbers' => true,
                                                'symbols' => true,
                                            ]);
                                            $strength = $service->calculateStrength($password);
                                            $set('password', $password);
                                            $set('password_strength', $strength);
                                        }),
                                ])->columnSpan(1),
                            ]),

                        Forms\Components\ViewField::make('password_strength')
                            ->label('Password Strength')
                            ->view('filament.forms.password-strength')
                            ->visible(fn ($get) => $get('password_strength')),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Password Expires At')
                            ->helperText('Set when this password should be changed'),

                        Forms\Components\Toggle::make('is_favorite')
                            ->label('Mark as Favorite'),
                    ]),

                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000)
                            ->rows(4)
                            ->helperText('Additional notes about this password entry'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_favorite')
                    ->label('★')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('username')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('url')
                    ->label('Website')
                    ->url(fn ($record) => $record->url)
                    ->openUrlInNewTab()
                    ->icon('heroicon-m-globe-alt')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->color ?? '#6B7280')
                    ->sortable(),

                Tables\Columns\ViewColumn::make('password_strength')
                    ->label('Strength')
                    ->view('filament.tables.password-strength')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_accessed_at')
                    ->label('Last Accessed')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->color(fn ($record) => $record->expires_at && $record->expires_at->isPast() ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::where('user_id', auth()->id())->pluck('name', 'id'))
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_favorite')
                    ->label('Favorites'),

                Tables\Filters\Filter::make('expires_soon')
                    ->label('Expires Soon')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<=', now()->addDays(30)))
                    ->toggle(),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query) => $query->where('expires_at', '<', now()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('view_password')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->action(function (PasswordEntry $record) {
                        $record->markAsAccessed();
                        AuditLog::log(
                            'password_viewed',
                            $record,
                            [],
                            ['title' => $record->title],
                            'medium',
                            "Password '{$record->title}' viewed"
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('View Password')
                    ->modalDescription(fn ($record) => "Are you sure you want to view the password for '{$record->title}'?")
                    ->modalSubmitActionLabel('View Password'),

                Tables\Actions\Action::make('copy_password')
                    ->label('Copy')
                    ->icon('heroicon-m-clipboard')
                    ->color('success')
                    ->action(function (PasswordEntry $record) {
                        $record->markAsAccessed();
                        AuditLog::log(
                            'password_copied',
                            $record,
                            [],
                            ['title' => $record->title],
                            'medium',
                            "Password '{$record->title}' copied to clipboard"
                        );
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Copy Password')
                    ->modalDescription(fn ($record) => "Copy password for '{$record->title}' to clipboard?"),

                Tables\Actions\EditAction::make()
                    ->before(function (PasswordEntry $record) {
                        AuditLog::log('password_edit_started', $record);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (PasswordEntry $record) {
                        AuditLog::log(
                            'password_deleted',
                            $record,
                            $record->toArray(),
                            [],
                            'high',
                            "Password '{$record->title}' deleted"
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                AuditLog::log('password_bulk_delete', $record);
                            }
                        }),
                ]),
            ])
            ->defaultSort('title');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', auth()->id())
            ->with(['category']);
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
            'index' => Pages\ListPasswordEntries::route('/'),
            'create' => Pages\CreatePasswordEntry::route('/create'),
            'edit' => Pages\EditPasswordEntry::route('/{record}/edit'),
        ];
    }
}
