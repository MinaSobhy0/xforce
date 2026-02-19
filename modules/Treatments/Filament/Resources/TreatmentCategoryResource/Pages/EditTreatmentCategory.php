<?php

namespace Modules\Treatments\Filament\Resources\TreatmentCategoryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Treatments\Filament\Resources\TreatmentCategoryResource;

class EditTreatmentCategory extends EditRecord
{
    protected static string $resource = TreatmentCategoryResource::class;

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
