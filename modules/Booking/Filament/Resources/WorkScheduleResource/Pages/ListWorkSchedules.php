<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\Pages;

use Modules\Booking\Filament\Resources\WorkScheduleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkSchedules extends ListRecords
{
    protected static string $resource = WorkScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
