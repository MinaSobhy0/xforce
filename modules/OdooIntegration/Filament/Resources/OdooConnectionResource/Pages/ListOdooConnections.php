<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOdooConnections extends ListRecords
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
