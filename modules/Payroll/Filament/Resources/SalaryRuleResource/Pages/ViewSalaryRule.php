<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;
use Modules\Payroll\Filament\Resources\SalaryRuleResource;

class ViewSalaryRule extends BaseViewRecord
{
    protected static string $resource = SalaryRuleResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
