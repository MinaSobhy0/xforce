<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Equipment\Models\Equipment;
use Modules\Core\Models\Room;
use Modules\Staff\Models\StaffProfile;
use Modules\Inventory\Models\Product;
use Modules\Accounting\Models\ChartOfAccount;

class ServiceCategory extends BaseModel
{
    use HasTenancy, HasTranslation;

    protected $table = 'service_categories';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'default_parameter_template_id',
        'unearned_revenue_account_id',
        'service_revenue_account_id',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    protected $appends = ['translated_name'];

    public function getTranslatedNameAttribute(): string
    {
        return $this->getTranslation('name', app()->getLocale())
            ?? $this->getTranslation('name', 'en')
            ?? '';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ServiceCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function allServices(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }

    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;
        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }
        return $depth;
    }

    public function getFullPathAttribute(): string
    {
        $path = [$this->translated_name];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->translated_name);
            $parent = $parent->parent;
        }
        return implode(' > ', $path);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    public function hasServices(): bool
    {
        return $this->services()->exists();
    }

    public function canDelete(): bool
    {
        return !$this->hasChildren() && !$this->hasServices();
    }

    /**
     * Get the default parameter template for this category.
     */
    public function defaultParameterTemplate(): BelongsTo
    {
        return $this->belongsTo(ParameterTemplate::class, 'default_parameter_template_id');
    }

    /**
     * Get required equipment for this category.
     */
    public function requiredEquipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'service_category_equipment', 'service_category_id', 'equipment_id')
            ->withPivot(['is_mandatory'])
            ->withTimestamps();
    }

    /**
     * Get qualified staff for this category.
     */
    public function qualifiedStaff(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class, 'service_category_qualified_staff', 'service_category_id', 'staff_profile_id')
            ->withTimestamps();
    }

    /**
     * Get rooms for this category.
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'service_category_rooms', 'service_category_id', 'room_id')
            ->withPivot(['is_primary', 'priority'])
            ->withTimestamps();
    }

    /**
     * Get consumables for this category.
     */
    public function consumables(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_category_consumables', 'service_category_id', 'product_id')
            ->withPivot(['quantity'])
            ->withTimestamps();
    }

    /**
     * Get the unearned revenue account for this category.
     */
    public function unearnedRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'unearned_revenue_account_id');
    }

    /**
     * Get the service revenue account for this category.
     */
    public function serviceRevenueAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'service_revenue_account_id');
    }
}
