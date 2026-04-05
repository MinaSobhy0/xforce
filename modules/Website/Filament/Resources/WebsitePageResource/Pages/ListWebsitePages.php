<?php

namespace Modules\Website\Filament\Resources\WebsitePageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Website\Filament\Resources\WebsitePageResource;

class ListWebsitePages extends ListRecords
{
    protected static string $resource = WebsitePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
