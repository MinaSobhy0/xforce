<?php

namespace Modules\Treatments\Filament\Resources\TreatmentCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Treatments\Filament\Resources\TreatmentCategoryResource;

class ListTreatmentCategories extends BaseListRecords
{
    protected static string $resource = TreatmentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
