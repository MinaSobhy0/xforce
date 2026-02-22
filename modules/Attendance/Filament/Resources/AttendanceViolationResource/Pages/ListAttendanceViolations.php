<?php

namespace Modules\Attendance\Filament\Resources\AttendanceViolationResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceViolationResource;

class ListAttendanceViolations extends ListRecords
{
    protected static string $resource = AttendanceViolationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
