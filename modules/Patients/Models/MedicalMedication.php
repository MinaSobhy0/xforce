<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicalMedication extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'medical_medications';

    protected $fillable = [
        'tenant_id',
        'medical_profile_id',
        'medication_name',
        'generic_name',
        'dosage',
        'frequency',
        'route',
        'reason',
        'start_date',
        'end_date',
        'is_ongoing',
        'affects_treatment',
        'treatment_implications',
        'prescribing_doctor',
        'is_otc',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_ongoing' => 'boolean',
        'affects_treatment' => 'boolean',
        'is_otc' => 'boolean',
    ];

    public const ROUTES = [
        'oral' => 'Oral',
        'topical' => 'Topical',
        'injection' => 'Injection',
        'inhalation' => 'Inhalation',
        'sublingual' => 'Sublingual',
        'transdermal' => 'Transdermal',
        'other' => 'Other',
    ];

    public const FREQUENCIES = [
        'once_daily' => 'Once daily',
        'twice_daily' => 'Twice daily',
        'three_times_daily' => '3 times daily',
        'four_times_daily' => '4 times daily',
        'as_needed' => 'As needed (PRN)',
        'weekly' => 'Weekly',
        'other' => 'Other',
    ];

    // Relationships
    public function medicalProfile(): BelongsTo
    {
        return $this->belongsTo(MedicalProfile::class);
    }

    // Accessors
    public function getFullDisplayAttribute(): string
    {
        $parts = [$this->medication_name];
        if ($this->dosage) {
            $parts[] = $this->dosage;
        }
        if ($this->frequency) {
            $parts[] = "({$this->frequency})";
        }
        return implode(' ', $parts);
    }

    // Scopes
    public function scopeOngoing($query)
    {
        return $query->where('is_ongoing', true);
    }

    public function scopeDiscontinued($query)
    {
        return $query->where('is_ongoing', false);
    }

    public function scopeAffectsTreatment($query)
    {
        return $query->where('affects_treatment', true);
    }

    public function scopePrescription($query)
    {
        return $query->where('is_otc', false);
    }

    public function scopeOverTheCounter($query)
    {
        return $query->where('is_otc', true);
    }

    // Helper Methods
    public function discontinue(): void
    {
        $this->update([
            'is_ongoing' => false,
            'end_date' => now(),
        ]);
    }
}
