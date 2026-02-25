<?php

namespace Modules\Prescriptions\Filament\Resources\MedicineCatalogResource\Pages;

use Modules\Prescriptions\Filament\Resources\MedicineCatalogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMedicineCatalog extends EditRecord
{
    protected static string $resource = MedicineCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->canDelete()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
