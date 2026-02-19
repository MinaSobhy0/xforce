<?php

namespace Modules\Treatments\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasTranslation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TreatmentCategory extends BaseModel
{
    use HasTenancy, HasTranslation;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
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
        return $this->belongsTo(TreatmentCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TreatmentCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(Treatment::class, 'category_id');
    }

    public function allTreatments(): HasMany
    {
        return $this->hasMany(Treatment::class, 'category_id');
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

    public function hasTreatments(): bool
    {
        return $this->treatments()->exists();
    }

    public function canDelete(): bool
    {
        return !$this->hasChildren() && !$this->hasTreatments();
    }
}
