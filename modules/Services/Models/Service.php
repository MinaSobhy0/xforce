<?php

namespace Modules\Services\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Core\Models\Room;
use Modules\Equipment\Models\Equipment;
use Modules\Inventory\Models\Product;
use Modules\Staff\Models\StaffProfile;
use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;

class Service extends BaseModel
{
    use HasActivity, HasSequence, HasTenancy, HasTranslation;

    protected $table = 'services';

    protected string $sequenceCode = 'service';

    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'unearned_revenue_account_id',
        'service_revenue_account_id',
        'consent_template_id',
        'parameter_template_id',
        'parameter_mode',
        'has_dynamic_parameters',
        'code',
        'name',
        'description',
        'short_description',
        'duration_minutes',
        'buffer_minutes',
        'base_price_minor',
        'max_discount_percent',
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
        'is_consultation',
        'requires_consent',
        'is_bookable_online',
        'time_slot_restrictions',
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
        'max_discount_percent' => 'integer',
        'recommended_sessions' => 'integer',
        'session_interval_days' => 'integer',
        'fitzpatrick_min' => 'integer',
        'fitzpatrick_max' => 'integer',
        'is_active' => 'boolean',
        'is_consultation' => 'boolean',
        'requires_consent' => 'boolean',
        'is_bookable_online' => 'boolean',
        'time_slot_restrictions' => 'array',
        'sort_order' => 'integer',
        'has_dynamic_parameters' => 'boolean',
    ];

    public array $translatable = [
        'name',
        'description',
        'short_description',
        'pre_care_instructions',
        'post_care_instructions',
    ];

    protected $appends = ['translated_name', 'formatted_price'];

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            // Auto-assign parameter_template_id from category if not set
            if (! $service->parameter_template_id && $service->category_id) {
                $category = ServiceCategory::find($service->category_id);
                if ($category && $category->default_parameter_template_id) {
                    $service->parameter_template_id = $category->default_parameter_template_id;
                    // Also enable template mode if we're assigning a template
                    if ($service->parameter_mode === 'none') {
                        $service->parameter_mode = 'template';
                    }
                }
            }
        });
    }

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->base_price_minor / 100, 2).' '.current_currency();
    }

    public function getTotalDurationAttribute(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function unearnedRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unearned_revenue_account_id');
    }

    public function serviceRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'service_revenue_account_id');
    }

    /**
     * Get the effective unearned revenue account.
     * Priority: Service's own account -> Category's account -> System default
     */
    public function getEffectiveUnearnedRevenueAccount(): ?ChartOfAccount
    {
        // First try service's own account
        if ($this->unearned_revenue_account_id) {
            return $this->unearnedRevenueAccount;
        }

        // Fall back to category's account
        if ($this->category && $this->category->unearned_revenue_account_id) {
            return $this->category->unearnedRevenueAccount;
        }

        return null;
    }

    /**
     * Get the effective service revenue account.
     * Priority: Service's own account -> Category's account -> System default
     */
    public function getEffectiveServiceRevenueAccount(): ?ChartOfAccount
    {
        // First try service's own account
        if ($this->service_revenue_account_id) {
            return $this->serviceRevenueAccount;
        }

        // Fall back to category's account
        if ($this->category && $this->category->service_revenue_account_id) {
            return $this->category->serviceRevenueAccount;
        }

        return null;
    }

    /**
     * Get the effective unearned revenue account ID.
     */
    public function getEffectiveUnearnedRevenueAccountId(): ?int
    {
        return $this->unearned_revenue_account_id
            ?? $this->category?->unearned_revenue_account_id;
    }

    /**
     * Get the effective service revenue account ID.
     */
    public function getEffectiveServiceRevenueAccountId(): ?int
    {
        return $this->service_revenue_account_id
            ?? $this->category?->service_revenue_account_id;
    }

    public function consentTemplate(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class);
    }

    public function parameterTemplate(): BelongsTo
    {
        return $this->belongsTo(ParameterTemplate::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ServiceParameter::class);
    }

    public function activeParameters(): HasMany
    {
        return $this->hasMany(ServiceParameter::class)
            ->where('is_active', true)
            ->orderBy('display_order');
    }

    public function parameterPresets(): HasMany
    {
        return $this->hasMany(ParameterPreset::class);
    }

    public function activePresets(): HasMany
    {
        return $this->hasMany(ParameterPreset::class)
            ->where('is_active', true);
    }

    public function defaultPreset(): HasOne
    {
        return $this->hasOne(ParameterPreset::class)
            ->where('is_default', true)
            ->where('is_active', true);
    }

    public function branchPricing(): HasMany
    {
        return $this->hasMany(ServiceBranchPricing::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(\Modules\Packages\Models\PackageItem::class);
    }

    /**
     * Get qualified staff profiles for this service.
     */
    public function qualifiedStaff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'service_qualified_staff', 'service_id', 'staff_profile_id')
            ->withTimestamps();
    }

    /**
     * Get qualified staff users through staff profiles.
     */
    public function qualifiedStaffUsers(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'service_qualified_staff', 'service_id', 'staff_profile_id')
            ->with('user')
            ->withTimestamps();
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'service_rooms')
            ->withPivot(['is_primary', 'priority'])
            ->withTimestamps();
    }

    public function getPrimaryRoomAttribute(): ?Room
    {
        return $this->rooms()->wherePivot('is_primary', true)->first();
    }

    public function backupRooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'service_rooms')
            ->wherePivot('is_primary', false)
            ->orderByPivot('priority')
            ->withTimestamps();
    }

    public function requiredEquipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_required_equipment')
            ->withPivot(['is_mandatory'])
            ->withTimestamps();
    }

    public function isTimeSlotAllowed(Carbon $dateTime): bool
    {
        $restrictions = $this->time_slot_restrictions;
        if (empty($restrictions)) {
            return true;
        }

        // Check day of week
        if (isset($restrictions['allowed_days']) &&
            ! in_array($dateTime->dayOfWeek, $restrictions['allowed_days'])) {
            return false;
        }

        // Check time range
        $time = $dateTime->format('H:i');
        if (isset($restrictions['allowed_time_start']) && $time < $restrictions['allowed_time_start']) {
            return false;
        }
        if (isset($restrictions['allowed_time_end']) && $time > $restrictions['allowed_time_end']) {
            return false;
        }

        // Check blackout dates
        if (isset($restrictions['blackout_dates']) &&
            in_array($dateTime->format('Y-m-d'), $restrictions['blackout_dates'])) {
            return false;
        }

        // Check min advance hours
        if (isset($restrictions['min_advance_hours'])) {
            $hoursUntilSlot = now()->diffInHours($dateTime, false);
            if ($hoursUntilSlot < $restrictions['min_advance_hours']) {
                return false;
            }
        }

        // Check max advance days
        if (isset($restrictions['max_advance_days'])) {
            $daysUntilSlot = now()->diffInDays($dateTime, false);
            if ($daysUntilSlot > $restrictions['max_advance_days']) {
                return false;
            }
        }

        return true;
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
        if (! $this->fitzpatrick_min && ! $this->fitzpatrick_max) {
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

    public function scopeConsultations($query)
    {
        return $query->where('is_consultation', true);
    }

    public function scopeTreatments($query)
    {
        return $query->where('is_consultation', false);
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

    /**
     * Check if this service has dynamic parameters configured.
     */
    public function hasParameters(): bool
    {
        return $this->has_dynamic_parameters || $this->parameter_mode !== 'none';
    }

    /**
     * Get all parameter definitions for this service.
     * Returns parameters from template or custom service parameters.
     */
    public function getParameterDefinitions(): array
    {
        if ($this->parameter_mode === 'template' && $this->parameterTemplate) {
            return $this->parameterTemplate->getParameterDefinitions();
        }

        if ($this->parameter_mode === 'custom') {
            return $this->activeParameters->map(fn ($p) => $p->toFormField())->toArray();
        }

        return [];
    }

    /**
     * Validate parameter values for this service.
     */
    public function validateParameterValues(array $values): array
    {
        if ($this->parameter_mode === 'template' && $this->parameterTemplate) {
            return $this->parameterTemplate->validateValues($values);
        }

        if ($this->parameter_mode === 'custom') {
            $errors = [];
            foreach ($this->activeParameters as $param) {
                $key = $param->parameter_key;
                $error = $param->validateValue($values[$key] ?? null);
                if ($error) {
                    $errors[$key] = $error;
                }
            }

            return $errors;
        }

        return [];
    }

    /**
     * Get default parameter values for this service.
     */
    public function getDefaultParameterValues(): array
    {
        // Check for default preset first
        $defaultPreset = $this->defaultPreset;
        if ($defaultPreset) {
            return $defaultPreset->getValues();
        }

        // Fall back to template defaults
        if ($this->parameter_mode === 'template' && $this->parameterTemplate) {
            return $this->parameterTemplate->getDefaultValues();
        }

        // Fall back to parameter-level defaults
        if ($this->parameter_mode === 'custom') {
            $defaults = [];
            foreach ($this->activeParameters as $param) {
                $default = $param->getDefaultValue();
                if ($default !== null) {
                    $defaults[$param->parameter_key] = $default;
                }
            }

            return $defaults;
        }

        return [];
    }

    /**
     * Scope to services with dynamic parameters.
     */
    public function scopeWithParameters($query)
    {
        return $query->where(function ($q) {
            $q->where('has_dynamic_parameters', true)
                ->orWhere('parameter_mode', '!=', 'none');
        });
    }

    /**
     * Get effective equipment (service's own or fallback to category).
     * Override pattern: If service has its own equipment, use it. Otherwise, use category's.
     */
    public function getEffectiveEquipment(): Collection
    {
        // Skip query if relationship already eager loaded
        if ($this->relationLoaded('requiredEquipment') && $this->requiredEquipment->isNotEmpty()) {
            return $this->requiredEquipment;
        }

        // If service has its own equipment, use it
        if ($this->requiredEquipment()->exists()) {
            return $this->requiredEquipment;
        }

        // Fall back to category's equipment (check if loaded first)
        if ($this->category) {
            if ($this->category->relationLoaded('requiredEquipment')) {
                return $this->category->requiredEquipment;
            }

            return $this->category->requiredEquipment;
        }

        return collect();
    }

    /**
     * Get effective qualified staff (service's own or fallback to category).
     * Override pattern: If service has its own staff, use it. Otherwise, use category's.
     */
    public function getEffectiveQualifiedStaff(): Collection
    {
        // Skip query if relationship already eager loaded
        if ($this->relationLoaded('qualifiedStaff') && $this->qualifiedStaff->isNotEmpty()) {
            return $this->qualifiedStaff;
        }

        // If service has its own qualified staff, use it
        if ($this->qualifiedStaff()->exists()) {
            return $this->qualifiedStaff;
        }

        // Fall back to category's qualified staff (check if loaded first)
        if ($this->category) {
            if ($this->category->relationLoaded('qualifiedStaff')) {
                return $this->category->qualifiedStaff;
            }

            return $this->category->qualifiedStaff;
        }

        return collect();
    }

    /**
     * Get effective rooms (service's own or fallback to category).
     * Override pattern: If service has its own rooms, use it. Otherwise, use category's.
     */
    public function getEffectiveRooms(): Collection
    {
        // Skip query if relationship already eager loaded
        if ($this->relationLoaded('rooms') && $this->rooms->isNotEmpty()) {
            return $this->rooms;
        }

        // If service has its own rooms, use it
        if ($this->rooms()->exists()) {
            return $this->rooms;
        }

        // Fall back to category's rooms (check if loaded first)
        if ($this->category) {
            if ($this->category->relationLoaded('rooms')) {
                return $this->category->rooms;
            }

            return $this->category->rooms;
        }

        return collect();
    }

    /**
     * Get effective consumables (service's own or fallback to category).
     * Override pattern: If service has its own consumables, use it. Otherwise, use category's.
     */
    public function getEffectiveConsumables(): Collection
    {
        // If service has consumables_required field set, use it
        if (! empty($this->consumables_required)) {
            // consumables_required is a JSON array of product IDs with quantities
            return collect($this->consumables_required)->map(function ($item) {
                return [
                    'product' => Product::find($item['product_id'] ?? null),
                    'quantity' => $item['quantity'] ?? 1,
                ];
            })->filter(fn ($item) => $item['product'] !== null);
        }

        // Fall back to category's consumables
        if ($this->category) {
            return $this->category->consumables->map(function ($product) {
                return [
                    'product' => $product,
                    'quantity' => $product->pivot->quantity ?? 1,
                ];
            });
        }

        return collect();
    }
}
