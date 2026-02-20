<?php

namespace Modules\Marketing\Filament\Resources\MessageTemplateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Marketing\Filament\Resources\MessageTemplateResource;

class CreateMessageTemplate extends CreateRecord
{
    protected static string $resource = MessageTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app('currentTenant')?->id;
        return $data;
    }
}
