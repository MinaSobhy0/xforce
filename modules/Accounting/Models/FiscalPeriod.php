<?php

namespace Modules\Accounting\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;

class FiscalPeriod extends BaseModel
{
    use HasTenancy;

    protected $table = 'fiscal_periods';

    protected $fillable = [
        'tenant_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_by_user_id',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_LOCKED = 'locked';

    public const STATUSES = [
        self::STATUS_OPEN => 'Open',
        self::STATUS_CLOSED => 'Closed',
        self::STATUS_LOCKED => 'Locked',
    ];

    public const STATUS_COLORS = [
        self::STATUS_OPEN => 'success',
        self::STATUS_CLOSED => 'warning',
        self::STATUS_LOCKED => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (FiscalPeriod $period) {
            if (empty($period->status)) {
                $period->status = self::STATUS_OPEN;
            }
        });
    }

    // Relationships
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    // State checks
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    // Check if a date falls within this period
    public function containsDate($date): bool
    {
        $date = \Carbon\Carbon::parse($date);
        return $date->between($this->start_date, $this->end_date);
    }

    // Close the period
    public function close(): bool
    {
        if (!$this->isOpen()) {
            return false;
        }

        // Check for unposted entries
        $hasUnposted = $this->journalEntries()
            ->where('status', JournalEntry::STATUS_DRAFT)
            ->exists();

        if ($hasUnposted) {
            return false;
        }

        $this->status = self::STATUS_CLOSED;
        $this->closed_at = now();
        $this->closed_by_user_id = auth()->id();

        return $this->save();
    }

    // Reopen the period
    public function reopen(): bool
    {
        if (!$this->isClosed()) {
            return false;
        }

        $this->status = self::STATUS_OPEN;
        $this->closed_at = null;
        $this->closed_by_user_id = null;

        return $this->save();
    }

    // Lock the period (permanent)
    public function lock(): bool
    {
        if (!$this->isClosed()) {
            return false;
        }

        $this->status = self::STATUS_LOCKED;
        return $this->save();
    }

    // Get status label
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', self::STATUS_CLOSED);
    }

    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', today())
            ->where('end_date', '>=', today());
    }

    // Get the current period
    public static function getCurrent(): ?self
    {
        return static::current()->first();
    }

    // Get the period for a specific date
    public static function getForDate($date): ?self
    {
        return static::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }
}
