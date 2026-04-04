<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOdooEntityMapping extends ViewRecord
{
    protected static string $resource = OdooEntityMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
