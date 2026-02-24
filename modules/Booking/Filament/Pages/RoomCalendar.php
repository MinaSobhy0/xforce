<?php

namespace Modules\Booking\Filament\Pages;

use App\Services\BranchContext;
use App\Traits\ChecksResourcePermissions;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Modules\Booking\Models\Appointment;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;

class RoomCalendar extends Page implements HasForms
{
    use ChecksResourcePermissions;
    use InteractsWithForms;

    protected static ?string $moduleCode = 'booking';

    protected static ?string $permissionKey = 'appointments';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $slug = 'room-calendar';

    // Hide from navigation - accessed via CalendarPage toggle
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'booking::filament.pages.room-calendar';

    #[Url]
    public ?string $selectedDate = null;

    #[Url]
    public ?string $selectedBranch = null;

    public int $startHour = 8;
    public int $endHour = 22;
    public int $intervalMinutes = 15;

    public function mount(): void
    {
        $this->selectedDate = $this->selectedDate ?? today()->format('Y-m-d');
        // Always default to current branch context
        $this->selectedBranch = BranchContext::currentId();
    }

    public static function getNavigationLabel(): string
    {
        return __('booking::room_calendar.navigation');
    }

    public function getTitle(): string
    {
        return __('booking::room_calendar.title');
    }

    public function getHeading(): string
    {
        $date = Carbon::parse($this->selectedDate);
        return __('booking::room_calendar.heading') . ' - ' . $date->format('l, M d, Y');
    }

    public function updatedSelectedDate(): void
    {
        // Refresh data when date changes
    }

    public function updatedSelectedBranch(): void
    {
        // Refresh data when branch changes
    }

    public function goToToday(): void
    {
        $this->selectedDate = today()->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->subDay()->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate)->addDay()->format('Y-m-d');
    }

    public function isToday(): bool
    {
        return Carbon::parse($this->selectedDate)->isToday();
    }

    public function getRooms(): Collection
    {
        $branchId = $this->selectedBranch ?? BranchContext::currentId();

        return Room::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->bookable()
            ->active()
            ->whereIn('room_type', ['treatment', 'consultation'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function getBranches(): Collection
    {
        return Branch::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    public function getTimeSlots(): array
    {
        $slots = [];
        $date = Carbon::parse($this->selectedDate);

        for ($hour = $this->startHour; $hour < $this->endHour; $hour++) {
            for ($minute = 0; $minute < 60; $minute += $this->intervalMinutes) {
                $time = $date->copy()->setTime($hour, $minute);
                $slots[] = [
                    'time' => $time,
                    'label' => $time->format('H:i'),
                    'isHour' => $minute === 0,
                ];
            }
        }

        return $slots;
    }

    public function getAppointments(): Collection
    {
        $branchId = $this->selectedBranch ?? BranchContext::currentId();
        $date = Carbon::parse($this->selectedDate);

        $query = Appointment::query()
            ->with(['patient', 'service', 'practitioner', 'room'])
            ->forDate($date)
            ->whereNotNull('room_id')
            ->whereNotIn('status', [
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
                Appointment::STATUS_RESCHEDULED,
            ])
            ->ordered();

        if ($branchId) {
            $query->forBranch($branchId);
        }

        return $query->get();
    }

    public function getAppointmentsByRoom(): array
    {
        $appointments = $this->getAppointments();
        $rooms = $this->getRooms();
        $result = [];

        foreach ($rooms as $room) {
            $result[$room->id] = $appointments->filter(fn ($a) => $a->room_id === $room->id)->values();
        }

        return $result;
    }

    public function getAppointmentPosition(Appointment $appointment): array
    {
        $startTime = $appointment->start_time;
        $endTime = $appointment->end_time;
        $duration = $appointment->duration_minutes ?? 30;

        if (!$endTime && $startTime) {
            $endTime = $startTime->copy()->addMinutes($duration);
        }

        // Calculate top position based on start time
        $startMinutes = ($startTime->hour - $this->startHour) * 60 + $startTime->minute;
        $totalMinutes = ($this->endHour - $this->startHour) * 60;

        // Calculate height based on duration
        $durationMinutes = $startTime->diffInMinutes($endTime);

        // Each 15-minute slot is 48px (adjust as needed)
        $slotHeight = 48;
        $slotsFromStart = $startMinutes / $this->intervalMinutes;
        $slotsSpan = max(1, ceil($durationMinutes / $this->intervalMinutes));

        return [
            'top' => $slotsFromStart * $slotHeight,
            'height' => $slotsSpan * $slotHeight,
            'startTime' => $startTime->format('H:i'),
            'endTime' => $endTime->format('H:i'),
            'duration' => $durationMinutes,
        ];
    }

    public function isSlotOccupied(string $roomId, Carbon $slotTime): ?Appointment
    {
        $appointments = $this->getAppointments();

        return $appointments->first(function ($appointment) use ($roomId, $slotTime) {
            if ($appointment->room_id !== $roomId) {
                return false;
            }

            $startTime = $appointment->start_time;
            $endTime = $appointment->end_time;
            $duration = $appointment->duration_minutes ?? 30;

            if (!$endTime && $startTime) {
                $endTime = $startTime->copy()->addMinutes($duration);
            }

            // Check if slot time falls within appointment time range
            $slotTimeOnly = $slotTime->format('H:i:s');
            $startTimeOnly = $startTime->format('H:i:s');
            $endTimeOnly = $endTime->format('H:i:s');

            return $slotTimeOnly >= $startTimeOnly && $slotTimeOnly < $endTimeOnly;
        });
    }

    public function getStatusColor(string $status): string
    {
        return match ($status) {
            Appointment::STATUS_SCHEDULED => 'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900/50 dark:border-blue-700 dark:text-blue-200',
            Appointment::STATUS_CONFIRMED => 'bg-indigo-100 border-indigo-300 text-indigo-800 dark:bg-indigo-900/50 dark:border-indigo-700 dark:text-indigo-200',
            Appointment::STATUS_CHECKED_IN => 'bg-amber-100 border-amber-300 text-amber-800 dark:bg-amber-900/50 dark:border-amber-700 dark:text-amber-200',
            Appointment::STATUS_IN_PROGRESS => 'bg-purple-100 border-purple-300 text-purple-800 dark:bg-purple-900/50 dark:border-purple-700 dark:text-purple-200',
            Appointment::STATUS_COMPLETED => 'bg-green-100 border-green-300 text-green-800 dark:bg-green-900/50 dark:border-green-700 dark:text-green-200',
            default => 'bg-gray-100 border-gray-300 text-gray-800 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-200',
        };
    }

    public function getCurrentTimePosition(): ?int
    {
        if (!$this->isToday()) {
            return null;
        }

        $now = now();
        $currentMinutes = ($now->hour - $this->startHour) * 60 + $now->minute;

        if ($currentMinutes < 0 || $currentMinutes > ($this->endHour - $this->startHour) * 60) {
            return null;
        }

        $slotHeight = 48;
        return (int) (($currentMinutes / $this->intervalMinutes) * $slotHeight);
    }

}
