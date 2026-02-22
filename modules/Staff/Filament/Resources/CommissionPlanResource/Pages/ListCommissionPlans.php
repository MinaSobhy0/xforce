<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Staff\Filament\Resources\CommissionPlanResource;

class ListCommissionPlans extends ListRecords
{
    protected static string $resource = CommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
