<?php

namespace Modules\Core\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class Room extends BaseModel
{
    use HasTenancy;
    use HasTranslations;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'code',
        'description',
        'capacity',
        'floor',
        'room_type',
        'color',
        'is_active',
        'is_bookable',
        'settings',
        'sort_order',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'boolean',
        'is_bookable' => 'boolean',
        'settings' => 'array',
        'sort_order' => 'integer',
    ];

    public array $translatable = ['name', 'description'];

    /**
     * Room types available in the system.
     */
    public const TYPES = [
        'treatment' => 'Treatment Room',
        'consultation' => 'Consultation Room',
        'waiting' => 'Waiting Area',
        'reception' => 'Reception',
        'storage' => 'Storage',
        'staff' => 'Staff Room',
        'other' => 'Other',
    ];

    /**
     * Get the branch this room belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the tenant this room belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to active rooms only.
     */
    public function scopeActive($query)
    {
        return $query->where('rooms.is_active', true);
    }

    /**
     * Scope to bookable rooms only.
     */
    public function scopeBookable($query)
    {
        return $query->where('rooms.is_bookable', true);
    }

    /**
     * Scope to rooms of a specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('rooms.room_type', $type);
    }

    /**
     * Scope to rooms in a specific branch.
     */
    public function scopeInBranch($query, string $branchId)
    {
        return $query->where('rooms.branch_id', $branchId);
    }

    /**
     * Get the display name for this room.
     */
    public function getDisplayName(): string
    {
        $branchName = $this->branch?->name ?? '';
        return $branchName ? "{$this->name} ({$branchName})" : $this->name;
    }

    /**
     * Get the room type label.
     */
    public function getRoomTypeLabel(): string
    {
        return self::TYPES[$this->room_type] ?? ucfirst($this->room_type);
    }

    /**
     * Check if the room can be booked for appointments.
     */
    public function canBook(): bool
    {
        return $this->is_active && $this->is_bookable && $this->branch?->is_active;
    }
}
