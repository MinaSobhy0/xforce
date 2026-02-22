<?php

namespace Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource\Pages;

use Modules\TreatmentPlans\Filament\Resources\TreatmentPlanResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListTreatmentPlans extends BaseListRecords
{
    protected static string $resource = TreatmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
