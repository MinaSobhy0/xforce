<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Staff\Filament\Resources\CommissionPlanResource;

class CreateCommissionPlan extends CreateRecord
{
    protected static string $resource = CommissionPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // Convert flat amount to minor units
        if (isset($data['default_flat_amount'])) {
            $data['default_flat_amount_minor'] = (int) round(($data['default_flat_amount'] ?? 0) * 100);
            unset($data['default_flat_amount']);
        }

        return $data;
    }
}
