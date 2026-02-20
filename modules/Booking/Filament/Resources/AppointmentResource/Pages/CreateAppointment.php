<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\Pages;

use Modules\Booking\Filament\Resources\AppointmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calculate end_time if not set
        if (!empty($data['start_time']) && !empty($data['duration_minutes']) && empty($data['end_time'])) {
            $start = \Carbon\Carbon::parse($data['start_time']);
            $data['end_time'] = $start->addMinutes((int) $data['duration_minutes'])->format('H:i');
        }

        return $data;
    }
}
