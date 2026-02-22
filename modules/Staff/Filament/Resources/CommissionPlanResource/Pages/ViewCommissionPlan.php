<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Staff\Filament\Resources\CommissionPlanResource;

class ViewCommissionPlan extends BaseViewRecord
{
    protected static string $resource = CommissionPlanResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
