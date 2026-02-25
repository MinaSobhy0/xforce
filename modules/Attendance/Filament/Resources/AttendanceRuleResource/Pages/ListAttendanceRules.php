<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class ListAttendanceRules extends BaseListRecords
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            ...parent::getHeaderActions(),
        ];
    }
}
