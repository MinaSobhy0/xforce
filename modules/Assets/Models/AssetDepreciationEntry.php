<?php

namespace Modules\Assets\Models;

use XLinic\Framework\Core\Model\BaseModel;
use XLinic\Framework\Core\Model\Traits\HasTenancy;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Modules\Accounting\Models\JournalEntry;
use Modules\Auth\Models\User;

class AssetDepreciationEntry extends BaseModel
{
    use HasTenancy;

    protected $table = 'asset_depreciation_entries';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'period_start',
        'period_end',
        'period_label',
        'depreciation_amount_minor',
        'accumulated_depreciation_minor',
        'book_value_minor',
        'journal_entry_id',
        'status',
        'created_by_user_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'depreciation_amount_minor' => 'integer',
        'accumulated_depreciation_minor' => 'integer',
        'book_value_minor' => 'integer',
    ];

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_POSTED = 'posted';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_POSTED => 'Posted',
        self::STATUS_REVERSED => 'Reversed',
    ];

    public const STATUS_COLORS = [
        self::STATUS_DRAFT => 'gray',
        self::STATUS_POSTED => 'success',
        self::STATUS_REVERSED => 'danger',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (AssetDepreciationEntry $entry) {
            if (empty($entry->created_by_user_id)) {
                $entry->created_by_user_id = auth()->id();
            }
            if (empty($entry->status)) {
                $entry->status = self::STATUS_DRAFT;
            }
        });
    }

    // Relationships
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Scopes
    public function scopeForPeriod(Builder $query, string $periodLabel): Builder
    {
        return $query->where('period_label', $periodLabel);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusColorAttribute(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'gray';
    }

    // State checks
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function canPost(): bool
    {
        return $this->isDraft() && !$this->journal_entry_id;
    }

    public function canReverse(): bool
    {
        return $this->isPosted() && $this->journal_entry_id;
    }
}
