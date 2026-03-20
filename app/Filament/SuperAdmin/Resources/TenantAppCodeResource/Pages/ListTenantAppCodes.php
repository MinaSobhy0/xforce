<?php

namespace App\Filament\SuperAdmin\Resources\TenantAppCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantAppCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantAppCodes extends ListRecords
{
    protected static string $resource = TenantAppCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
