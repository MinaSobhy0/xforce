<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOdooEntityMapping extends CreateRecord
{
    protected static string $resource = OdooEntityMappingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = current_tenant_id();
        return $data;
    }
}
