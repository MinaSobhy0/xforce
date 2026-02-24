<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\Pages;

use Modules\Booking\Filament\Resources\AppointmentResource;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseListRecords;

class ListAppointments extends BaseListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Actions\Action::make('create')
                ->label(__('booking::appointments.actions.new'))
                ->icon('heroicon-o-plus')
                ->url(fn () => route('filament.tenant.pages.create-booking')),
        ];
    }
}
