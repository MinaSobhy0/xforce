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
        'phone_country_code',
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
        'created_by_user_id',
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

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Patient $patient) {
            // Track who created the patient
            if (empty($patient->created_by_user_id) && auth()->check()) {
                $patient->created_by_user_id = auth()->id();
            }
        });
    }

    /**
     * Get the user who created this patient.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Modules\Auth\Models\User::class, 'created_by_user_id');
    }

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
     * Get the international phone number (with country code).
     */
    public function getInternationalPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }

        $phone = preg_replace('/[^0-9]/', '', $this->phone);
        $countryCode = $this->phone_country_code ?? '+20';

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // Ensure country code starts with +
        if (!str_starts_with($countryCode, '+')) {
            $countryCode = '+' . $countryCode;
        }

        return $countryCode . $phone;
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
     * Get the patient's appointments.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class);
    }

    /**
     * Get the patient's invoices.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(\Modules\Billing\Models\Invoice::class);
    }

    /**
     * Get the patient's visits.
     */
    public function visits(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Visit::class)->orderByDesc('check_in_at');
    }

    /**
     * Get the patient's package subscriptions.
     */
    public function packageSubscriptions(): HasMany
    {
        return $this->hasMany(\Modules\Packages\Models\PackageSubscription::class);
    }

    public function treatmentPlans(): HasMany
    {
        return $this->hasMany(\Modules\TreatmentPlans\Models\TreatmentPlan::class);
    }

    /**
     * Get the patient's loyalty transactions.
     */
    public function loyaltyTransactions(): HasMany
    {
        return $this->hasMany(\Modules\Loyalty\Models\LoyaltyTransaction::class);
    }

    /**
     * Get the patient's medical profile (AMR).
     */
    public function medicalProfile(): HasOne
    {
        return $this->hasOne(\Modules\Patients\Models\MedicalProfile::class);
    }

    /**
     * Get the patient's AMR tests.
     */
    public function amrTests(): HasMany
    {
        return $this->hasMany(PatientAmrTest::class)->orderByDesc('collection_date');
    }

    /**
     * Get the patient's AMR summary.
     */
    public function amrSummary(): HasOne
    {
        return $this->hasOne(PatientAmrSummary::class);
    }

    /**
     * Check if patient has any MDRO flags.
     */
    public function hasMdroFlags(): bool
    {
        return $this->amrSummary && !empty($this->amrSummary->mdro_flags);
    }

    /**
     * Check if patient has critical resistance.
     */
    public function hasCriticalResistance(): bool
    {
        return $this->amrSummary && $this->amrSummary->has_critical_resistance;
    }

    /**
     * Get known resistances.
     */
    public function getKnownResistancesAttribute(): array
    {
        return $this->amrSummary?->known_resistances ?? [];
    }

    /**
     * Get or create the patient's AMR summary.
     */
    public function getOrCreateAmrSummary(): PatientAmrSummary
    {
        if (!$this->amrSummary) {
            return PatientAmrSummary::create([
                'tenant_id' => $this->tenant_id,
                'patient_id' => $this->id,
            ]);
        }
        return $this->amrSummary;
    }

    /**
     * Check if patient has a medical profile.
     */
    public function hasMedicalProfile(): bool
    {
        return $this->medicalProfile !== null;
    }

    /**
     * Get or create the patient's medical profile.
     */
    public function getOrCreateMedicalProfile(): \Modules\Patients\Models\MedicalProfile
    {
        if (!$this->medicalProfile) {
            return \Modules\Patients\Models\MedicalProfile::create([
                'tenant_id' => $this->tenant_id,
                'patient_id' => $this->id,
            ]);
        }
        return $this->medicalProfile;
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

    /**
     * Get patient's balance from accounting entries.
     * Positive = patient owes money
     * Negative = patient has credit (overpaid)
     */
    public function getBalanceMinorAttribute(): int
    {
        return \Modules\Accounting\Models\JournalEntryLine::query()
            ->where('partner_type', self::class)
            ->where('partner_id', $this->id)
            ->whereHas('account', fn($q) => $q->where('type', 'receivable'))
            ->whereHas('journalEntry', fn($q) => $q->where('status', 'posted'))
            ->sum(\Illuminate\Support\Facades\DB::raw('debit_minor - credit_minor'));
    }

    /**
     * Get formatted balance.
     */
    public function getFormattedBalanceAttribute(): string
    {
        return format_money($this->balance_minor);
    }

    /**
     * Check if patient has outstanding balance.
     */
    public function hasOutstandingBalance(): bool
    {
        return $this->balance_minor > 0;
    }

    /**
     * Check if patient has credit (overpaid).
     */
    public function hasCredit(): bool
    {
        return $this->balance_minor < 0;
    }

    /**
     * Get balance status color for UI.
     */
    public function getBalanceStatusColorAttribute(): string
    {
        if ($this->balance_minor > 0) {
            return 'danger'; // Owes money
        } elseif ($this->balance_minor < 0) {
            return 'success'; // Has credit
        }
        return 'gray'; // Zero balance
    }

    /**
     * Get balance status label.
     */
    public function getBalanceStatusLabelAttribute(): string
    {
        if ($this->balance_minor > 0) {
            return __('patients::patients.balance.owes');
        } elseif ($this->balance_minor < 0) {
            return __('patients::patients.balance.credit');
        }
        return __('patients::patients.balance.settled');
    }
}
