<?php

namespace Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Modules\Marketing\Filament\Resources\AutomationRuleResource;

class EditAutomationRule extends EditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
