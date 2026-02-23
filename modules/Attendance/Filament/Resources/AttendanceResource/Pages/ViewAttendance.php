<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\Attendance\Filament\Resources\AttendanceResource;

class ViewAttendance extends BaseViewRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
