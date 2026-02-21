<?php

namespace Modules\PatientPortal\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Modules\Booking\Models\Appointment;
use Carbon\Carbon;

class MyAppointments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static string $view = 'patientportal::filament.pages.my-appointments';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('patientportal::portal.my_appointments');
    }

    public function getTitle(): string
    {
        return __('patientportal::portal.my_appointments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('date')
                    ->label(__('patientportal::portal.date'))
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('start_time')
                    ->label(__('patientportal::portal.time'))
                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('h:i A')),

                TextColumn::make('treatment.name')
                    ->label(__('patientportal::portal.treatment'))
                    ->formatStateUsing(function ($state) {
                        if (is_array(json_decode($state, true))) {
                            return json_decode($state, true)[app()->getLocale()] ?? json_decode($state, true)['en'] ?? $state;
                        }
                        return $state;
                    }),

                TextColumn::make('branch.name')
                    ->label(__('patientportal::portal.branch')),

                TextColumn::make('practitioner.name')
                    ->label(__('patientportal::portal.practitioner')),

                BadgeColumn::make('status')
                    ->label(__('patientportal::portal.status'))
                    ->colors([
                        'warning' => 'scheduled',
                        'success' => ['confirmed', 'completed'],
                        'danger' => ['cancelled', 'no_show'],
                        'info' => 'in_progress',
                    ])
                    ->formatStateUsing(fn ($state) => __('booking::booking.statuses.' . $state)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'scheduled' => __('booking::booking.statuses.scheduled'),
                        'confirmed' => __('booking::booking.statuses.confirmed'),
                        'completed' => __('booking::booking.statuses.completed'),
                        'cancelled' => __('booking::booking.statuses.cancelled'),
                    ]),
            ])
            ->actions([
                Action::make('cancel')
                    ->label(__('patientportal::portal.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('patientportal::portal.cancel_appointment'))
                    ->modalDescription(__('patientportal::portal.cancel_confirmation'))
                    ->visible(fn (Appointment $record) => $this->canCancel($record))
                    ->action(fn (Appointment $record) => $this->cancelAppointment($record)),

                Action::make('reschedule')
                    ->label(__('patientportal::portal.reschedule'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Appointment $record) => $this->canReschedule($record))
                    ->url(fn (Appointment $record) => BookAppointment::getUrl([
                        'reschedule' => $record->id,
                    ])),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading(__('patientportal::portal.no_appointments'))
            ->emptyStateDescription(__('patientportal::portal.no_appointments_desc'))
            ->emptyStateIcon('heroicon-o-calendar')
            ->emptyStateActions([
                Action::make('book')
                    ->label(__('patientportal::portal.book_now'))
                    ->url(BookAppointment::getUrl())
                    ->icon('heroicon-o-plus'),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $patient = Auth::guard('patient')->user();

        return Appointment::query()
            ->where('patient_id', $patient->id)
            ->with(['treatment', 'branch', 'practitioner']);
    }

    protected function canCancel(Appointment $appointment): bool
    {
        if (!in_array($appointment->status, ['scheduled', 'confirmed'])) {
            return false;
        }

        $hoursRequired = config('patientportal.booking.cancel_hours_before', 24);
        $appointmentTime = Carbon::parse($appointment->date . ' ' . $appointment->start_time);

        return $appointmentTime->diffInHours(now()) >= $hoursRequired;
    }

    protected function canReschedule(Appointment $appointment): bool
    {
        if (!in_array($appointment->status, ['scheduled', 'confirmed'])) {
            return false;
        }

        $hoursRequired = config('patientportal.booking.reschedule_hours_before', 24);
        $appointmentTime = Carbon::parse($appointment->date . ' ' . $appointment->start_time);

        return $appointmentTime->diffInHours(now()) >= $hoursRequired;
    }

    protected function cancelAppointment(Appointment $appointment): void
    {
        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => __('patientportal::portal.cancelled_by_patient'),
        ]);

        Notification::make()
            ->title(__('patientportal::portal.appointment_cancelled'))
            ->success()
            ->send();
    }
}
