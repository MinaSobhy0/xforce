<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Staff\Filament\Resources\CommissionPlanResource;

class ListCommissionPlans extends BaseListRecords
{
    protected static string $resource = CommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
