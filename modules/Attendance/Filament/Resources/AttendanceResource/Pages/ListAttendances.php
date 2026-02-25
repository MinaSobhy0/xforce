<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Attendance\Filament\Resources\AttendanceResource;

class ListAttendances extends BaseListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
