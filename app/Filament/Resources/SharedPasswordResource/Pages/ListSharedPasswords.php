<?php

namespace App\Filament\Resources\SharedPasswordResource\Pages;

use App\Filament\Resources\SharedPasswordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSharedPasswords extends ListRecords
{
    protected static string $resource = SharedPasswordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
