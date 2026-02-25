<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class PatientAmrSummary extends BaseModel
{
    use HasTenancy;

    protected $table = 'patient_amr_summaries';

    // Disable timestamps management since we handle last_updated_at manually
    public $timestamps = true;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'known_organisms',
        'known_resistances',
        'known_sensitivities',
        'mdro_flags',
        'has_critical_resistance',
        'alert_notes',
        'last_test_id',
        'last_test_date',
        'last_updated_by',
        'last_updated_at',
    ];

    protected $casts = [
        'known_organisms' => 'array',
        'known_resistances' => 'array',
        'known_sensitivities' => 'array',
        'mdro_flags' => 'array',
        'has_critical_resistance' => 'boolean',
        'last_test_date' => 'date',
        'last_updated_at' => 'datetime',
    ];

    // Critical antibiotics (resistance to these is concerning)
    public const CRITICAL_ANTIBIOTICS = [
        'vancomycin',
        'meropenem',
        'imipenem',
        'colistin',
        'linezolid',
        'daptomycin',
        'ceftaroline',
        'tigecycline',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function lastTest(): BelongsTo
    {
        return $this->belongsTo(PatientAmrTest::class, 'last_test_id');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    // Accessors
    public function getHasAnyDataAttribute(): bool
    {
        return !empty($this->known_organisms) ||
            !empty($this->known_resistances) ||
            !empty($this->mdro_flags);
    }

    public function getOrganismCountAttribute(): int
    {
        return count($this->known_organisms ?? []);
    }

    public function getResistanceCountAttribute(): int
    {
        return count($this->known_resistances ?? []);
    }

    public function getSensitivityCountAttribute(): int
    {
        return count($this->known_sensitivities ?? []);
    }

    public function getMdroCountAttribute(): int
    {
        return count($this->mdro_flags ?? []);
    }

    public function getHasMdroAttribute(): bool
    {
        return !empty($this->mdro_flags);
    }

    public function getMdroFlagsDisplayAttribute(): string
    {
        if (!$this->mdro_flags) {
            return '';
        }

        return collect($this->mdro_flags)
            ->map(fn ($flag) => PatientAmrTest::MDRO_TYPES[$flag] ?? $flag)
            ->join(', ');
    }

    public function getAlertLevelAttribute(): string
    {
        if ($this->has_critical_resistance || !empty($this->mdro_flags)) {
            return 'critical';
        }

        if (!empty($this->known_resistances)) {
            return 'warning';
        }

        return 'info';
    }

    public function getAlertColorAttribute(): string
    {
        return match ($this->alert_level) {
            'critical' => 'danger',
            'warning' => 'warning',
            default => 'info',
        };
    }

    // Helper methods
    public function hasResistanceTo(string $antibiotic): bool
    {
        if (!$this->known_resistances) {
            return false;
        }

        return collect($this->known_resistances)
            ->contains(fn ($r) => strtolower($r) === strtolower($antibiotic));
    }

    public function hasSensitivityTo(string $antibiotic): bool
    {
        if (!$this->known_sensitivities) {
            return false;
        }

        return collect($this->known_sensitivities)
            ->contains(fn ($s) => strtolower($s) === strtolower($antibiotic));
    }

    public function hasMdroFlag(string $type): bool
    {
        if (!$this->mdro_flags) {
            return false;
        }

        return in_array($type, $this->mdro_flags);
    }

    public function hasOrganism(string $organism): bool
    {
        if (!$this->known_organisms) {
            return false;
        }

        return collect($this->known_organisms)
            ->contains(fn ($o) => stripos($o, $organism) !== false);
    }

    /**
     * Recompute summary from all patient's AMR tests
     */
    public function recomputeFromTests(): void
    {
        $tests = PatientAmrTest::where('patient_id', $this->patient_id)
            ->orderBy('collection_date', 'desc')
            ->get();

        if ($tests->isEmpty()) {
            $this->fill([
                'known_organisms' => [],
                'known_resistances' => [],
                'known_sensitivities' => [],
                'mdro_flags' => [],
                'has_critical_resistance' => false,
                'last_test_id' => null,
                'last_test_date' => null,
                'last_updated_at' => now(),
            ]);
            $this->save();
            return;
        }

        // Aggregate organisms
        $organisms = $tests->pluck('organism_name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Aggregate MDRO flags
        $mdroFlags = $tests->pluck('mdro_types')
            ->flatten()
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Aggregate resistances (from most recent tests only - last 6 months)
        $recentTests = $tests->filter(fn ($t) => $t->collection_date->gte(now()->subMonths(6)));

        $resistances = $recentTests->flatMap(fn ($t) => $t->resistant_antibiotics)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $sensitivities = $recentTests->flatMap(fn ($t) => $t->sensitive_antibiotics)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        // Check for critical resistance
        $hasCriticalResistance = collect($resistances)
            ->intersect(self::CRITICAL_ANTIBIOTICS)
            ->isNotEmpty();

        // Get latest test
        $latestTest = $tests->first();

        $this->fill([
            'known_organisms' => $organisms,
            'known_resistances' => $resistances,
            'known_sensitivities' => $sensitivities,
            'mdro_flags' => $mdroFlags,
            'has_critical_resistance' => $hasCriticalResistance,
            'last_test_id' => $latestTest->id,
            'last_test_date' => $latestTest->collection_date,
            'last_updated_at' => now(),
            'last_updated_by' => auth()->id(),
        ]);

        $this->save();
    }

    /**
     * Get a formatted alert message for display
     */
    public function getAlertMessage(): ?string
    {
        $messages = [];

        if (!empty($this->mdro_flags)) {
            $flags = collect($this->mdro_flags)
                ->map(fn ($f) => PatientAmrTest::MDRO_TYPES[$f] ?? $f)
                ->join(', ');
            $messages[] = "MDRO Alert: {$flags}";
        }

        if ($this->has_critical_resistance) {
            $critical = collect($this->known_resistances)
                ->intersect(self::CRITICAL_ANTIBIOTICS)
                ->map(fn ($a) => PatientAmrTest::getAntibioticLabel($a))
                ->join(', ');
            $messages[] = "Critical Resistance: {$critical}";
        }

        if (!empty($this->known_resistances) && !$this->has_critical_resistance) {
            $count = count($this->known_resistances);
            $messages[] = "Known resistances to {$count} antibiotic(s)";
        }

        return empty($messages) ? null : implode(' | ', $messages);
    }

    /**
     * Get formatted resistances for display
     */
    public function getFormattedResistances(): array
    {
        if (!$this->known_resistances) {
            return [];
        }

        return collect($this->known_resistances)
            ->map(fn ($antibiotic) => [
                'key' => $antibiotic,
                'label' => PatientAmrTest::getAntibioticLabel($antibiotic),
                'is_critical' => in_array($antibiotic, self::CRITICAL_ANTIBIOTICS),
            ])
            ->sortByDesc('is_critical')
            ->values()
            ->toArray();
    }

    /**
     * Get formatted sensitivities for display
     */
    public function getFormattedSensitivities(): array
    {
        if (!$this->known_sensitivities) {
            return [];
        }

        return collect($this->known_sensitivities)
            ->map(fn ($antibiotic) => [
                'key' => $antibiotic,
                'label' => PatientAmrTest::getAntibioticLabel($antibiotic),
            ])
            ->values()
            ->toArray();
    }

    // Scopes
    public function scopeWithMdro($query)
    {
        return $query->whereJsonLength('mdro_flags', '>', 0);
    }

    public function scopeWithCriticalResistance($query)
    {
        return $query->where('has_critical_resistance', true);
    }

    public function scopeWithAlerts($query)
    {
        return $query->where(function ($q) {
            $q->where('has_critical_resistance', true)
                ->orWhereJsonLength('mdro_flags', '>', 0);
        });
    }

    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('last_test_date', '>=', now()->subDays($days));
    }
}
