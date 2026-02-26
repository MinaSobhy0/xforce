<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class OnboardingRequest extends Model
{

    protected $connection = 'central';

    protected $table = 'public.onboarding_requests';

    protected $fillable = [
        'clinic_name',
        'slug',
        'owner_name',
        'owner_email',
        'owner_phone',
        'country',
        'city',
        'timezone',
        'subscription_plan_id',
        'promo_code',
        'source',
        'status',
        'notes',
        'rejection_reason',
        'approved_at',
        'rejected_at',
        'provisioned_at',
        'approved_by',
        'rejected_by',
        'tenant_id',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'provisioned_at' => 'datetime',
    ];

    public const STATUSES = [
        'pending' => 'Pending Review',
        'info_requested' => 'Info Requested',
        'in_progress' => 'In Progress',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'provisioned' => 'Provisioned',
    ];

    public const SOURCES = [
        'website' => 'Website Signup',
        'referral' => 'Referral',
        'partner' => 'Partner',
        'manual' => 'Manual Entry',
        'import' => 'Import',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'rejected_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Tenant::class);
    }

    public function approve(string $userId): void
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $userId,
        ]);
    }

    public function reject(string $userId, string $reason): void
    {
        $this->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
        ]);
    }

    public function markProvisioned(string $tenantId): void
    {
        $this->update([
            'status' => 'provisioned',
            'provisioned_at' => now(),
            'tenant_id' => $tenantId,
        ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', ['approved', 'provisioned']);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
