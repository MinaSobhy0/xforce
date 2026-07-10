<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailListResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailListResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformEmailLists extends ListRecords
{
    protected static string $resource = PlatformEmailListResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
