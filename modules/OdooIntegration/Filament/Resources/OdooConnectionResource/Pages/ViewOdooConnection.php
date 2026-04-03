<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOdooConnection extends ViewRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
