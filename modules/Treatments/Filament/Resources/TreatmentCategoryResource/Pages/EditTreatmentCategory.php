<?php

namespace Modules\Treatments\Filament\Resources\TreatmentCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Treatments\Filament\Resources\TreatmentCategoryResource;

class EditTreatmentCategory extends BaseEditRecord
{
    protected static string $resource = TreatmentCategoryResource::class;

    protected function getEditHeaderActions(): array
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
