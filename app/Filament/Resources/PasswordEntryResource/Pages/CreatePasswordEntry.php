<?php

namespace App\Filament\Resources\PasswordEntryResource\Pages;

use App\Filament\Resources\PasswordEntryResource;
use App\Models\AuditLog;
use App\Services\PasswordSecurityService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Crypt;

class CreatePasswordEntry extends CreateRecord
{
    protected static string $resource = PasswordEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Calculate password strength
        if (isset($data['password'])) {
            $service = new PasswordSecurityService();
            $data['password_strength'] = $service->calculateStrength($data['password']);
            $data['password_changed_at'] = now();

            // Encrypt password
            $data['encrypted_password'] = Crypt::encryptString($data['password']);
            unset($data['password']); // Remove plain password from data
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::log(
            'password_created',
            $this->record,
            [],
            [
                'title' => $this->record->title,
                'category' => $this->record->category?->name,
                'strength' => $this->record->password_strength,
            ],
            'medium',
            "Password entry '{$this->record->title}' created"
        );
    }
}
