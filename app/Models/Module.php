<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Module extends Model
{
    use HasUuids, SoftDeletes, HasTranslations, HasPostgresBoolean;

    protected $connection = 'central';

    protected $table = 'public.modules';

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'icon_emoji',
        'tier',
        'addon_price_monthly_minor',
        'is_core',
        'is_active',
        'is_beta',
        'sort_order',
        'dependencies',
        'settings_schema',
    ];

    protected $casts = [
        'addon_price_monthly_minor' => 'integer',
        'is_core' => 'boolean',
        'is_active' => 'boolean',
        'is_beta' => 'boolean',
        'sort_order' => 'integer',
        'dependencies' => 'array',
        'settings_schema' => 'array',
    ];

    protected function getPostgresBooleanFields(): array
    {
        return ['is_core', 'is_active', 'is_beta'];
    }

    public const CATEGORIES = [
        'core' => 'Core',
        'operations' => 'Operations',
        'financial' => 'Financial',
        'sales' => 'Sales',
        'marketing' => 'Marketing',
        'advanced' => 'Advanced',
    ];

    public const TIERS = [
        'free' => 'Free (All Plans)',
        'starter' => 'Starter+',
        'professional' => 'Professional+',
        'enterprise' => 'Enterprise Only',
        'addon' => 'Add-on',
    ];

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeCore($query)
    {
        return $query->whereRaw('is_core = true');
    }

    public function scopeAddons($query)
    {
        return $query->where('tier', 'addon');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function getAdoptionCountAttribute(): int
    {
        return \DB::table('tenant_modules')
            ->where('module_code', $this->code)
            ->whereRaw('is_active = true')
            ->count();
    }

    public function getTotalTenantsAttribute(): int
    {
        return \Modules\Core\Models\Tenant::whereIn('status', ['active', 'trial'])->count();
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst($this->category);
    }

    public function getTierLabelAttribute(): string
    {
        return self::TIERS[$this->tier] ?? ucfirst($this->tier);
    }
}
