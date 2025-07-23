<?php

namespace App\Filament\Resources\SharedPasswordResource\Pages;

use App\Filament\Resources\SharedPasswordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSharedPassword extends EditRecord
{
    protected static string $resource = SharedPasswordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
