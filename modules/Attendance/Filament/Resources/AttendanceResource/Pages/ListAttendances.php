<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceResource;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
