<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Equipment\Models\EquipmentType;
use Modules\Equipment\Models\Equipment;
use Modules\Core\Models\Room;
use Modules\Auth\Models\User;
use Carbon\Carbon;

class Service extends BaseModel
{
    use HasTenancy, HasTranslation, HasActivity, HasSequence;

    protected $table = 'services';

    protected string $sequenceCode = 'service';
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
        'recommended_sessions' => 'integer',
        'session_interval_days' => 'integer',
        'fitzpatrick_min' => 'integer',
        'fitzpatrick_max' => 'integer',
        'is_active' => 'boolean',
        'requires_consent' => 'boolean',
        'is_bookable_online' => 'boolean',
        'time_slot_restrictions' => 'array',
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
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function consentTemplate(): BelongsTo
    {
        return $this->belongsTo(ConsentTemplate::class);
    }

    public function branchPricing(): HasMany
    {
        return $this->hasMany(ServiceBranchPricing::class);
    }

    public function requiredEquipmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipmentType::class,
            'service_equipment_requirements',
            'service_id',
            'equipment_type_id'
        )->withPivot('is_required')->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(\Modules\Booking\Models\Appointment::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(\Modules\Packages\Models\PackageItem::class);
    }

    public function qualifiedStaff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_qualified_staff')
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
            !in_array($dateTime->dayOfWeek, $restrictions['allowed_days'])) {
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
