<?php

namespace App\Filament\Resources\SharedPasswordResource\Pages;

use App\Filament\Resources\SharedPasswordResource;
use App\Models\AuditLog;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSharedPassword extends CreateRecord
{
    protected static string $resource = SharedPasswordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['shared_by_user_id'] = auth()->id();
        return $data;
    }

    protected function afterCreate(): void
    {
        AuditLog::log(
            'password_shared',
            $this->record,
            [],
            [
                'password_title' => $this->record->passwordEntry->title,
                'shared_with' => $this->record->sharedWith->name,
                'permission' => $this->record->permission,
            ],
            'medium',
            "Password '{$this->record->passwordEntry->title}' shared with {$this->record->sharedWith->name}"
        );
    }
}
