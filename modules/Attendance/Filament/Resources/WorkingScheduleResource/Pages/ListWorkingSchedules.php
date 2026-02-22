<?php

namespace Modules\Attendance\Filament\Resources\WorkingScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource;

class ListWorkingSchedules extends ListRecords
{
    protected static string $resource = WorkingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
