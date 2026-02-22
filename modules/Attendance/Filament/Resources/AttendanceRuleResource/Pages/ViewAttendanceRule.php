<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class ViewAttendanceRule extends ViewRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
