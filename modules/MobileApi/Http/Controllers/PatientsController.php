<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientsController extends BaseApiController
{
    /**
     * Search patients.
     * GET /api/v2/patients/search?q=
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->success([]);
        }

        $query = $request->q;

        $patients = \Modules\Patients\Models\Patient::query()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'ilike', "%{$query}%")
                    ->orWhere('last_name', 'ilike', "%{$query}%")
                    ->orWhere('phone', 'ilike', "%{$query}%")
                    ->orWhere('email', 'ilike', "%{$query}%")
                    ->orWhere('patient_number', 'ilike', "%{$query}%");
            })
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        if ($patients->isEmpty()) {
            return $this->success([
                'results' => [],
                'message' => __('mobile_api::mobile.patients.no_results'),
            ]);
        }

        return $this->success([
            'results' => $patients->map(fn($p) => [
                'id' => $p->id,
                'patient_number' => $p->patient_number,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'email' => $p->email,
                'avatar_url' => $p->avatar_url,
            ])->all(),
        ]);
    }

    /**
     * Get patient details.
     * GET /api/v2/patients/{id}
     */
    public function show(int $id): JsonResponse
    {
        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->notFound();
        }

        $patient = \Modules\Patients\Models\Patient::find($id);

        if (!$patient) {
            return $this->notFound();
        }

        return $this->success([
            'id' => $patient->id,
            'patient_number' => $patient->patient_number,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'full_name' => $patient->full_name,
            'phone' => $patient->phone,
            'email' => $patient->email,
            'date_of_birth' => $patient->date_of_birth?->toDateString(),
            'age' => $patient->date_of_birth?->age,
            'gender' => $patient->gender,
            'address' => $patient->address,
            'avatar_url' => $patient->avatar_url,
            'notes' => $patient->notes,
            'medical_history' => $patient->medical_history ?? [],
            'allergies' => $patient->allergies ?? [],
            'skin_type' => $patient->skin_type,
            'fitzpatrick_scale' => $patient->fitzpatrick_scale,
            'created_at' => $patient->created_at->toDateString(),
            'last_visit' => $patient->last_visit_at?->toDateString(),
        ]);
    }

    /**
     * Get patient's appointments.
     * GET /api/v2/patients/{id}/appointments
     */
    public function appointments(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->notFound();
        }

        $patient = \Modules\Patients\Models\Patient::find($id);

        if (!$patient) {
            return $this->notFound();
        }

        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->success([]);
        }

        $query = \Modules\Booking\Models\Appointment::with('service')
            ->where('patient_id', $patient->id);

        // Optionally filter to only this practitioner's appointments
        if ($request->boolean('mine_only')) {
            $query->where('practitioner_id', $staffProfile->id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderByDesc('date')
            ->orderByDesc('start_time')
            ->paginate($this->getPerPage());

        return $this->paginated($appointments);
    }

    /**
     * Get patient's visit history.
     * GET /api/v2/patients/{id}/visits
     */
    public function visits(int $id): JsonResponse
    {
        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->notFound();
        }

        $patient = \Modules\Patients\Models\Patient::find($id);

        if (!$patient) {
            return $this->notFound();
        }

        // Get completed appointments as visits
        if (!class_exists(\Modules\Booking\Models\Appointment::class)) {
            return $this->success([]);
        }

        $visits = \Modules\Booking\Models\Appointment::with(['service', 'practitioner.user'])
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get();

        return $this->success([
            'total_visits' => $visits->count(),
            'visits' => $visits->map(fn($v) => [
                'id' => $v->id,
                'date' => $v->completed_at?->toDateString() ?? $v->date->toDateString(),
                'service' => $v->service?->name,
                'practitioner' => $v->practitioner?->user?->full_name,
                'notes' => $v->practitioner_notes,
                'outcome' => $v->outcome,
            ])->all(),
        ]);
    }
}
