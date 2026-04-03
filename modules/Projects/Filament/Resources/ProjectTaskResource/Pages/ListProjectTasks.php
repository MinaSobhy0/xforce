<?php

namespace Modules\Projects\Filament\Resources\ProjectTaskResource\Pages;

use Modules\Projects\Filament\Resources\ProjectTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectTasks extends ListRecords
{
    protected static string $resource = ProjectTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
