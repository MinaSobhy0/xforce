<?php

namespace App\Filament\SuperAdmin\Resources\OwnerUserResource\Pages;

use App\Filament\SuperAdmin\Resources\OwnerUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOwnerUser extends EditRecord
{
    protected static string $resource = OwnerUserResource::class;

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
