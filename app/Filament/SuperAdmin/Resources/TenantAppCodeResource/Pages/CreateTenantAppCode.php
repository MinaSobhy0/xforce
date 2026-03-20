<?php

namespace App\Filament\SuperAdmin\Resources\TenantAppCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantAppCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantAppCode extends CreateRecord
{
    protected static string $resource = TenantAppCodeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
