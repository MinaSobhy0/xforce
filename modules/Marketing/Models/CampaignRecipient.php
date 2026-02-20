<?php

namespace Modules\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Patients\Models\Patient;

class CampaignRecipient extends BaseModel
{
    protected $table = 'campaign_recipients';

    // Recipient statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'patient_id',
        'phone',
        'email',
        'status',
        'variables_json',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
        'provider_message_id',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * Get all available statuses.
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => __('marketing::marketing.recipient_statuses.pending'),
            self::STATUS_SENT => __('marketing::marketing.recipient_statuses.sent'),
            self::STATUS_DELIVERED => __('marketing::marketing.recipient_statuses.delivered'),
            self::STATUS_READ => __('marketing::marketing.recipient_statuses.read'),
            self::STATUS_FAILED => __('marketing::marketing.recipient_statuses.failed'),
            self::STATUS_BOUNCED => __('marketing::marketing.recipient_statuses.bounced'),
            self::STATUS_UNSUBSCRIBED => __('marketing::marketing.recipient_statuses.unsubscribed'),
        ];
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'gray',
            self::STATUS_SENT => 'info',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_READ => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_BOUNCED => 'danger',
            self::STATUS_UNSUBSCRIBED => 'warning',
            default => 'gray',
        };
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(?string $providerMessageId = null): bool
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        if ($providerMessageId) {
            $this->provider_message_id = $providerMessageId;
        }
        return $this->save();
    }

    /**
     * Mark as delivered.
     */
    public function markAsDelivered(): bool
    {
        $this->status = self::STATUS_DELIVERED;
        $this->delivered_at = now();
        return $this->save();
    }

    /**
     * Mark as read.
     */
    public function markAsRead(): bool
    {
        $this->status = self::STATUS_READ;
        $this->read_at = now();
        return $this->save();
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $errorMessage): bool
    {
        $this->status = self::STATUS_FAILED;
        $this->failed_at = now();
        $this->error_message = $errorMessage;
        return $this->save();
    }

    /**
     * Get the campaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Scope to pending recipients.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to sent recipients.
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to failed recipients.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
}
