<?php

namespace Modules\Projects\Filament\Resources\ProjectTaskResource\Pages;

use Modules\Projects\Filament\Resources\ProjectTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProjectTask extends ViewRecord
{
    protected static string $resource = ProjectTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
