<?php

namespace Modules\Booking\Filament\Resources\TimeOffAllocationResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Booking\Filament\Resources\TimeOffAllocationResource;

class ListTimeOffAllocations extends ListRecords
{
    protected static string $resource = TimeOffAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
