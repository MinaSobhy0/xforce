<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOdooConnection extends CreateRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = current_tenant_id();

        return $data;
    }
}
