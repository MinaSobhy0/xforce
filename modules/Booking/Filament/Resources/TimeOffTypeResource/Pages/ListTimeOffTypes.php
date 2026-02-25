<?php

namespace Modules\Booking\Filament\Resources\TimeOffTypeResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Booking\Filament\Resources\TimeOffTypeResource;

class ListTimeOffTypes extends ListRecords
{
    protected static string $resource = TimeOffTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
