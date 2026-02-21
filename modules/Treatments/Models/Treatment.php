<?php

namespace Modules\Treatments\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Equipment\Models\EquipmentType;

class Treatment extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity, HasSequence;

    protected string $sequenceCode = 'treatment';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'consent_template_id',
        'code',
        'name',
        'description',
        'short_description',
        'duration_minutes',
        'buffer_minutes',
        'base_price_minor',
        'recommended_sessions',
        'session_interval_days',
        'fitzpatrick_min',
        'fitzpatrick_max',
        'contraindications',
        'pre_care_instructions',
        'post_care_instructions',
        'equipment_required',
        'consumables_required',
        'is_active',
        'requires_consent',
        'is_bookable_online',
        'sort_order',
        'image_url',
        'tags',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'short_description' => 'array',
        'pre_care_instructions' => 'array',
        'post_care_instructions' => 'array',
        'contraindications' => 'array',
        'equipment_required' => 'array',
        'consumables_required' => 'array',
        'tags' => 'array',
        'duration_minutes' => 'integer',
        'buffer_minutes' => 'integer',
        'base_price_minor' => 'integer',
        'recommended_sessions' => 'integer',
        'session_interval_days' => 'integer',
        'fitzpatrick_min' => 'integer',
        'fitzpatrick_max' => 'integer',
        'is_active' => 'boolean',
        'requires_consent' => 'boolean',
        'is_bookable_online' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = [
        'name',
        'description',
        'short_description',
        'pre_care_instructions',
        'post_care_instructions',
    ];

    protected $appends = ['translated_name', 'formatted_price'];

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->base_price_minor / 100, 2) . ' EGP';
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TreatmentCategory::class, 'category_id');
    }

    public function consentTemplate(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class);
    }

    public function branchPricing(): HasMany
    {
        return $this->hasMany(TreatmentBranchPricing::class);
    }

    public function requiredEquipmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipmentType::class,
            'treatment_equipment_requirements',
            'treatment_id',
            'equipment_type_id'
        )->withPivot('is_required')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class);
    }

    public function getPriceForBranch(string $branchId): int
    {
        $branchPrice = $this->branchPricing()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        return $branchPrice?->price_minor ?? $this->base_price_minor;
    }

    public function isSafeForFitzpatrick(int $type): bool
    {
        if (!$this->fitzpatrick_min && !$this->fitzpatrick_max) {
            return true;
        }

        $min = $this->fitzpatrick_min ?? 1;
        $max = $this->fitzpatrick_max ?? 6;

        return $type >= $min && $type <= $max;
    }

    public function hasContraindication(string $key): bool
    {
        return in_array($key, $this->contraindications ?? []);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBookableOnline($query)
    {
        return $query->where('is_bookable_online', true)->where('is_active', true);
    }

    public function scopeInCategory($query, string $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('code', 'ilike', "%{$term}%")
                ->orWhereRaw("name->>'en' ILIKE ?", ["%{$term}%"])
                ->orWhereRaw("name->>'ar' ILIKE ?", ["%{$term}%"]);
        });
    }
}
