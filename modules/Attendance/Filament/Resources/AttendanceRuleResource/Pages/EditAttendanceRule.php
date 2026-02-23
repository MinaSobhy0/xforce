<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRuleResource\Pages;

use App\Filament\Resources\Pages\BaseEditRecord;
use Filament\Actions;
use Modules\Attendance\Filament\Resources\AttendanceRuleResource;

class EditAttendanceRule extends BaseEditRecord
{
    protected static string $resource = AttendanceRuleResource::class;

    protected function getEditHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
