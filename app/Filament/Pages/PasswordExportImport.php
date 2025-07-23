<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\PasswordEntry;
use App\Services\PasswordSecurityService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class PasswordExportImport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    
    protected static ?string $navigationGroup = 'Password Management';
    
    protected static ?int $navigationSort = 4;
    
    protected static ?string $navigationLabel = 'Export/Import';
    
    protected static string $view = 'filament.pages.password-export-import';
    
    public ?string $exportPassword = null;
    public ?string $importPassword = null;
    public $importFile = null;
    
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('exportPassword')
                    ->label('Export Password')
                    ->password()
                    ->required()
                    ->helperText('This password will be used to encrypt your exported data'),
                    
                TextInput::make('importPassword')
                    ->label('Import Password')
                    ->password()
                    ->helperText('Enter the password used to encrypt the import file'),
                    
                FileUpload::make('importFile')
                    ->label('Import File')
                    ->acceptedFileTypes(['application/json'])
                    ->maxSize(10240) // 10MB
                    ->helperText('Select an encrypted JSON file to import'),
            ]);
    }
    
    public function exportPasswords()
    {
        if (!$this->exportPassword) {
            Notification::make()
                ->title('Export password is required')
                ->danger()
                ->send();
            return;
        }
        
        $user = auth()->user();
        $passwords = PasswordEntry::where('user_id', $user->id)
            ->with(['category'])
            ->get();
            
        $categories = Category::where('user_id', $user->id)->get();
        
        // Prepare export data
        $exportData = [
            'version' => '1.0',
            'exported_at' => now()->toISOString(),
            'user_email' => $user->email,
            'categories' => $categories->map(function ($category) {
                return [
                    'name' => $category->name,
                    'color' => $category->color,
                    'description' => $category->description,
                    'is_default' => $category->is_default,
                ];
            })->toArray(),
            'passwords' => $passwords->map(function ($password) {
                return [
                    'title' => $password->title,
                    'username' => $password->username,
                    'password' => Crypt::decryptString($password->encrypted_password),
                    'url' => $password->url,
                    'notes' => $password->notes,
                    'category_name' => $password->category?->name,
                    'is_favorite' => $password->is_favorite,
                    'password_strength' => $password->password_strength,
                    'created_at' => $password->created_at->toISOString(),
                ];
            })->toArray(),
        ];
        
        // Encrypt the export data
        try {
            $encryptedData = Crypt::encryptString(json_encode($exportData));
            
            $filename = 'password_export_' . now()->format('Y-m-d_H-i-s') . '.json';
            $filePath = 'exports/' . $filename;
            
            Storage::disk('local')->put($filePath, $encryptedData);
            
            AuditLog::log(
                'passwords_exported',
                null,
                [],
                ['password_count' => count($exportData['passwords'])],
                'medium',
                "Exported " . count($exportData['passwords']) . " passwords"
            );
            
            Notification::make()
                ->title('Passwords exported successfully')
                ->success()
                ->send();
                
            // Download the file
            return response()->download(storage_path('app/' . $filePath), $filename)->deleteFileAfterSend();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Export failed')
                ->body('An error occurred while exporting passwords')
                ->danger()
                ->send();
        }
    }
    
    public function importPasswords(): void
    {
        if (!$this->importPassword || !$this->importFile) {
            Notification::make()
                ->title('Import password and file are required')
                ->danger()
                ->send();
            return;
        }
        
        try {
            $filePath = Storage::disk('local')->path($this->importFile);
            $encryptedData = file_get_contents($filePath);
            
            // Decrypt the data
            $decryptedData = Crypt::decryptString($encryptedData);
            $importData = json_decode($decryptedData, true);
            
            if (!$importData || !isset($importData['passwords'])) {
                throw new \Exception('Invalid import file format');
            }
            
            $user = auth()->user();
            $importedCount = 0;
            $skippedCount = 0;
            
            // Import categories first
            foreach ($importData['categories'] ?? [] as $categoryData) {
                Category::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'name' => $categoryData['name']
                    ],
                    [
                        'color' => $categoryData['color'] ?? '#3B82F6',
                        'description' => $categoryData['description'] ?? '',
                        'is_default' => false, // Don't override default categories
                    ]
                );
            }
            
            // Import passwords
            $passwordService = new PasswordSecurityService();
            
            foreach ($importData['passwords'] as $passwordData) {
                // Check if password already exists
                $existing = PasswordEntry::where('user_id', $user->id)
                    ->where('title', $passwordData['title'])
                    ->first();
                    
                if ($existing) {
                    $skippedCount++;
                    continue;
                }
                
                // Find category
                $category = null;
                if (!empty($passwordData['category_name'])) {
                    $category = Category::where('user_id', $user->id)
                        ->where('name', $passwordData['category_name'])
                        ->first();
                }
                
                // Calculate password strength
                $strength = $passwordService->calculateStrength($passwordData['password']);
                
                PasswordEntry::create([
                    'user_id' => $user->id,
                    'category_id' => $category?->id,
                    'title' => $passwordData['title'],
                    'username' => $passwordData['username'] ?? '',
                    'encrypted_password' => Crypt::encryptString($passwordData['password']),
                    'url' => $passwordData['url'] ?? '',
                    'notes' => $passwordData['notes'] ?? '',
                    'is_favorite' => $passwordData['is_favorite'] ?? false,
                    'password_strength' => $strength,
                    'password_changed_at' => now(),
                ]);
                
                $importedCount++;
            }
            
            AuditLog::log(
                'passwords_imported',
                null,
                [],
                [
                    'imported_count' => $importedCount,
                    'skipped_count' => $skippedCount,
                ],
                'medium',
                "Imported {$importedCount} passwords, skipped {$skippedCount} duplicates"
            );
            
            Notification::make()
                ->title('Import completed')
                ->body("Imported {$importedCount} passwords, skipped {$skippedCount} duplicates")
                ->success()
                ->send();
                
            // Clear form
            $this->importPassword = null;
            $this->importFile = null;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'password_import_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Password import failed: ' . $e->getMessage()
            );
            
            Notification::make()
                ->title('Import failed')
                ->body('Invalid file or incorrect password')
                ->danger()
                ->send();
        }
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Passwords')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Export Passwords')
                ->modalDescription('This will export all your passwords in an encrypted format.')
                ->action('exportPasswords'),
                
            Action::make('import')
                ->label('Import Passwords')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Import Passwords')
                ->modalDescription('This will import passwords from an encrypted file. Existing passwords with the same title will be skipped.')
                ->action('importPasswords'),
        ];
    }
}
