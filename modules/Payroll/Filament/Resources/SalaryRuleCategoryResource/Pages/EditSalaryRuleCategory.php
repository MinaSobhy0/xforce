<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource;

class EditSalaryRuleCategory extends BaseEditRecord
{
    protected static string $resource = SalaryRuleCategoryResource::class;

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
}
