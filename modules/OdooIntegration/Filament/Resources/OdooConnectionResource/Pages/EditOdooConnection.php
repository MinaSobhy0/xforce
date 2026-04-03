<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\Pages;

use Modules\OdooIntegration\Filament\Resources\OdooConnectionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOdooConnection extends EditRecord
{
    protected static string $resource = OdooConnectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
