<?php

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\Services\Models\Service;
use Modules\Core\Models\Branch;
use Modules\Booking\Models\Appointment;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class BookingController extends BaseApiController
{
    /**
     * Get available services for booking
     */
    public function services(Request $request): JsonResponse
    {
        $services = Service::where('is_active', true)
            ->where('is_bookable_online', true)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $this->getTranslatedName($s->name),
                'description' => $this->getTranslatedName($s->description),
                'duration_minutes' => $s->duration_minutes,
                'price' => $s->price_minor / 100,
                'currency' => current_currency(),
            ]);

        return $this->success($services);
    }

    /**
     * Get available branches
     */
    public function branches(Request $request): JsonResponse
    {
        $branches = Branch::where('is_active', true)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $this->getTranslatedName($b->name),
                'address' => $b->address,
                'phone' => $b->phone,
            ]);

        return $this->success($branches);
    }

    /**
     * Get available time slots for a date
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'branch_id' => 'required|exists:branches,id',
            'date' => 'required|date|after:today',
        ]);

        $service = Service::find($request->service_id);
        $date = Carbon::parse($request->date);
        $duration = $service->duration_minutes ?? 30;

        // Get practitioners at this branch
        $practitioners = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['practitioner', 'doctor', 'therapist']))
            ->where('branch_id', $request->branch_id)
            ->where('is_active', true)
            ->get();

        $slots = [];
        $dayOfWeek = $date->dayOfWeek;

        foreach ($practitioners as $practitioner) {
            $workingHours = $practitioner->working_hours[$dayOfWeek] ?? null;

            if (!$workingHours || !($workingHours['is_working'] ?? false)) {
                continue;
            }

            $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['start']);
            $endTime = Carbon::parse($date->format('Y-m-d') . ' ' . $workingHours['end']);

            // Get existing appointments
            $existingAppointments = Appointment::where('practitioner_id', $practitioner->id)
                ->where('date', $date->format('Y-m-d'))
                ->whereNotIn('status', ['cancelled', 'no_show'])
                ->get(['start_time', 'end_time']);

            $current = $startTime->copy();
            while ($current->copy()->addMinutes($duration)->lte($endTime)) {
                $slotEnd = $current->copy()->addMinutes($duration);

                $conflict = false;
                foreach ($existingAppointments as $existing) {
                    $existingStart = Carbon::parse($date->format('Y-m-d') . ' ' . $existing->start_time);
                    $existingEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $existing->end_time);

                    if ($current->lt($existingEnd) && $slotEnd->gt($existingStart)) {
                        $conflict = true;
                        break;
                    }
                }

                $minHours = config('patientportal.booking.min_hours_before', 2);
                if (!$conflict && $current->gt(now()->addHours($minHours))) {
                    $slots[] = [
                        'practitioner_id' => $practitioner->id,
                        'practitioner_name' => $practitioner->name,
                        'start_time' => $current->format('H:i'),
                        'end_time' => $slotEnd->format('H:i'),
                    ];
                }

                $current->addMinutes(15);
            }
        }

        // Sort by time
        usort($slots, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

        return $this->success([
            'date' => $date->format('Y-m-d'),
            'slots' => $slots,
        ]);
    }

    /**
     * Create a new appointment
     */
    public function book(Request $request): JsonResponse
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'branch_id' => 'required|exists:branches,id',
            'practitioner_id' => 'required|exists:users,id',
            'date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
        ]);

        $patient = $request->user();
        $service = Service::find($request->service_id);
        $duration = $service->duration_minutes ?? 30;

        $startTime = Carbon::parse($request->date . ' ' . $request->start_time);
        $endTime = $startTime->copy()->addMinutes($duration);

        // Check minimum hours before
        $minHours = config('patientportal.booking.min_hours_before', 2);
        if ($startTime->lt(now()->addHours($minHours))) {
            return $this->error(__('api::api.booking_too_soon'), 422);
        }

        // Check for conflicts
        $conflict = Appointment::where('practitioner_id', $request->practitioner_id)
            ->where('date', $request->date)
            ->whereNotIn('status', ['cancelled', 'no_show'])
            ->where(function ($q) use ($request, $endTime) {
                $q->whereBetween('start_time', [$request->start_time, $endTime->format('H:i')])
                    ->orWhereBetween('end_time', [$request->start_time, $endTime->format('H:i')]);
            })
            ->exists();

        if ($conflict) {
            return $this->error(__('api::api.slot_not_available'), 409);
        }

        try {
            DB::beginTransaction();

            $appointment = Appointment::create([
                'patient_id' => $patient->id,
                'service_id' => $request->service_id,
                'branch_id' => $request->branch_id,
                'practitioner_id' => $request->practitioner_id,
                'date' => $request->date,
                'start_time' => $request->start_time,
                'end_time' => $endTime->format('H:i'),
                'duration_minutes' => $duration,
                'status' => 'scheduled',
                'booked_via' => 'api',
                'notes' => $request->notes,
            ]);

            DB::commit();

            return $this->success([
                'appointment_id' => $appointment->id,
                'date' => $appointment->date->format('Y-m-d'),
                'start_time' => $appointment->start_time,
                'end_time' => $appointment->end_time,
                'status' => $appointment->status,
            ], __('api::api.booking_success'), 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error(__('api::api.booking_failed'), 500);
        }
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Request $request, $appointmentId): JsonResponse
    {
        $patient = $request->user();

        $appointment = Appointment::where('id', $appointmentId)
            ->where('patient_id', $patient->id)
            ->first();

        if (!$appointment) {
            return $this->error(__('api::api.appointment_not_found'), 404);
        }

        if (!in_array($appointment->status, ['scheduled', 'confirmed'])) {
            return $this->error(__('api::api.cannot_cancel'), 422);
        }

        $appointmentTime = Carbon::parse($appointment->date . ' ' . $appointment->start_time);
        $hoursRequired = config('patientportal.booking.cancel_hours_before', 24);

        if ($appointmentTime->diffInHours(now()) < $hoursRequired) {
            return $this->error(__('api::api.cancel_too_late', ['hours' => $hoursRequired]), 422);
        }

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => __('api::api.cancelled_via_api'),
        ]);

        return $this->success(
            ['cancelled' => true],
            __('api::api.appointment_cancelled')
        );
    }

    protected function getTranslatedName($value): string
    {
        if (is_array($decoded = json_decode($value, true))) {
            return $decoded[app()->getLocale()] ?? $decoded['en'] ?? $value;
        }
        return $value ?? '';
    }
}
