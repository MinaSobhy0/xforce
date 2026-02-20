<?php

namespace Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Marketing\Filament\Resources\AutomationRuleResource;

class CreateAutomationRule extends CreateRecord
{
    protected static string $resource = AutomationRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('currentTenant')?->id;
        return $data;
    }
}
