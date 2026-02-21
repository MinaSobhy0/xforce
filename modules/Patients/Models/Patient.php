<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;

class Patient extends BaseModel implements Authenticatable
{
    use HasTenancy,
        HasActivity,
        HasSequence,
        SoftDeletes,
        AuthenticatableTrait;

    /**
     * Sequence code for auto-generation.
     */
    protected string $sequenceCode = 'patient';

    /**
     * Sequence column name.
     */
    protected string $sequenceColumn = 'code';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'secondary_phone',
        'date_of_birth',
        'gender',
        'national_id',
        'address',
        'city',
        'country',
        'postal_code',
        'occupation',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'referral_source',
        'referred_by_patient_id',
        'referred_by_name',
        'language',
        'status',
        'tags',
        'notes',
        'portal_access_enabled',
        'portal_password',
        'last_visit_at',
        'total_visits',
        'total_spent_minor',
        'loyalty_points',
        'marketing_consent',
        'sms_consent',
        'email_consent',
        'whatsapp_consent',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'tags' => 'array',
        'portal_access_enabled' => 'boolean',
        'last_visit_at' => 'datetime',
        'total_visits' => 'integer',
        'total_spent_minor' => 'integer',
        'loyalty_points' => 'integer',
        'marketing_consent' => 'boolean',
        'sms_consent' => 'boolean',
        'email_consent' => 'boolean',
        'whatsapp_consent' => 'boolean',
    ];

    /**
     * The attributes that should be hidden.
     */
    protected $hidden = [
        'portal_password',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'full_name',
        'age',
        'display_name',
    ];

    /**
     * Get the full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get the age.
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    /**
     * Get the display name (for UI).
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code ? "{$this->full_name} ({$this->code})" : $this->full_name;
    }

    /**
     * Get the branch this patient belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Branch::class);
    }

    /**
     * Get the patient's medical history.
     */
    public function medicalHistory(): HasOne
    {
        return $this->hasOne(PatientMedicalHistory::class);
    }

    /**
     * Get the patient's consent forms.
     */
    public function consentForms(): HasMany
    {
        return $this->hasMany(PatientConsentForm::class);
    }

    /**
     * Get the patient's photos.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(PatientPhoto::class);
    }

    /**
     * Get the patient's notes.
     */
    public function notes(): HasMany
    {
        return $this->hasMany(PatientNote::class)->orderByDesc('created_at');
    }

    /**
     * Get the patient who referred this patient.
     */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Patient::class, 'referred_by_patient_id');
    }

    /**
     * Get patients referred by this patient.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Patient::class, 'referred_by_patient_id');
    }

    /**
     * Check if patient has signed a specific consent template.
     */
    public function hasSignedConsent(string $templateId): bool
    {
        return $this->consentForms()
            ->where('consent_template_id', $templateId)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->exists();
    }

    /**
     * Get before photos.
     */
    public function beforePhotos(): HasMany
    {
        return $this->photos()->where('type', 'before');
    }

    /**
     * Get after photos.
     */
    public function afterPhotos(): HasMany
    {
        return $this->photos()->where('type', 'after');
    }

    /**
     * Increment visit count.
     */
    public function recordVisit(): void
    {
        $this->increment('total_visits');
        $this->update(['last_visit_at' => now()]);
    }

    /**
     * Add to total spent.
     */
    public function addSpending(int $amountMinor): void
    {
        $this->increment('total_spent_minor', $amountMinor);
    }

    /**
     * Scope: Active patients.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: Search by name, phone, email, or code.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('first_name', 'ilike', "%{$term}%")
                ->orWhere('last_name', 'ilike', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('email', 'ilike', "%{$term}%")
                ->orWhere('code', 'ilike', "%{$term}%");
        });
    }

    /**
     * Scope: New patients this month.
     */
    public function scopeNewThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Scope: By referral source.
     */
    public function scopeByReferralSource($query, string $source)
    {
        return $query->where('referral_source', $source);
    }

    /**
     * Scope: By gender.
     */
    public function scopeByGender($query, string $gender)
    {
        return $query->where('gender', $gender);
    }

    /**
     * Scope: Has visited in last X days.
     */
    public function scopeRecentVisitors($query, int $days = 30)
    {
        return $query->where('last_visit_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: No visit in last X days.
     */
    public function scopeInactiveVisitors($query, int $days = 90)
    {
        return $query->where(function ($q) use ($days) {
            $q->where('last_visit_at', '<', now()->subDays($days))
                ->orWhereNull('last_visit_at');
        });
    }
}
