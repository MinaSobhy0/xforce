<?php

namespace Modules\Booking\Filament\Pages;

use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Modules\Core\Models\Branch;
use App\Services\BranchContext;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;

class CalendarPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'calendar';

    protected static string $view = 'booking::filament.pages.calendar';

    public ?string $selectedBranch = null;
    public ?string $selectedPractitioner = null;
    public ?string $selectedDate = null;
    public string $viewMode = 'week';

    public static function getNavigationLabel(): string
    {
        return __('booking::calendar.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::calendar.title');
    }

    public function mount(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
        $this->selectedBranch = BranchContext::currentId();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedPractitioner')
                    ->label(__('booking::calendar.filters.practitioner'))
                    ->options(function () {
                        return User::query()
                            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['doctor', 'nurse', 'technician']))
                            ->get()
                            ->pluck('full_name', 'id');
                    })
                    ->placeholder(__('booking::calendar.filters.all_practitioners'))
                    ->live(),

                DatePicker::make('selectedDate')
                    ->label(__('booking::calendar.filters.date'))
                    ->native(false)
                    ->live(),
            ])
            ->columns(2);
    }

    public function getAppointments(): array
    {
        $query = Appointment::query()
            ->with(['patient', 'service', 'practitioner', 'branch', 'room'])
            ->active()
            ->forBranch($this->selectedBranch);

        if ($this->selectedPractitioner) {
            $query->forPractitioner($this->selectedPractitioner);
        }

        // Get appointments for the current view range
        $date = $this->selectedDate ? \Carbon\Carbon::parse($this->selectedDate) : today();

        if ($this->viewMode === 'day') {
            $query->forDate($date);
        } elseif ($this->viewMode === 'week') {
            $query->forDateRange($date->startOfWeek(), $date->copy()->endOfWeek());
        } else { // month
            $query->forDateRange($date->startOfMonth(), $date->copy()->endOfMonth());
        }

        return $query->ordered()->get()->map(function (Appointment $appointment) {
            $phone = $appointment->patient?->phone;
            $title = $appointment->patient?->full_name;
            if ($phone) {
                $title .= "\n" . $phone;
            }
            $title .= "\n" . $appointment->service?->name;

            $colors = $this->getStatusColors($appointment->status);

            return [
                'id' => $appointment->id,
                'title' => $title,
                'start' => $appointment->date->format('Y-m-d') . 'T' . $appointment->start_time->format('H:i:s'),
                'end' => $appointment->date->format('Y-m-d') . 'T' . ($appointment->end_time ? $appointment->end_time->format('H:i:s') : $appointment->start_time->addMinutes($appointment->duration_minutes)->format('H:i:s')),
                'backgroundColor' => $colors['bg'],
                'borderColor' => $colors['border'],
                'textColor' => $colors['text'],
                'extendedProps' => [
                    'code' => $appointment->code,
                    'status' => $appointment->status,
                    'patient' => $appointment->patient?->full_name,
                    'phone' => $appointment->patient?->phone,
                    'treatment' => $appointment->service?->name,
                    'practitioner' => $appointment->practitioner?->full_name,
                    'branch' => $appointment->branch?->name,
                    'room' => $appointment->room?->name,
                    'time' => $appointment->start_time->format('H:i') . ' - ' . ($appointment->end_time ? $appointment->end_time->format('H:i') : $appointment->start_time->addMinutes($appointment->duration_minutes)->format('H:i')),
                ],
            ];
        })->toArray();
    }

    protected function getStatusColor(string $status): string
    {
        return $this->getStatusColors($status)['border'];
    }

    protected function getStatusColors(string $status): array
    {
        return match ($status) {
            Appointment::STATUS_SCHEDULED => ['bg' => 'rgba(59, 130, 246, 0.15)', 'border' => '#3b82f6', 'text' => '#1e40af'],
            Appointment::STATUS_CONFIRMED => ['bg' => 'rgba(139, 92, 246, 0.15)', 'border' => '#8b5cf6', 'text' => '#5b21b6'],
            Appointment::STATUS_CHECKED_IN => ['bg' => 'rgba(245, 158, 11, 0.15)', 'border' => '#f59e0b', 'text' => '#b45309'],
            Appointment::STATUS_IN_PROGRESS => ['bg' => 'rgba(99, 102, 241, 0.15)', 'border' => '#6366f1', 'text' => '#4338ca'],
            Appointment::STATUS_COMPLETED => ['bg' => 'rgba(16, 185, 129, 0.15)', 'border' => '#10b981', 'text' => '#047857'],
            Appointment::STATUS_CANCELLED => ['bg' => 'rgba(239, 68, 68, 0.15)', 'border' => '#ef4444', 'text' => '#b91c1c'],
            Appointment::STATUS_NO_SHOW => ['bg' => 'rgba(107, 114, 128, 0.15)', 'border' => '#6b7280', 'text' => '#374151'],
            Appointment::STATUS_RESCHEDULED => ['bg' => 'rgba(245, 158, 11, 0.15)', 'border' => '#f59e0b', 'text' => '#b45309'],
            default => ['bg' => 'rgba(107, 114, 128, 0.15)', 'border' => '#6b7280', 'text' => '#374151'],
        };
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
        $this->dispatch('calendarViewChanged', mode: $mode, events: $this->getAppointments());
    }

    public function navigateToDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function today(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
        $this->dispatch('calendarDateChanged', date: $this->selectedDate, events: $this->getAppointments());
    }

    public function previous(): void
    {
        $date = \Carbon\Carbon::parse($this->selectedDate);
        $this->selectedDate = match ($this->viewMode) {
            'day' => $date->subDay()->format('Y-m-d'),
            'week' => $date->subWeek()->format('Y-m-d'),
            'month' => $date->subMonth()->format('Y-m-d'),
            default => $date->subWeek()->format('Y-m-d'),
        };
        $this->dispatch('calendarDateChanged', date: $this->selectedDate, events: $this->getAppointments());
    }

    public function next(): void
    {
        $date = \Carbon\Carbon::parse($this->selectedDate);
        $this->selectedDate = match ($this->viewMode) {
            'day' => $date->addDay()->format('Y-m-d'),
            'week' => $date->addWeek()->format('Y-m-d'),
            'month' => $date->addMonth()->format('Y-m-d'),
            default => $date->addWeek()->format('Y-m-d'),
        };
        $this->dispatch('calendarDateChanged', date: $this->selectedDate, events: $this->getAppointments());
    }

    public function getDateRangeLabel(): string
    {
        $date = \Carbon\Carbon::parse($this->selectedDate);

        return match ($this->viewMode) {
            'day' => $date->format('l, F j, Y'),
            'week' => $date->startOfWeek()->format('M j') . ' - ' . $date->endOfWeek()->format('M j, Y'),
            'month' => $date->format('F Y'),
            default => $date->format('F Y'),
        };
    }
}
