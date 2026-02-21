<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource;

class CreateSalaryRuleCategory extends CreateRecord
{
    protected static string $resource = SalaryRuleCategoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
