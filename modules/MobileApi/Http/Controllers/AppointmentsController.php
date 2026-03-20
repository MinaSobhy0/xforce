<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentsController extends BaseApiController
{
    /**
     * Get today's appointments.
     * GET /api/v2/appointments/today
     */
    public function today(): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->success([]);
        }

        $appointments = \Modules\Booking\Models\Appointment::with(['patient', 'service'])
            ->where('practitioner_id', $staffProfile->id)
            ->whereDate('date', today())
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        return $this->success([
            'date' => today()->toDateString(),
            'total' => $appointments->count(),
            'completed' => $appointments->where('status', 'completed')->count(),
            'appointments' => $appointments->map(fn($a) => $this->formatAppointment($a))->all(),
        ]);
    }

    /**
     * Get appointments list with filters.
     * GET /api/v2/appointments
     */
    public function index(Request $request): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->success([]);
        }

        $query = \Modules\Booking\Models\Appointment::with(['patient', 'service'])
            ->where('practitioner_id', $staffProfile->id);

        // Filter by date range
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date);
        } elseif ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderBy('date')
            ->orderBy('start_time')
            ->paginate($this->getPerPage());

        return $this->paginated($appointments);
    }

    /**
     * Get appointment details.
     * GET /api/v2/appointments/{id}
     */
    public function show(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->notFound();
        }

        $appointment = \Modules\Booking\Models\Appointment::with([
            'patient',
            'service',
            'package',
            'invoicePayments',
        ])
            ->where('practitioner_id', $staffProfile->id)
            ->find($id);

        if (!$appointment) {
            return $this->notFound();
        }

        return $this->success($this->formatAppointmentDetails($appointment));
    }

    /**
     * Get patient info for appointment.
     * GET /api/v2/appointments/{id}/patient
     */
    public function patient(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->notFound();
        }

        $appointment = \Modules\Booking\Models\Appointment::with('patient')
            ->where('practitioner_id', $staffProfile->id)
            ->find($id);

        if (!$appointment || !$appointment->patient) {
            return $this->notFound();
        }

        $patient = $appointment->patient;

        return $this->success([
            'id' => $patient->id,
            'full_name' => $patient->full_name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'gender' => $patient->gender,
            'notes' => $patient->notes,
            'medical_history' => $patient->medical_history ?? [],
            'allergies' => $patient->allergies ?? [],
        ]);
    }

    /**
     * Start appointment session.
     * POST /api/v2/appointments/{id}/start
     */
    public function start(int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->error('Booking module not available', 503);
        }

        $appointment = \Modules\Booking\Models\Appointment::where('practitioner_id', $staffProfile->id)
            ->find($id);

        if (!$appointment) {
            return $this->notFound();
        }

        if (!in_array($appointment->status, ['scheduled', 'confirmed', 'arrived'])) {
            return $this->error(__('mobile_api::mobile.appointments.cannot_start'), 400);
        }

        $appointment->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $this->success([
            'status' => 'in_progress',
            'started_at' => now()->format('H:i'),
        ], __('mobile_api::mobile.appointments.session_started'));
    }

    /**
     * Complete appointment session.
     * POST /api/v2/appointments/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        $request->validate([
            'notes' => 'sometimes|string|max:5000',
            'outcome' => 'sometimes|string|max:500',
        ]);

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->error('Booking module not available', 503);
        }

        $appointment = \Modules\Booking\Models\Appointment::where('practitioner_id', $staffProfile->id)
            ->find($id);

        if (!$appointment) {
            return $this->notFound();
        }

        if ($appointment->status !== 'in_progress') {
            return $this->error(__('mobile_api::mobile.appointments.cannot_complete'), 400);
        }

        $appointment->update([
            'status' => 'completed',
            'completed_at' => now(),
            'practitioner_notes' => $request->notes ?? $appointment->practitioner_notes,
            'outcome' => $request->outcome,
        ]);

        return $this->success([
            'status' => 'completed',
            'completed_at' => now()->format('H:i'),
        ], __('mobile_api::mobile.appointments.session_completed'));
    }

    /**
     * Add/update notes for appointment.
     * POST /api/v2/appointments/{id}/notes
     */
    public function notes(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        $request->validate([
            'notes' => 'required|string|max:5000',
        ]);

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->error('Booking module not available', 503);
        }

        $appointment = \Modules\Booking\Models\Appointment::where('practitioner_id', $staffProfile->id)
            ->find($id);

        if (!$appointment) {
            return $this->notFound();
        }

        $appointment->update([
            'practitioner_notes' => $request->notes,
        ]);

        return $this->success(null, __('mobile_api::mobile.appointments.notes_saved'));
    }

    /**
     * Format appointment for list.
     */
    protected function formatAppointment($appointment): array
    {
        return [
            'id' => $appointment->id,
            'patient' => [
                'id' => $appointment->patient?->id,
                'name' => $appointment->patient?->full_name,
                'phone' => $appointment->patient?->phone,
            ],
            'service' => [
                'id' => $appointment->service?->id,
                'name' => $appointment->service?->name,
            ],
            'scheduled_at' => $appointment->date->format('Y-m-d') . ' ' . $appointment->start_time->format('H:i'),
            'time' => $appointment->start_time->format('H:i'),
            'duration_minutes' => $appointment->duration_minutes,
            'status' => $appointment->status,
            'is_package' => $appointment->package_id !== null,
        ];
    }

    /**
     * Format appointment details.
     */
    protected function formatAppointmentDetails($appointment): array
    {
        return [
            'id' => $appointment->id,
            'patient' => [
                'id' => $appointment->patient?->id,
                'name' => $appointment->patient?->full_name,
                'phone' => $appointment->patient?->phone,
                'email' => $appointment->patient?->email,
            ],
            'service' => [
                'id' => $appointment->service?->id,
                'name' => $appointment->service?->name,
                'description' => $appointment->service?->description,
            ],
            'package' => $appointment->package ? [
                'id' => $appointment->package->id,
                'name' => $appointment->package->name,
                'session_number' => $appointment->session_number,
                'total_sessions' => $appointment->package->sessions_count,
            ] : null,
            'scheduled_at' => $appointment->date->format('Y-m-d') . ' ' . $appointment->start_time->format('H:i'),
            'duration_minutes' => $appointment->duration_minutes,
            'status' => $appointment->status,
            'started_at' => $appointment->started_at?->format('H:i'),
            'completed_at' => $appointment->completed_at?->format('H:i'),
            'notes' => $appointment->notes,
            'practitioner_notes' => $appointment->practitioner_notes,
            'outcome' => $appointment->outcome,
            'is_paid' => $appointment->invoicePayments->sum('amount') >= $appointment->total_amount,
            'created_at' => $appointment->created_at->toDateTimeString(),
        ];
    }
}
