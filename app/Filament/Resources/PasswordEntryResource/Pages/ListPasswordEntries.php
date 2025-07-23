<?php

namespace App\Filament\Resources\PasswordEntryResource\Pages;

use App\Filament\Resources\PasswordEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPasswordEntries extends ListRecords
{
    protected static string $resource = PasswordEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
