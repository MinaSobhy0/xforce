<?php

namespace Modules\Prescriptions\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class MedicineCatalog extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'medicine_catalog';

    protected $fillable = [
        'tenant_id',
        'brand_name',
        'generic_name',
        'manufacturer',
        'strength',
        'strength_unit',
        'form',
        'default_dosage',
        'default_dosage_unit',
        'default_frequency',
        'default_duration',
        'default_duration_unit',
        'default_route',
        'default_instructions',
        'description',
        'indications',
        'contraindications',
        'side_effects',
        'warnings',
        'category',
        'drug_class',
        'is_controlled',
        'requires_prescription',
        'is_active',
        'is_system',
    ];

    protected $casts = [
        'default_duration' => 'integer',
        'is_controlled' => 'boolean',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    // Categories
    public const CATEGORIES = [
        'antibiotic' => 'Antibiotic',
        'analgesic' => 'Analgesic / Pain Relief',
        'antipyretic' => 'Antipyretic / Fever',
        'anti_inflammatory' => 'Anti-inflammatory',
        'antihistamine' => 'Antihistamine / Allergy',
        'antacid' => 'Antacid / GI',
        'antihypertensive' => 'Antihypertensive',
        'antidiabetic' => 'Antidiabetic',
        'cardiovascular' => 'Cardiovascular',
        'respiratory' => 'Respiratory',
        'dermatological' => 'Dermatological / Skin',
        'ophthalmic' => 'Ophthalmic / Eye',
        'otic' => 'Otic / Ear',
        'vitamin' => 'Vitamins & Supplements',
        'muscle_relaxant' => 'Muscle Relaxant',
        'antidepressant' => 'Antidepressant',
        'anxiolytic' => 'Anxiolytic / Anti-anxiety',
        'antiemetic' => 'Antiemetic / Nausea',
        'laxative' => 'Laxative',
        'antidiarrheal' => 'Antidiarrheal',
        'antifungal' => 'Antifungal',
        'antiviral' => 'Antiviral',
        'hormone' => 'Hormones',
        'immunosuppressant' => 'Immunosuppressant',
        'other' => 'Other',
    ];

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_system', false);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeControlled(Builder $query): Builder
    {
        return $query->where('is_controlled', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('brand_name', 'ilike', "%{$term}%")
              ->orWhere('generic_name', 'ilike', "%{$term}%")
              ->orWhere('manufacturer', 'ilike', "%{$term}%");
        });
    }

    /**
     * Include system medicines (tenant_id = null) in queries.
     */
    public function scopeWithSystemMedicines(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', current_tenant_id());
        });
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $name = $this->brand_name;

        if ($this->strength && $this->strength_unit) {
            $name .= " {$this->strength}{$this->strength_unit}";
        }

        if ($this->form) {
            $formLabel = PrescriptionItem::FORMS[$this->form] ?? $this->form;
            $name .= " ({$formLabel})";
        }

        return $name;
    }

    public function getDisplayNameAttribute(): string
    {
        $name = $this->brand_name;

        if ($this->generic_name) {
            $name .= " ({$this->generic_name})";
        }

        return $name;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category ?? '';
    }

    public function getStrengthDisplayAttribute(): string
    {
        if (!$this->strength) {
            return '';
        }

        return $this->strength . ($this->strength_unit ?? '');
    }

    public function getFormLabelAttribute(): string
    {
        return PrescriptionItem::FORMS[$this->form] ?? $this->form ?? '';
    }

    public function getFrequencyLabelAttribute(): string
    {
        return PrescriptionItem::FREQUENCIES[$this->default_frequency] ?? $this->default_frequency ?? '';
    }

    public function getRouteLabelAttribute(): string
    {
        return PrescriptionItem::ROUTES[$this->default_route] ?? $this->default_route ?? '';
    }

    public function getInstructionsLabelAttribute(): string
    {
        return PrescriptionItem::INSTRUCTIONS[$this->default_instructions] ?? $this->default_instructions ?? '';
    }

    // Methods
    public function canDelete(): bool
    {
        return !$this->is_system;
    }

    public function canEdit(): bool
    {
        // System medicines can only be edited by platform admin
        if ($this->is_system) {
            return false;
        }

        return true;
    }

    /**
     * Convert to prescription item data array.
     */
    public function toPrescriptionItemData(): array
    {
        return [
            'medication_name' => $this->brand_name,
            'generic_name' => $this->generic_name,
            'dosage' => $this->default_dosage ?? $this->strength,
            'dosage_unit' => $this->default_dosage_unit ?? $this->strength_unit,
            'form' => $this->form,
            'frequency' => $this->default_frequency,
            'duration' => $this->default_duration,
            'duration_unit' => $this->default_duration_unit ?? 'days',
            'route' => $this->default_route,
            'instructions' => $this->default_instructions,
            'quantity' => '',
            'special_instructions' => '',
        ];
    }
}
