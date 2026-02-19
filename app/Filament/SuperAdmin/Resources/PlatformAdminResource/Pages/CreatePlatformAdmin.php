<?php

namespace App\Filament\SuperAdmin\Resources\PlatformAdminResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformAdminResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAdmin extends CreateRecord
{
    protected static string $resource = PlatformAdminResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = null; // Ensure platform admin has no tenant
        return $data;
    }
}
