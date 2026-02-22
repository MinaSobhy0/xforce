<?php

namespace Modules\Attendance\Filament\Resources\WorkingScheduleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Attendance\Filament\Resources\WorkingScheduleResource;

class ViewWorkingSchedule extends ViewRecord
{
    protected static string $resource = WorkingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
