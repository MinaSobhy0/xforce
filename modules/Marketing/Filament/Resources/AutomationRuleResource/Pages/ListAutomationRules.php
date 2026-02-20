<?php

namespace Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Marketing\Filament\Resources\AutomationRuleResource;

class ListAutomationRules extends ListRecords
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
