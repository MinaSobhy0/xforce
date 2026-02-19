<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromoCode extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'discount_type',
        'discount_value',
        'discount_duration_months',
        'applicable_plan_ids',
        'min_plan_tier',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'discount_duration_months' => 'integer',
        'applicable_plan_ids' => 'array',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public const DISCOUNT_TYPES = [
        'percentage' => 'Percentage',
        'fixed' => 'Fixed Amount',
    ];

    public function scopeActive($query)
    {
        return $query->whereRaw('is_active = true');
    }

    public function scopeValid($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')
                    ->orWhereColumn('used_count', '<', 'max_uses');
            });
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return 'expired';
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return 'exhausted';
        }

        return 'active';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'expired' => 'gray',
            'exhausted' => 'warning',
            'inactive' => 'gray',
            default => 'gray',
        };
    }

    public function getFormattedDiscountAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            return $this->discount_value . '%';
        }
        return number_format($this->discount_value, 0) . ' EGP';
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->valid_from && $this->valid_from->isFuture()) {
            return false;
        }

        if ($this->valid_until && $this->valid_until->isPast()) {
            return false;
        }

        if ($this->max_uses && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function use(): void
    {
        $this->increment('used_count');
    }
}
