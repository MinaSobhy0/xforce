<?php

namespace Modules\Attendance\Filament\Resources\AttendanceViolationResource\Pages;

use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Attendance\Filament\Resources\AttendanceViolationResource;

class ListAttendanceViolations extends BaseListRecords
{
    protected static string $resource = AttendanceViolationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
        ];
    }
}
