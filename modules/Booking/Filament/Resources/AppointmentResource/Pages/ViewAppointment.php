<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\Pages;

use Modules\Booking\Filament\Resources\AppointmentResource;
use Modules\Booking\Models\Appointment;
use Filament\Actions;
use App\Filament\Resources\Pages\BaseViewRecord;

class ViewAppointment extends BaseViewRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getViewHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            Actions\Action::make('confirm')
                ->label(__('booking::appointments.actions.confirm'))
                ->icon('heroicon-o-check')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CONFIRMED))
                ->action(fn () => $this->record->confirm()),

            Actions\Action::make('check_in')
                ->label(__('booking::appointments.actions.check_in'))
                ->icon('heroicon-o-arrow-right-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CHECKED_IN))
                ->action(fn () => $this->record->checkIn()),

            Actions\Action::make('start')
                ->label(__('booking::appointments.actions.start'))
                ->icon('heroicon-o-play')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_IN_PROGRESS))
                ->action(fn () => $this->record->start()),

            Actions\Action::make('complete')
                ->label(__('booking::appointments.actions.complete'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_COMPLETED))
                ->action(fn () => $this->record->complete()),

            Actions\Action::make('cancel')
                ->label(__('booking::appointments.actions.cancel'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\Textarea::make('cancellation_reason')
                        ->label(__('booking::appointments.fields.cancellation_reason'))
                        ->required(),
                ])
                ->visible(fn (): bool => $this->record->canTransitionTo(Appointment::STATUS_CANCELLED))
                ->action(fn (array $data) => $this->record->cancel($data['cancellation_reason'])),
        ];
    }
}
