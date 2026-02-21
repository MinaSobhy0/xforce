<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Payroll\Filament\Resources\SalaryRuleResource;

class ListSalaryRules extends BaseListRecords
{
    protected static string $resource = SalaryRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
