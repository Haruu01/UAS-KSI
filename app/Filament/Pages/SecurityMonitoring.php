<?php

namespace App\Filament\Pages;

use App\Services\DatabaseSecurityService;
use App\Services\KeyManagementService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SecurityMonitoring extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationGroup = 'Security & Monitoring';
    
    protected static ?int $navigationSort = 2;
    
    protected static ?string $navigationLabel = 'Security Status';
    
    protected static string $view = 'filament.pages.security-monitoring';
    
    public array $securityStatus = [];
    public array $keyStatus = [];
    public array $integrityIssues = [];
    
    public function mount(): void
    {
        $this->loadSecurityStatus();
    }
    
    public function loadSecurityStatus(): void
    {
        $dbService = new DatabaseSecurityService();
        $keyService = new KeyManagementService();
        
        $this->securityStatus = $dbService->getSecurityStatus();
        $this->keyStatus = $keyService->getKeyRotationStatus();
        $this->integrityIssues = $dbService->verifyDatabaseIntegrity();
    }
    
    public function runIntegrityCheck(): void
    {
        $dbService = new DatabaseSecurityService();
        $this->integrityIssues = $dbService->verifyDatabaseIntegrity();
        
        if (empty($this->integrityIssues)) {
            Notification::make()
                ->title('Database integrity check passed')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Database integrity issues found')
                ->body(count($this->integrityIssues) . ' issues detected')
                ->warning()
                ->send();
        }
    }
    
    public function encryptSensitiveData(): void
    {
        $dbService = new DatabaseSecurityService();
        
        if ($dbService->encryptSensitiveColumns()) {
            Notification::make()
                ->title('Sensitive data encrypted successfully')
                ->success()
                ->send();
                
            $this->loadSecurityStatus();
        } else {
            Notification::make()
                ->title('Failed to encrypt sensitive data')
                ->danger()
                ->send();
        }
    }
    
    public function createDatabaseBackup(): void
    {
        try {
            $dbService = new DatabaseSecurityService();
            $backupPath = $dbService->createEncryptedBackup('backup_password_' . time());
            
            Notification::make()
                ->title('Database backup created successfully')
                ->body('Backup saved to: ' . basename($backupPath))
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            Notification::make()
                ->title('Database backup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
    
    public function cleanupAuditLogs(): void
    {
        $dbService = new DatabaseSecurityService();
        $deletedCount = $dbService->cleanupOldAuditLogs(90);
        
        Notification::make()
            ->title('Audit logs cleaned up')
            ->body("Removed {$deletedCount} old audit log entries")
            ->success()
            ->send();
            
        $this->loadSecurityStatus();
    }
    
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin();
    }
    
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Status')
                ->icon('heroicon-o-arrow-path')
                ->action('loadSecurityStatus'),
                
            Action::make('integrityCheck')
                ->label('Run Integrity Check')
                ->icon('heroicon-o-shield-check')
                ->color('info')
                ->action('runIntegrityCheck'),
                
            Action::make('encryptData')
                ->label('Encrypt Sensitive Data')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Encrypt Sensitive Data')
                ->modalDescription('This will encrypt any unencrypted sensitive data in the database.')
                ->action('encryptSensitiveData'),
                
            Action::make('createBackup')
                ->label('Create Backup')
                ->icon('heroicon-o-archive-box')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Create Database Backup')
                ->modalDescription('This will create an encrypted backup of the entire database.')
                ->action('createDatabaseBackup'),
                
            Action::make('cleanupLogs')
                ->label('Cleanup Old Logs')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cleanup Old Audit Logs')
                ->modalDescription('This will remove audit logs older than 90 days (low severity only).')
                ->action('cleanupAuditLogs'),
        ];
    }
}
