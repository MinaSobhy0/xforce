<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use App\Filament\Resources\Pages\BaseViewRecord;
use Filament\Actions;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class ViewAttendanceRule extends BaseViewRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
