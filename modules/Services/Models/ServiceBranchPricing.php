<?php

namespace Modules\Services\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBranchPricing extends BaseModel
{
    protected $table = 'service_branch_pricing';

    protected $fillable = [
        'service_id',
        'branch_id',
        'price_minor',
        'is_active',
    ];

    protected $casts = [
        'price_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $appends = ['formatted_price'];

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_minor / 100, 2) . ' ' . current_currency();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Branch::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForBranch($query, ?string $branchId)
    {
        if ($branchId === null) {
            return $query;
        }
        return $query->where('branch_id', $branchId);
    }
}
