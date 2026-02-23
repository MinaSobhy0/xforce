<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Attendance\Filament\Resources\AttendanceResource;
use Modules\Attendance\Models\Attendance;

class CreateAttendance extends CreateRecord
{
    protected static string $resource = AttendanceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Check for existing soft-deleted record
        $existing = Attendance::query()
            ->withoutGlobalScope('branch')
            ->withoutGlobalScope('tenant')
            ->withTrashed()
            ->where('tenant_id', $data['tenant_id'])
            ->where('staff_profile_id', $data['staff_profile_id'])
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();

        if ($existing) {
            // Restore and update the soft-deleted record
            if ($existing->trashed()) {
                $existing->restore();
            }
            $existing->fill($data);
            $existing->save();

            return $existing;
        }

        return parent::handleRecordCreation($data);
    }
}
