<?php

namespace App\Filament\SuperAdmin\Resources\TenantAppCodeResource\Pages;

use App\Filament\SuperAdmin\Resources\TenantAppCodeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantAppCode extends EditRecord
{
    protected static string $resource = TenantAppCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
