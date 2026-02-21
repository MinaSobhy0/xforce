<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource;

class ViewSalaryRuleCategory extends BaseViewRecord
{
    protected static string $resource = SalaryRuleCategoryResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
