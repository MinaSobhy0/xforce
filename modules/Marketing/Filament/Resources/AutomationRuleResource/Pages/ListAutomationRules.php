<?php

namespace Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Marketing\Filament\Resources\AutomationRuleResource;

class ListAutomationRules extends BaseListRecords
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\CreateAction::make(),
        ];
    }
}
