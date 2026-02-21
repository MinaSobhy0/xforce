<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleResource;

class EditSalaryRule extends BaseEditRecord
{
    protected static string $resource = SalaryRuleResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert amount_fixed from major to minor units
        if (isset($data['amount_fixed'])) {
            $data['amount_fixed_minor'] = (int) round($data['amount_fixed'] * 100);
            unset($data['amount_fixed']);
        }

        return $data;
    }
}
