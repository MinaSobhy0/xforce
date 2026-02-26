<?php

namespace Modules\Booking\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;
use XLinic\Framework\Core\Model\BaseModel;

class TimeOffType extends BaseModel
{
    use HasTranslations;

    protected $table = 'time_off_types';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'description',
        'color',
        'is_paid',
        'requires_approval',
        'default_days_per_year',
        'max_days_per_request',
        'min_days_notice',
        'allow_half_day',
        'allow_partial_day',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'requires_approval' => 'boolean',
        'default_days_per_year' => 'integer',
        'max_days_per_request' => 'integer',
        'min_days_notice' => 'integer',
        'allow_half_day' => 'boolean',
        'allow_partial_day' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'color' => 'gray',
        'is_paid' => true,
        'requires_approval' => true,
        'default_days_per_year' => 0,
        'min_days_notice' => 0,
        'allow_half_day' => true,
        'allow_partial_day' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    public const COLORS = [
        'gray' => 'Gray',
        'primary' => 'Primary',
        'success' => 'Green',
        'info' => 'Blue',
        'warning' => 'Yellow',
        'danger' => 'Red',
    ];

    /**
     * Get allocations for this type.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(TimeOffAllocation::class);
    }

    /**
     * Get time off requests for this type.
     */
    public function timeOffRequests(): HasMany
    {
        return $this->hasMany(PractitionerTimeOff::class);
    }

    /**
     * Scope to active types only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to ordered types.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
