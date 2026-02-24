<?php

namespace Modules\Booking\Filament\Widgets;

use App\Services\BranchContext;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Model;
use Modules\Booking\Models\Appointment;
use Modules\Core\Models\Room;
use Modules\Treatments\Models\Service;
use Modules\Auth\Models\User;
use Modules\Patients\Models\Patient;
use Filament\Actions\Action;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public Model|string|null $model = Appointment::class;

    protected function headerActions(): array
    {
        return [
            Action::make('rooms')
                ->label(__('booking::calendar.view.rooms'))
                ->icon('heroicon-o-building-office')
                ->color('gray')
                ->url(route('filament.tenant.pages.room-calendar')),
        ];
    }

    public function config(): array
    {
        return [
            'initialView' => 'timeGridWeek',
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay',
            ],
            'slotMinTime' => '08:00:00',
            'slotMaxTime' => '22:00:00',
            'slotDuration' => '00:15:00',
            'allDaySlot' => false,
            'nowIndicator' => true,
            'editable' => true,
            'selectable' => true,
            'selectMirror' => true,
            'dayMaxEvents' => true,
            'weekends' => true,
            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        $branchId = BranchContext::currentId();

        return Appointment::query()
            ->with(['patient', 'service', 'practitioner', 'room'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('date', '>=', $fetchInfo['start'])
            ->where('date', '<=', $fetchInfo['end'])
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->get()
            ->map(fn (Appointment $appointment) => EventData::make()
                ->id($appointment->id)
                ->title($appointment->patient?->full_name . ' - ' . $appointment->service?->name)
                ->start($appointment->date->format('Y-m-d') . 'T' . $appointment->start_time->format('H:i:s'))
                ->end($appointment->date->format('Y-m-d') . 'T' . ($appointment->end_time?->format('H:i:s') ?? $appointment->start_time->addMinutes($appointment->duration_minutes)->format('H:i:s')))
                ->backgroundColor($this->getStatusColor($appointment->status))
                ->borderColor($this->getStatusColor($appointment->status))
                ->extendedProps([
                    'status' => $appointment->status,
                    'patient' => $appointment->patient?->full_name,
                    'service' => $appointment->service?->name,
                    'practitioner' => $appointment->practitioner?->full_name,
                    'room' => $appointment->room?->name,
                ])
            )
            ->toArray();
    }

    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_SCHEDULED => '#3b82f6', // blue
            Appointment::STATUS_CONFIRMED => '#8b5cf6', // purple
            Appointment::STATUS_CHECKED_IN => '#f59e0b', // amber
            Appointment::STATUS_IN_PROGRESS => '#6366f1', // indigo
            Appointment::STATUS_COMPLETED => '#10b981', // green
            Appointment::STATUS_CANCELLED => '#ef4444', // red
            Appointment::STATUS_NO_SHOW => '#6b7280', // gray
            Appointment::STATUS_RESCHEDULED => '#f59e0b', // amber
            default => '#6b7280',
        };
    }

    public function getFormSchema(): array
    {
        return [
            Grid::make()
                ->schema([
                    Select::make('patient_id')
                        ->label(__('booking::appointments.fields.patient'))
                        ->relationship('patient', 'first_name')
                        ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->full_name)
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('service_id')
                        ->label(__('booking::appointments.fields.service'))
                        ->options(Service::query()->active()->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('practitioner_id')
                        ->label(__('booking::appointments.fields.practitioner'))
                        ->options(
                            User::query()
                                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                                ->get()
                                ->pluck('full_name', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required(),

                    Select::make('room_id')
                        ->label(__('booking::appointments.fields.room'))
                        ->options(Room::query()->bookable()->active()->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),

                    DateTimePicker::make('starts_at')
                        ->label(__('booking::appointments.fields.start_time'))
                        ->required(),

                    DateTimePicker::make('ends_at')
                        ->label(__('booking::appointments.fields.end_time'))
                        ->required(),

                    Textarea::make('notes')
                        ->label(__('booking::appointments.fields.notes'))
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function onEventClick(array $event): void
    {
        $this->redirect(route('filament.tenant.resources.appointments.view', ['record' => $event['id']]));
    }

    public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta, ?array $oldResource, ?array $newResource): bool
    {
        $appointment = Appointment::find($event['id']);

        if ($appointment) {
            $newStart = \Carbon\Carbon::parse($event['start']);
            $newEnd = $event['end'] ? \Carbon\Carbon::parse($event['end']) : $newStart->copy()->addMinutes($appointment->duration_minutes);

            $appointment->update([
                'date' => $newStart->toDateString(),
                'start_time' => $newStart->format('H:i:s'),
                'end_time' => $newEnd->format('H:i:s'),
            ]);

            return true;
        }

        return false;
    }

    public function onEventResize(array $event, array $oldEvent, array $relatedEvents, array $startDelta, array $endDelta): bool
    {
        $appointment = Appointment::find($event['id']);

        if ($appointment) {
            $newEnd = \Carbon\Carbon::parse($event['end']);

            $appointment->update([
                'end_time' => $newEnd->format('H:i:s'),
                'duration_minutes' => $appointment->start_time->diffInMinutes($newEnd),
            ]);

            return true;
        }

        return false;
    }
}
