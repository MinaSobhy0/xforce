<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Attendance\Filament\Resources\AttendanceResource;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();

        return $data;
    }
}
