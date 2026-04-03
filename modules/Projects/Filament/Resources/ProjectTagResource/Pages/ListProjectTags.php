<?php

namespace Modules\Projects\Filament\Resources\ProjectTagResource\Pages;

use Modules\Projects\Filament\Resources\ProjectTagResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectTags extends ListRecords
{
    protected static string $resource = ProjectTagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
