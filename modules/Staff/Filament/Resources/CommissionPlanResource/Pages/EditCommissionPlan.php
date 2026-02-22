<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Staff\Filament\Resources\CommissionPlanResource;

class EditCommissionPlan extends EditRecord
{
    protected static string $resource = CommissionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert minor to major units
        $data['default_flat_amount'] = ($data['default_flat_amount_minor'] ?? 0) / 100;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert flat amount to minor units
        if (isset($data['default_flat_amount'])) {
            $data['default_flat_amount_minor'] = (int) round(($data['default_flat_amount'] ?? 0) * 100);
            unset($data['default_flat_amount']);
        }

        return $data;
    }
}
