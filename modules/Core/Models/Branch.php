<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'city',
        'phone',
        'email',
        'working_hours',
        'is_active',
        'is_main',
        'sort_order',
    ];

    protected $casts = [
        'working_hours' => 'array',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }
}
