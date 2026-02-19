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

        // Split name into first_name and last_name
        if (isset($data['name'])) {
            $nameParts = explode(' ', trim($data['name']), 2);
            $data['first_name'] = $nameParts[0];
            $data['last_name'] = $nameParts[1] ?? '';
            unset($data['name']);
        }

        return $data;
    }
}
