<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMaintenanceLog extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id',
        'equipment_id',
        'type',
        'description',
        'performed_by',
        'cost_minor',
        'parts_replaced',
        'next_due_date',
        'performed_at',
    ];

    protected $casts = [
        'parts_replaced' => 'array',
        'cost_minor' => 'integer',
        'next_due_date' => 'date',
        'performed_at' => 'datetime',
    ];

    public const TYPE_PREVENTIVE = 'preventive';
    public const TYPE_CORRECTIVE = 'corrective';
    public const TYPE_CALIBRATION = 'calibration';

    public const TYPES = [
        self::TYPE_PREVENTIVE => 'Preventive',
        self::TYPE_CORRECTIVE => 'Corrective',
        self::TYPE_CALIBRATION => 'Calibration',
    ];

    protected static function booted(): void
    {
        static::created(function (EquipmentMaintenanceLog $log) {
            // Update equipment maintenance dates
            $equipment = $log->equipment;
            if ($equipment) {
                $equipment->last_maintenance_at = $log->performed_at;
                if ($log->next_due_date) {
                    $equipment->next_maintenance_at = $log->next_due_date;
                }
                $equipment->save();
            }
        });
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function getCostAttribute(): float
    {
        return $this->cost_minor / 100;
    }
}
