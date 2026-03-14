<?php

namespace App\Filament\SuperAdmin\Resources\OwnerUserResource\Pages;

use App\Filament\SuperAdmin\Resources\OwnerUserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOwnerUser extends CreateRecord
{
    protected static string $resource = OwnerUserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
