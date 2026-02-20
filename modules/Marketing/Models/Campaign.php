<?php

namespace Modules\Marketing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\User;
use Spatie\Translatable\HasTranslations;

class Campaign extends BaseModel
{
    use HasTranslations;

    protected $table = 'campaigns';

    // Campaign statuses
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // State transitions
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_SCHEDULED, self::STATUS_CANCELLED],
        self::STATUS_SCHEDULED => [self::STATUS_SENDING, self::STATUS_PAUSED, self::STATUS_CANCELLED],
        self::STATUS_SENDING => [self::STATUS_PAUSED, self::STATUS_COMPLETED],
        self::STATUS_PAUSED => [self::STATUS_SENDING, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
    ];

    public array $translatable = ['name', 'description'];

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'template_id',
        'channel',
        'audience_filters_json',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'created_by_user_id',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'audience_filters_json' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'read_count' => 'integer',
        'failed_count' => 'integer',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'total_recipients' => 0,
        'sent_count' => 0,
        'delivered_count' => 0,
        'read_count' => 0,
        'failed_count' => 0,
    ];

    /**
     * Get all available statuses.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => __('marketing::marketing.statuses.draft'),
            self::STATUS_SCHEDULED => __('marketing::marketing.statuses.scheduled'),
            self::STATUS_SENDING => __('marketing::marketing.statuses.sending'),
            self::STATUS_PAUSED => __('marketing::marketing.statuses.paused'),
            self::STATUS_COMPLETED => __('marketing::marketing.statuses.completed'),
            self::STATUS_CANCELLED => __('marketing::marketing.statuses.cancelled'),
        ];
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_SCHEDULED => 'info',
            self::STATUS_SENDING => 'warning',
            self::STATUS_PAUSED => 'warning',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'danger',
            default => 'gray',
        };
    }

    /**
     * Check if transition to new status is allowed.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowed = self::TRANSITIONS[$this->status] ?? [];
        return in_array($newStatus, $allowed);
    }

    /**
     * Schedule the campaign.
     */
    public function schedule(\DateTimeInterface $scheduledAt): bool
    {
        if (!$this->canTransitionTo(self::STATUS_SCHEDULED)) {
            return false;
        }

        $this->status = self::STATUS_SCHEDULED;
        $this->scheduled_at = $scheduledAt;
        return $this->save();
    }

    /**
     * Start sending the campaign.
     */
    public function startSending(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_SENDING)) {
            return false;
        }

        $this->status = self::STATUS_SENDING;
        $this->started_at = now();
        return $this->save();
    }

    /**
     * Pause the campaign.
     */
    public function pause(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_PAUSED)) {
            return false;
        }

        $this->status = self::STATUS_PAUSED;
        return $this->save();
    }

    /**
     * Resume the campaign.
     */
    public function resume(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_SENDING)) {
            return false;
        }

        $this->status = self::STATUS_SENDING;
        return $this->save();
    }

    /**
     * Mark campaign as completed.
     */
    public function complete(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_COMPLETED)) {
            return false;
        }

        $this->status = self::STATUS_COMPLETED;
        $this->completed_at = now();
        return $this->save();
    }

    /**
     * Cancel the campaign.
     */
    public function cancel(): bool
    {
        if (!$this->canTransitionTo(self::STATUS_CANCELLED)) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = now();
        return $this->save();
    }

    /**
     * Get the template for this campaign.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * Get recipients for this campaign.
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    /**
     * Get the user who created this campaign.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Calculate delivery rate.
     */
    public function getDeliveryRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->delivered_count / $this->sent_count) * 100, 2);
    }

    /**
     * Calculate read rate (for WhatsApp).
     */
    public function getReadRateAttribute(): float
    {
        if ($this->delivered_count === 0) {
            return 0;
        }
        return round(($this->read_count / $this->delivered_count) * 100, 2);
    }

    /**
     * Calculate failure rate.
     */
    public function getFailureRateAttribute(): float
    {
        if ($this->sent_count === 0) {
            return 0;
        }
        return round(($this->failed_count / $this->sent_count) * 100, 2);
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_recipients === 0) {
            return 0;
        }
        return (int) round((($this->sent_count + $this->failed_count) / $this->total_recipients) * 100);
    }

    /**
     * Check if campaign is editable.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED]);
    }

    /**
     * Scope to draft campaigns.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope to active campaigns (scheduled or sending).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_SENDING]);
    }
}
