<?php

namespace Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Payroll\Filament\Resources\SalaryRuleCategoryResource;

class ListSalaryRuleCategories extends BaseListRecords
{
    protected static string $resource = SalaryRuleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
