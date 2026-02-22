<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class ListAttendanceRules extends ListRecords
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
