<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooEntityMappingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOdooEntityMappings extends ListRecords
{
    protected static string $resource = OdooEntityMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
