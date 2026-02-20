<?php

namespace Modules\Core\Filament\Resources\BranchResource\Pages;

use Modules\Core\Filament\Resources\BranchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default working hours if not provided
        if (empty($data['working_hours'])) {
            $data['working_hours'] = $this->getDefaultWorkingHours();
        }

        return $data;
    }

    protected function getDefaultWorkingHours(): array
    {
        $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[] = [
                'day' => $day,
                'open_time' => $day === 'friday' ? null : '09:00',
                'close_time' => $day === 'friday' ? null : '18:00',
                'is_closed' => $day === 'friday',
            ];
        }

        return $hours;
    }
}
