<?php

namespace Modules\Treatments\Filament\Resources\TreatmentCategoryResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Treatments\Filament\Resources\TreatmentCategoryResource;

class ListTreatmentCategories extends ListRecords
{
    protected static string $resource = TreatmentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
