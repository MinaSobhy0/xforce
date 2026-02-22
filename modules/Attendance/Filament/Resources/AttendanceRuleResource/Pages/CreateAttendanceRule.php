<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class CreateAttendanceRule extends CreateRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
