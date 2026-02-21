<?php

namespace App\Models;

use App\Traits\HasPostgresBoolean;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\Tenant;

class AddOn extends Model
{
    use HasFactory, HasUuids, SoftDeletes, HasPostgresBoolean;

    protected $connection = 'central';

    protected $table = 'public.add_ons';

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'icon',
        'monthly_price',
        'yearly_price',
        'is_active',
        'is_recurring',
        'billing_interval',
        'features',
        'limits',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_recurring' => 'boolean',
        'features' => 'array',
        'limits' => 'array',
        'sort_order' => 'integer',
    ];

    protected function getPostgresBooleanFields(): array
    {
        return ['is_active', 'is_recurring'];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_add_ons')
            ->withPivot(['activated_at', 'expires_at', 'billing_interval', 'price'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->tenants()
            ->whereNull('tenant_add_ons.expires_at')
            ->orWhere('tenant_add_ons.expires_at', '>', now())
            ->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->tenants()
            ->whereNotNull('tenant_add_ons.activated_at')
            ->sum('tenant_add_ons.price');
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'ar' && $this->name_ar
            ? $this->name_ar
            : $this->name;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'ar' && $this->description_ar
            ? $this->description_ar
            : $this->description;
    }
}
