<?php

namespace Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseEditRecord;
use Modules\Marketing\Filament\Resources\AutomationRuleResource;

class EditAutomationRule extends BaseEditRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
