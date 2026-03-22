<?php

namespace Modules\MobileApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientsController extends BaseApiController
{
    /**
     * Check if the current user can access a specific patient's data.
     * SECURITY: Prevents IDOR by verifying user has legitimate access.
     *
     * Access is granted if:
     * 1. User has 'patients.view_all' permission
     * 2. User has had appointments with this patient (as practitioner)
     * 3. User is a receptionist/admin with patient management role
     */
    protected function canAccessPatient(int $patientId): bool
    {
        $user = $this->user();

        // Check for global patient access permission
        if (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('patients.view_all')) {
            return true;
        }

        // Check if user has admin/receptionist role that grants patient access
        if (method_exists($user, 'hasRole') && $user->hasRole(['admin', 'tenant_owner', 'receptionist', 'manager'])) {
            return true;
        }

        // For practitioners: only allow access to their own patients
        // (patients they have/had appointments with)
        if (class_exists(\Modules\Booking\Models\Appointment::class)) {
            $hasAppointment = \Modules\Booking\Models\Appointment::where('patient_id', $patientId)
                ->where('practitioner_id', $user->id)
                ->exists();

            if ($hasAppointment) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search patients.
     * GET /api/v2/patients/search?q=
     *
     * SECURITY: Results are filtered based on user's access level.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->success([]);
        }

        $user = $this->user();
        $searchQuery = $request->q;

        $query = \Modules\Patients\Models\Patient::query()
            ->where(function ($q) use ($searchQuery) {
                $q->where('first_name', 'ilike', "%{$searchQuery}%")
                    ->orWhere('last_name', 'ilike', "%{$searchQuery}%")
                    ->orWhere('phone', 'ilike', "%{$searchQuery}%")
                    ->orWhere('email', 'ilike', "%{$searchQuery}%")
                    ->orWhere('patient_number', 'ilike', "%{$searchQuery}%");
            });

        // SECURITY: Filter results based on user's access level
        $hasFullAccess = (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo('patients.view_all'))
            || (method_exists($user, 'hasRole') && $user->hasRole(['admin', 'tenant_owner', 'receptionist', 'manager']));

        if (!$hasFullAccess && class_exists(\Modules\Booking\Models\Appointment::class)) {
            // Practitioners can only search their own patients
            $patientIds = \Modules\Booking\Models\Appointment::where('practitioner_id', $user->id)
                ->distinct()
                ->pluck('patient_id');

            $query->whereIn('id', $patientIds);
        }

        $patients = $query->orderBy('first_name')
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
     *
     * SECURITY: Verifies user has access to this patient before returning data.
     */
    public function show(int $id): JsonResponse
    {
        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->notFound();
        }

        // SECURITY: Check access before fetching patient data
        if (!$this->canAccessPatient($id)) {
            Log::warning('Unauthorized patient access attempt', [
                'user_id' => $this->user()->id,
                'patient_id' => $id,
                'ip' => request()->ip(),
            ]);
            return $this->forbidden(__('mobile_api::mobile.errors.unauthorized_patient_access'));
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
     *
     * SECURITY: Verifies user has access to this patient before returning data.
     */
    public function appointments(Request $request, int $id): JsonResponse
    {
        $staffProfile = $this->staffProfile();

        if (!class_exists(\Modules\Patients\Models\Patient::class)) {
            return $this->notFound();
        }

        // SECURITY: Check access before fetching patient data
        if (!$this->canAccessPatient($id)) {
            Log::warning('Unauthorized patient appointments access attempt', [
                'user_id' => $this->user()->id,
                'patient_id' => $id,
                'ip' => request()->ip(),
            ]);
            return $this->forbidden(__('mobile_api::mobile.errors.unauthorized_patient_access'));
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
            $query->where('practitioner_id', $this->user()->id);
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
     *
     * SECURITY: Verifies user has access to this patient before returning data.
     */
    public function visits(int $id): JsonResponse
    {
        // SECURITY: Check access before fetching patient data
        if (!$this->canAccessPatient($id)) {
            Log::warning('Unauthorized patient visits access attempt', [
                'user_id' => $this->user()->id,
                'patient_id' => $id,
                'ip' => request()->ip(),
            ]);
            return $this->forbidden(__('mobile_api::mobile.errors.unauthorized_patient_access'));
        }

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
