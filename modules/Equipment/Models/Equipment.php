<?php

namespace Modules\Equipment\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use XLinic\Framework\Core\Model\Traits\HasActivity;
use XLinic\Framework\Core\Model\Traits\HasSequence;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;

class Equipment extends BaseModel
{
    use HasTenancy, HasActivity, HasSequence;

    protected $table = 'equipment';

    protected string $sequenceCode = 'equipment';
    protected string $sequenceColumn = 'code';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'equipment_type_id',
        'branch_id',
        'room_id',
        'serial_number',
        'purchase_date',
        'purchase_price_minor',
        'warranty_expiry',
        'total_shots_fired',
        'status',
        'last_maintenance_at',
        'next_maintenance_at',
        'depreciation_years',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_price_minor' => 'integer',
        'total_shots_fired' => 'integer',
        'last_maintenance_at' => 'datetime',
        'next_maintenance_at' => 'datetime',
        'depreciation_years' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';
    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_MAINTENANCE => 'Under Maintenance',
        self::STATUS_OUT_OF_SERVICE => 'Out of Service',
        self::STATUS_RETIRED => 'Retired',
    ];

    public const STATUS_COLORS = [
        self::STATUS_ACTIVE => 'success',
        self::STATUS_MAINTENANCE => 'warning',
        self::STATUS_OUT_OF_SERVICE => 'danger',
        self::STATUS_RETIRED => 'gray',
    ];

    protected static function booted(): void
    {
        static::creating(function (Equipment $equipment) {
            if (empty($equipment->status)) {
                $equipment->status = self::STATUS_ACTIVE;
            }
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EquipmentType::class, 'equipment_type_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function maintenanceLogs(): HasMany
    {
        return $this->hasMany(EquipmentMaintenanceLog::class);
    }

    public function shotLogs(): HasMany
    {
        return $this->hasMany(EquipmentShotLog::class);
    }

    public function getShotsRemainingAttribute(): ?int
    {
        if (!$this->type || !$this->type->max_shots) {
            return null;
        }
        return max(0, $this->type->max_shots - $this->total_shots_fired);
    }

    public function getShotsPercentageAttribute(): ?float
    {
        if (!$this->type || !$this->type->max_shots) {
            return null;
        }
        return round(($this->total_shots_fired / $this->type->max_shots) * 100, 1);
    }

    public function getDepreciatedValueAttribute(): int
    {
        if (!$this->purchase_price_minor || !$this->depreciation_years || !$this->purchase_date) {
            return $this->purchase_price_minor ?? 0;
        }

        $yearsOwned = $this->purchase_date->diffInYears(now());
        $depreciationRate = $this->purchase_price_minor / $this->depreciation_years;
        $depreciation = $depreciationRate * min($yearsOwned, $this->depreciation_years);

        return max(0, (int) ($this->purchase_price_minor - $depreciation));
    }

    public function getIsMaintenanceDueAttribute(): bool
    {
        if (!$this->next_maintenance_at) {
            return false;
        }
        return $this->next_maintenance_at->isPast() || $this->next_maintenance_at->isToday();
    }

    public function getIsWarrantyActiveAttribute(): bool
    {
        if (!$this->warranty_expiry) {
            return false;
        }
        return $this->warranty_expiry->isFuture();
    }

    public function canTransitionTo(string $status): bool
    {
        $transitions = [
            self::STATUS_ACTIVE => [self::STATUS_MAINTENANCE, self::STATUS_OUT_OF_SERVICE, self::STATUS_RETIRED],
            self::STATUS_MAINTENANCE => [self::STATUS_ACTIVE, self::STATUS_RETIRED],
            self::STATUS_OUT_OF_SERVICE => [self::STATUS_ACTIVE, self::STATUS_MAINTENANCE, self::STATUS_RETIRED],
            self::STATUS_RETIRED => [],
        ];

        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status): bool
    {
        if (!$this->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    public function recordShots(int $shots, ?string $appointmentId = null, array $settings = []): EquipmentShotLog
    {
        $log = $this->shotLogs()->create([
            'appointment_id' => $appointmentId,
            'shots_count' => $shots,
            'energy_setting' => $settings['energy'] ?? null,
            'spot_size' => $settings['spot_size'] ?? null,
            'pulse_duration' => $settings['pulse_duration'] ?? null,
            'notes' => $settings['notes'] ?? null,
            'logged_at' => now(),
        ]);

        $this->increment('total_shots_fired', $shots);

        return $log;
    }
}
