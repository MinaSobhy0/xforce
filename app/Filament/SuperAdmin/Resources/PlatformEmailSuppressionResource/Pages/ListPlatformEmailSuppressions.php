<?php

namespace App\Filament\SuperAdmin\Resources\PlatformEmailSuppressionResource\Pages;

use App\Filament\SuperAdmin\Resources\PlatformEmailSuppressionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlatformEmailSuppressions extends ListRecords
{
    protected static string $resource = PlatformEmailSuppressionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Add suppression')];
    }
}
