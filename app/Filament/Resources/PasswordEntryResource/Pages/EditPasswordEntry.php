<?php

namespace App\Filament\Resources\PasswordEntryResource\Pages;

use App\Filament\Resources\PasswordEntryResource;
use App\Models\AuditLog;
use App\Services\PasswordSecurityService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Crypt;

class EditPasswordEntry extends EditRecord
{
    protected static string $resource = PasswordEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    AuditLog::log(
                        'password_deleted',
                        $this->record,
                        $this->record->toArray(),
                        [],
                        'high',
                        "Password entry '{$this->record->title}' deleted"
                    );
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Decrypt password for editing
        if (isset($data['encrypted_password'])) {
            try {
                $data['password'] = Crypt::decryptString($data['encrypted_password']);
            } catch (\Exception $e) {
                $data['password'] = '';
            }
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Calculate password strength if password changed
        if (isset($data['password'])) {
            $service = new PasswordSecurityService();
            $data['password_strength'] = $service->calculateStrength($data['password']);

            // Only update password_changed_at if password actually changed
            $currentPassword = '';
            try {
                $currentPassword = Crypt::decryptString($this->record->encrypted_password);
            } catch (\Exception $e) {
                // Handle decryption error
            }

            if ($data['password'] !== $currentPassword) {
                $data['password_changed_at'] = now();
            }

            // Encrypt password
            $data['encrypted_password'] = Crypt::encryptString($data['password']);
            unset($data['password']); // Remove plain password from data
        }

        return $data;
    }

    protected function afterSave(): void
    {
        AuditLog::log(
            'password_updated',
            $this->record,
            $this->record->getOriginal(),
            $this->record->getChanges(),
            'medium',
            "Password entry '{$this->record->title}' updated"
        );
    }
}
