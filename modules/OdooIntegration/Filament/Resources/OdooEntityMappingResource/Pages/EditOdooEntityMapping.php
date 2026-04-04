<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOdooEntityMapping extends EditRecord
{
    protected static string $resource = OdooEntityMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
