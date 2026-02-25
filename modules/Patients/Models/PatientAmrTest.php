<?php

namespace Modules\Patients\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;

class PatientAmrTest extends BaseModel
{
    use HasTenancy, HasActivity, SoftDeletes;

    protected $table = 'patient_amr_tests';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'lab_accession_number',
        'collection_date',
        'result_date',
        'specimen_source',
        'specimen_site',
        'organism_name',
        'organism_code',
        'is_mdro',
        'mdro_types',
        'antibiotic_results',
        'laboratory_name',
        'ordering_physician_id',
        'clinical_notes',
        'recommendations',
        'created_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'collection_date' => 'date',
        'result_date' => 'date',
        'is_mdro' => 'boolean',
        'mdro_types' => 'array',
        'antibiotic_results' => 'array',
        'verified_at' => 'datetime',
    ];

    // Specimen sources
    public const SPECIMEN_SOURCES = [
        'blood' => 'Blood',
        'urine' => 'Urine',
        'wound' => 'Wound',
        'sputum' => 'Sputum',
        'stool' => 'Stool',
        'throat' => 'Throat',
        'nasal' => 'Nasal',
        'skin' => 'Skin',
        'csf' => 'CSF (Cerebrospinal Fluid)',
        'abscess' => 'Abscess',
        'tissue' => 'Tissue',
        'drainage' => 'Drainage',
        'catheter' => 'Catheter',
        'respiratory' => 'Respiratory',
        'genital' => 'Genital',
        'other' => 'Other',
    ];

    // Common organisms
    public const COMMON_ORGANISMS = [
        'Staphylococcus aureus' => 'Staphylococcus aureus',
        'MRSA' => 'MRSA (Methicillin-resistant S. aureus)',
        'Escherichia coli' => 'Escherichia coli (E. coli)',
        'Pseudomonas aeruginosa' => 'Pseudomonas aeruginosa',
        'Klebsiella pneumoniae' => 'Klebsiella pneumoniae',
        'Enterococcus faecalis' => 'Enterococcus faecalis',
        'Enterococcus faecium' => 'Enterococcus faecium',
        'Streptococcus pneumoniae' => 'Streptococcus pneumoniae',
        'Streptococcus pyogenes' => 'Streptococcus pyogenes',
        'Acinetobacter baumannii' => 'Acinetobacter baumannii',
        'Enterobacter cloacae' => 'Enterobacter cloacae',
        'Proteus mirabilis' => 'Proteus mirabilis',
        'Serratia marcescens' => 'Serratia marcescens',
        'Candida albicans' => 'Candida albicans',
        'Candida auris' => 'Candida auris',
        'Clostridioides difficile' => 'Clostridioides difficile (C. diff)',
        'Haemophilus influenzae' => 'Haemophilus influenzae',
        'Neisseria gonorrhoeae' => 'Neisseria gonorrhoeae',
        'Salmonella species' => 'Salmonella species',
        'Other' => 'Other',
    ];

    // MDRO types
    public const MDRO_TYPES = [
        'MRSA' => 'MRSA (Methicillin-resistant S. aureus)',
        'VRE' => 'VRE (Vancomycin-resistant Enterococcus)',
        'ESBL' => 'ESBL (Extended-spectrum beta-lactamase)',
        'CRE' => 'CRE (Carbapenem-resistant Enterobacteriaceae)',
        'CRKP' => 'CRKP (Carbapenem-resistant K. pneumoniae)',
        'MDR-PA' => 'MDR-PA (Multidrug-resistant P. aeruginosa)',
        'MDR-AB' => 'MDR-AB (Multidrug-resistant A. baumannii)',
        'C.auris' => 'Candida auris',
        'MDR-TB' => 'MDR-TB (Multidrug-resistant tuberculosis)',
    ];

    // Sensitivity levels
    public const SENSITIVITY_LEVELS = [
        'S' => 'Susceptible',
        'I' => 'Intermediate',
        'R' => 'Resistant',
        'SDD' => 'Susceptible-Dose Dependent',
        'NS' => 'Non-susceptible',
    ];

    public const SENSITIVITY_COLORS = [
        'S' => 'success',
        'I' => 'warning',
        'R' => 'danger',
        'SDD' => 'info',
        'NS' => 'danger',
    ];

    // Common antibiotics organized by class
    public const ANTIBIOTIC_CLASSES = [
        'Penicillins' => [
            'penicillin' => 'Penicillin',
            'ampicillin' => 'Ampicillin',
            'amoxicillin' => 'Amoxicillin',
            'amoxicillin_clavulanate' => 'Amoxicillin/Clavulanate (Augmentin)',
            'piperacillin_tazobactam' => 'Piperacillin/Tazobactam (Zosyn)',
            'oxacillin' => 'Oxacillin',
            'nafcillin' => 'Nafcillin',
        ],
        'Cephalosporins' => [
            'cefazolin' => 'Cefazolin (1st gen)',
            'cephalexin' => 'Cephalexin (1st gen)',
            'cefuroxime' => 'Cefuroxime (2nd gen)',
            'cefoxitin' => 'Cefoxitin (2nd gen)',
            'ceftriaxone' => 'Ceftriaxone (3rd gen)',
            'ceftazidime' => 'Ceftazidime (3rd gen)',
            'cefepime' => 'Cefepime (4th gen)',
            'ceftaroline' => 'Ceftaroline (5th gen)',
        ],
        'Carbapenems' => [
            'imipenem' => 'Imipenem',
            'meropenem' => 'Meropenem',
            'ertapenem' => 'Ertapenem',
            'doripenem' => 'Doripenem',
        ],
        'Aminoglycosides' => [
            'gentamicin' => 'Gentamicin',
            'tobramycin' => 'Tobramycin',
            'amikacin' => 'Amikacin',
            'streptomycin' => 'Streptomycin',
        ],
        'Fluoroquinolones' => [
            'ciprofloxacin' => 'Ciprofloxacin',
            'levofloxacin' => 'Levofloxacin',
            'moxifloxacin' => 'Moxifloxacin',
            'ofloxacin' => 'Ofloxacin',
        ],
        'Glycopeptides' => [
            'vancomycin' => 'Vancomycin',
            'teicoplanin' => 'Teicoplanin',
            'daptomycin' => 'Daptomycin',
        ],
        'Macrolides' => [
            'azithromycin' => 'Azithromycin',
            'clarithromycin' => 'Clarithromycin',
            'erythromycin' => 'Erythromycin',
        ],
        'Tetracyclines' => [
            'tetracycline' => 'Tetracycline',
            'doxycycline' => 'Doxycycline',
            'minocycline' => 'Minocycline',
            'tigecycline' => 'Tigecycline',
        ],
        'Sulfonamides' => [
            'trimethoprim_sulfamethoxazole' => 'Trimethoprim/Sulfamethoxazole (Bactrim)',
        ],
        'Other' => [
            'clindamycin' => 'Clindamycin',
            'metronidazole' => 'Metronidazole',
            'linezolid' => 'Linezolid',
            'rifampin' => 'Rifampin',
            'nitrofurantoin' => 'Nitrofurantoin',
            'fosfomycin' => 'Fosfomycin',
            'colistin' => 'Colistin',
            'polymyxin_b' => 'Polymyxin B',
        ],
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function orderingPhysician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordering_physician_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Accessors
    public function getSpecimenSourceLabelAttribute(): string
    {
        return self::SPECIMEN_SOURCES[$this->specimen_source] ?? $this->specimen_source;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }

    public function getResistantAntibioticsAttribute(): array
    {
        if (!$this->antibiotic_results) {
            return [];
        }

        return collect($this->antibiotic_results)
            ->filter(fn ($result) => ($result['sensitivity'] ?? '') === 'R')
            ->pluck('antibiotic')
            ->toArray();
    }

    public function getSensitiveAntibioticsAttribute(): array
    {
        if (!$this->antibiotic_results) {
            return [];
        }

        return collect($this->antibiotic_results)
            ->filter(fn ($result) => ($result['sensitivity'] ?? '') === 'S')
            ->pluck('antibiotic')
            ->toArray();
    }

    public function getIntermediateAntibioticsAttribute(): array
    {
        if (!$this->antibiotic_results) {
            return [];
        }

        return collect($this->antibiotic_results)
            ->filter(fn ($result) => in_array($result['sensitivity'] ?? '', ['I', 'SDD']))
            ->pluck('antibiotic')
            ->toArray();
    }

    public function getResistanceCountAttribute(): int
    {
        return count($this->resistant_antibiotics);
    }

    public function getSensitivityCountAttribute(): int
    {
        return count($this->sensitive_antibiotics);
    }

    public function getMdroTypesDisplayAttribute(): string
    {
        if (!$this->mdro_types) {
            return '';
        }

        return collect($this->mdro_types)
            ->map(fn ($type) => self::MDRO_TYPES[$type] ?? $type)
            ->join(', ');
    }

    // Helper methods
    public function verify(string $userId): bool
    {
        $this->verified_by = $userId;
        $this->verified_at = now();
        $result = $this->save();

        // Update the patient's AMR summary
        $this->updatePatientSummary();

        return $result;
    }

    public function updatePatientSummary(): void
    {
        $summary = PatientAmrSummary::firstOrCreate(
            ['patient_id' => $this->patient_id],
            ['tenant_id' => $this->tenant_id]
        );

        $summary->recomputeFromTests();
    }

    public function hasResistanceTo(string $antibiotic): bool
    {
        return in_array(strtolower($antibiotic), array_map('strtolower', $this->resistant_antibiotics));
    }

    public function hasSensitivityTo(string $antibiotic): bool
    {
        return in_array(strtolower($antibiotic), array_map('strtolower', $this->sensitive_antibiotics));
    }

    public function getSensitivityFor(string $antibiotic): ?string
    {
        if (!$this->antibiotic_results) {
            return null;
        }

        $result = collect($this->antibiotic_results)
            ->first(fn ($r) => strtolower($r['antibiotic'] ?? '') === strtolower($antibiotic));

        return $result['sensitivity'] ?? null;
    }

    // Static helpers
    public static function getAntibioticOptions(): array
    {
        $options = [];
        foreach (self::ANTIBIOTIC_CLASSES as $class => $antibiotics) {
            foreach ($antibiotics as $key => $label) {
                $options[$key] = "{$label} ({$class})";
            }
        }
        return $options;
    }

    public static function getAntibioticLabel(string $key): string
    {
        foreach (self::ANTIBIOTIC_CLASSES as $antibiotics) {
            if (isset($antibiotics[$key])) {
                return $antibiotics[$key];
            }
        }
        return $key;
    }

    // Scopes
    public function scopeForPatient($query, string $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('verified_at');
    }

    public function scopeUnverified($query)
    {
        return $query->whereNull('verified_at');
    }

    public function scopeMdro($query)
    {
        return $query->where('is_mdro', true);
    }

    public function scopeByOrganism($query, string $organism)
    {
        return $query->where('organism_name', 'ilike', "%{$organism}%");
    }

    public function scopeBySpecimenSource($query, string $source)
    {
        return $query->where('specimen_source', $source);
    }

    public function scopeRecent($query, int $days = 90)
    {
        return $query->where('collection_date', '>=', now()->subDays($days));
    }

    public function scopeOrderedByDate($query, string $direction = 'desc')
    {
        return $query->orderBy('collection_date', $direction);
    }

    // Boot
    protected static function booted(): void
    {
        parent::booted();

        static::saved(function (PatientAmrTest $test) {
            $test->updatePatientSummary();
        });

        static::deleted(function (PatientAmrTest $test) {
            $test->updatePatientSummary();
        });
    }
}
