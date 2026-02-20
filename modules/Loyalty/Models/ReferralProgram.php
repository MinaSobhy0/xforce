<?php

namespace Modules\Loyalty\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralProgram extends BaseModel
{
    use HasTenancy, SoftDeletes;

    protected $table = 'referral_programs';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'referrer_points',
        'referred_points',
        'referrer_discount_percentage',
        'referred_discount_percentage',
        'min_purchase_minor',
        'max_referrals_per_patient',
        'require_first_purchase',
        'conditions',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'referrer_points' => 'integer',
        'referred_points' => 'integer',
        'referrer_discount_percentage' => 'decimal:2',
        'referred_discount_percentage' => 'decimal:2',
        'min_purchase_minor' => 'integer',
        'max_referrals_per_patient' => 'integer',
        'require_first_purchase' => 'boolean',
        'conditions' => 'array',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected $attributes = [
        'is_active' => true,
        'require_first_purchase' => true,
        'referrer_points' => 100,
        'referred_points' => 50,
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public static function getActiveProgram()
    {
        return static::active()->orderBy('created_at', 'desc')->first();
    }
}
