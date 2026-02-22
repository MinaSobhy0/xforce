<?php

namespace Modules\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Booking\Models\Appointment;
use Modules\Billing\Models\Invoice;
use Modules\Loyalty\Models\LoyaltyTransaction;
use Modules\GiftCards\Models\GiftCard;
use Modules\Api\Http\Resources\AppointmentResource;
use Modules\Api\Http\Resources\InvoiceResource;

class PatientController extends BaseApiController
{
    /**
     * Get patient profile
     */
    public function profile(Request $request): JsonResponse
    {
        $patient = $request->user();

        return $this->success([
            'id' => $patient->id,
            'code' => $patient->code,
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'full_name' => $patient->full_name,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'date_of_birth' => $patient->date_of_birth?->format('Y-m-d'),
            'gender' => $patient->gender,
            'address' => $patient->address,
            'emergency_contact_name' => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
            'loyalty_points' => $patient->loyalty_points ?? 0,
            'total_visits' => $patient->total_visits ?? 0,
            'is_vip' => $patient->is_vip ?? false,
        ]);
    }

    /**
     * Update patient profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|nullable|email|max:255',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'gender' => 'sometimes|nullable|in:male,female',
            'address' => 'sometimes|nullable|string|max:500',
            'emergency_contact_name' => 'sometimes|nullable|string|max:200',
            'emergency_contact_phone' => 'sometimes|nullable|string|max:20',
        ]);

        $patient = $request->user();
        $patient->update($request->only([
            'first_name',
            'last_name',
            'email',
            'date_of_birth',
            'gender',
            'address',
            'emergency_contact_name',
            'emergency_contact_phone',
        ]));

        return $this->success(
            ['updated' => true],
            __('api::api.profile_updated')
        );
    }

    /**
     * Get patient appointments
     */
    public function appointments(Request $request): JsonResponse
    {
        $patient = $request->user();

        $query = Appointment::where('patient_id', $patient->id)
            ->with(['treatment', 'branch', 'practitioner'])
            ->orderByDesc('date');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by upcoming/past
        if ($request->boolean('upcoming')) {
            $query->where('date', '>=', now()->toDateString());
        } elseif ($request->boolean('past')) {
            $query->where('date', '<', now()->toDateString());
        }

        $appointments = $query->paginate($this->getPerPage());

        return $this->paginated($appointments, AppointmentResource::class);
    }

    /**
     * Get patient invoices
     */
    public function invoices(Request $request): JsonResponse
    {
        $patient = $request->user();

        $query = Invoice::where('patient_id', $patient->id)
            ->with(['appointment.treatment'])
            ->orderByDesc('created_at');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by unpaid
        if ($request->boolean('unpaid')) {
            $query->whereIn('status', ['sent', 'partially_paid', 'overdue']);
        }

        $invoices = $query->paginate($this->getPerPage());

        return $this->paginated($invoices, InvoiceResource::class);
    }

    /**
     * Get patient loyalty points
     */
    public function loyaltyPoints(Request $request): JsonResponse
    {
        $patient = $request->user();

        $totalPoints = LoyaltyTransaction::where('patient_id', $patient->id)
            ->sum('points');

        $transactions = LoyaltyTransaction::where('patient_id', $patient->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return $this->success([
            'total_points' => $totalPoints,
            'transactions' => $transactions->map(fn ($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'points' => $t->points,
                'description' => $t->description,
                'balance_after' => $t->balance_after,
                'created_at' => $t->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Check gift card balance
     */
    public function checkGiftCard(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $card = GiftCard::where('code', $request->code)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$card) {
            return $this->error(__('api::api.gift_card_not_found'), 404);
        }

        return $this->success([
            'code' => $card->code,
            'status' => $card->status,
            'initial_balance' => $card->initial_balance_minor / 100,
            'current_balance' => $card->current_balance_minor / 100,
            'currency' => current_currency(),
            'expires_at' => $card->expires_at?->toIso8601String(),
        ]);
    }
}
