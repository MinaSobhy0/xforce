<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentShotLog extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'appointment_id',
        'shots_count',
        'energy_setting',
        'spot_size',
        'pulse_duration',
        'notes',
        'cumulative_data',
        'all_parameters',
        'logged_at',
    ];

    protected $casts = [
        'shots_count' => 'integer',
        'cumulative_data' => 'array',
        'all_parameters' => 'array',
        'logged_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
