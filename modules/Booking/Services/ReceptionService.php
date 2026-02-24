<?php

namespace Modules\Booking\Services;

use App\Services\BranchContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Booking\Models\Appointment;
use Modules\Core\Models\Room;

class ReceptionService
{
    /**
     * Get available rooms for appointment assignment.
     */
    public function getAvailableRooms(
        string $branchId,
        Carbon $date,
        Carbon $startTime,
        Carbon $endTime
    ): Collection {
        // Get room IDs that are currently occupied
        $occupiedRoomIds = Appointment::query()
            ->forDate($date)
            ->forBranch($branchId)
            ->active()
            ->whereNotNull('room_id')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereRaw("start_time < ?", [$endTime->format('H:i:s')])
                    ->whereRaw("COALESCE(end_time, start_time + (duration_minutes || ' minutes')::interval) > ?", [$startTime->format('H:i:s')]);
            })
            ->pluck('room_id');

        return Room::query()
            ->inBranch($branchId)
            ->bookable()
            ->active()
            ->whereIn('room_type', ['treatment', 'consultation'])
            ->whereNotIn('id', $occupiedRoomIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all bookable rooms for a branch.
     */
    public function getAllRooms(string $branchId): Collection
    {
        return Room::query()
            ->inBranch($branchId)
            ->bookable()
            ->active()
            ->whereIn('room_type', ['treatment', 'consultation'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Calculate wait time for an appointment.
     */
    public function calculateWaitTime(Appointment $appointment): ?array
    {
        if (!$appointment->checked_in_at) {
            return null;
        }

        // If session already started, no wait time
        if ($appointment->status === Appointment::STATUS_IN_PROGRESS) {
            return null;
        }

        $minutes = now()->diffInMinutes($appointment->checked_in_at);

        return [
            'minutes' => $minutes,
            'formatted' => $this->formatWaitTime($minutes),
            'severity' => $this->getWaitTimeSeverity($minutes),
        ];
    }

    /**
     * Format wait time as human-readable string.
     */
    protected function formatWaitTime(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return "{$hours}h {$remainingMinutes}m";
    }

    /**
     * Get severity level based on wait time.
     */
    protected function getWaitTimeSeverity(int $minutes): string
    {
        if ($minutes < 15) {
            return 'success';
        }

        if ($minutes < 30) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * Get appointments grouped by flow stage.
     */
    public function getPatientFlowData(?string $branchId = null, ?Carbon $date = null): array
    {
        $branchId = $branchId ?? BranchContext::currentId();
        $date = $date ?? today();

        $query = Appointment::query()
            ->with(['patient', 'service', 'practitioner', 'room'])
            ->forDate($date)
            ->ordered();

        if ($branchId) {
            $query->forBranch($branchId);
        }

        $appointments = $query->get();

        $now = now();
        $isToday = $date->isToday();
        $thirtyMinutesFromNow = $now->copy()->addMinutes(30);
        $twoHoursAgo = $now->copy()->subHours(2);

        return [
            'arriving' => $appointments->filter(function ($a) use ($now, $thirtyMinutesFromNow, $isToday, $date) {
                if (!in_array($a->status, [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])) {
                    return false;
                }
                $startTime = $a->start_date_time;
                if (!$startTime) {
                    return false;
                }
                // For today, show next 30 minutes
                // For other days, show all scheduled/confirmed as "arriving"
                if ($isToday) {
                    return $startTime->between($now, $thirtyMinutesFromNow);
                }
                return true; // Show all upcoming for other days
            })->values(),

            'waiting' => $appointments->filter(function ($a) {
                return $a->status === Appointment::STATUS_CHECKED_IN && !$a->room_id;
            })->values(),

            'in_rooms' => $appointments->filter(function ($a) {
                return $a->status === Appointment::STATUS_CHECKED_IN && $a->room_id;
            })->values(),

            'with_doctor' => $appointments->filter(function ($a) {
                return $a->status === Appointment::STATUS_IN_PROGRESS;
            })->values(),

            'done' => $appointments->filter(function ($a) use ($twoHoursAgo, $isToday) {
                if ($a->status !== Appointment::STATUS_COMPLETED) {
                    return false;
                }
                // For today, only show completed in last 2 hours
                // For other days, show all completed
                if ($isToday) {
                    return $a->completed_at && $a->completed_at->gte($twoHoursAgo);
                }
                return true;
            })->values(),
        ];
    }

    /**
     * Get reception statistics for a given date.
     */
    public function getReceptionStats(?string $branchId = null, ?Carbon $date = null): array
    {
        $branchId = $branchId ?? BranchContext::currentId();
        $date = $date ?? today();

        $query = Appointment::query()
            ->forDate($date);

        if ($branchId) {
            $query->forBranch($branchId);
        }

        $appointments = $query->get();

        return [
            'total' => $appointments->count(),
            'waiting' => $appointments->filter(fn ($a) =>
                $a->status === Appointment::STATUS_CHECKED_IN && !$a->room_id
            )->count(),
            'in_rooms' => $appointments->filter(fn ($a) =>
                $a->status === Appointment::STATUS_CHECKED_IN && $a->room_id
            )->count(),
            'in_progress' => $appointments->filter(fn ($a) =>
                $a->status === Appointment::STATUS_IN_PROGRESS
            )->count(),
            'completed' => $appointments->filter(fn ($a) =>
                $a->status === Appointment::STATUS_COMPLETED
            )->count(),
            'no_shows' => $appointments->filter(fn ($a) =>
                $a->status === Appointment::STATUS_NO_SHOW
            )->count(),
            'upcoming' => $appointments->filter(fn ($a) =>
                in_array($a->status, [Appointment::STATUS_SCHEDULED, Appointment::STATUS_CONFIRMED])
            )->count(),
        ];
    }

    /**
     * Get average wait time for a given date.
     */
    public function getAverageWaitTime(?string $branchId = null, ?Carbon $date = null): ?int
    {
        $branchId = $branchId ?? BranchContext::currentId();
        $date = $date ?? today();

        $query = Appointment::query()
            ->forDate($date)
            ->whereNotNull('checked_in_at')
            ->whereNotNull('started_at');

        if ($branchId) {
            $query->forBranch($branchId);
        }

        $appointments = $query->get();

        if ($appointments->isEmpty()) {
            return null;
        }

        $totalMinutes = $appointments->sum(function ($a) {
            return $a->started_at->diffInMinutes($a->checked_in_at);
        });

        return (int) round($totalMinutes / $appointments->count());
    }

    /**
     * Check if a room is currently occupied.
     */
    public function isRoomOccupied(string $roomId): bool
    {
        return Appointment::query()
            ->forDate(today())
            ->forRoom($roomId)
            ->whereIn('status', [
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_PROGRESS,
            ])
            ->exists();
    }

    /**
     * Get current patient in a room.
     */
    public function getPatientInRoom(string $roomId): ?Appointment
    {
        return Appointment::query()
            ->with(['patient', 'service', 'practitioner'])
            ->forDate(today())
            ->forRoom($roomId)
            ->whereIn('status', [
                Appointment::STATUS_CHECKED_IN,
                Appointment::STATUS_IN_PROGRESS,
            ])
            ->first();
    }
}
