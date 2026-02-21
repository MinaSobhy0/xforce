<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleResource;

class CreateSalaryRule extends CreateRecord
{
    protected static string $resource = SalaryRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert amount_fixed from major to minor units
        if (isset($data['amount_fixed'])) {
            $data['amount_fixed_minor'] = (int) round($data['amount_fixed'] * 100);
            unset($data['amount_fixed']);
        }

        return $data;
    }
}
