<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Models\AuditLog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function () {
                    AuditLog::log(
                        'category_deleted',
                        $this->record,
                        $this->record->toArray(),
                        [],
                        'medium',
                        "Category '{$this->record->name}' deleted"
                    );
                }),
        ];
    }

    protected function afterSave(): void
    {
        AuditLog::log(
            'category_updated',
            $this->record,
            $this->record->getOriginal(),
            $this->record->getChanges(),
            'low',
            "Category '{$this->record->name}' updated"
        );
    }
}
