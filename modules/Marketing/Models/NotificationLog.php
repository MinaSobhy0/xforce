<?php

namespace Modules\Marketing\Models;

use XLinic\Framework\Core\Model\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Patients\Models\Patient;

class NotificationLog extends BaseModel
{
    protected $table = 'notification_logs';

    // Channel types
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';

    // Notification types
    public const TYPE_APPOINTMENT_CONFIRMATION = 'appointment_confirmation';
    public const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    public const TYPE_APPOINTMENT_FOLLOWUP = 'appointment_followup';
    public const TYPE_INVOICE_RECEIPT = 'invoice_receipt';
    public const TYPE_PAYMENT_REMINDER = 'payment_reminder';
    public const TYPE_CAMPAIGN = 'campaign';
    public const TYPE_MANUAL = 'manual';

    // Statuses
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'channel',
        'type',
        'template_id',
        'campaign_id',
        'recipient_address',
        'subject',
        'content',
        'variables_json',
        'status',
        'provider_message_id',
        'provider_response_json',
        'error_message',
        'cost_minor',
        'reference_type',
        'reference_id',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'provider_response_json' => 'array',
        'cost_minor' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_QUEUED,
        'cost_minor' => 0,
    ];

    /**
     * Get all available channels.
     */
    public static function channels(): array
    {
        return [
            self::CHANNEL_WHATSAPP => __('marketing::marketing.channels.whatsapp'),
            self::CHANNEL_SMS => __('marketing::marketing.channels.sms'),
            self::CHANNEL_EMAIL => __('marketing::marketing.channels.email'),
        ];
    }

    /**
     * Get all notification types.
     */
    public static function types(): array
    {
        return [
            self::TYPE_APPOINTMENT_CONFIRMATION => __('marketing::marketing.notification_types.appointment_confirmation'),
            self::TYPE_APPOINTMENT_REMINDER => __('marketing::marketing.notification_types.appointment_reminder'),
            self::TYPE_APPOINTMENT_FOLLOWUP => __('marketing::marketing.notification_types.appointment_followup'),
            self::TYPE_INVOICE_RECEIPT => __('marketing::marketing.notification_types.invoice_receipt'),
            self::TYPE_PAYMENT_REMINDER => __('marketing::marketing.notification_types.payment_reminder'),
            self::TYPE_CAMPAIGN => __('marketing::marketing.notification_types.campaign'),
            self::TYPE_MANUAL => __('marketing::marketing.notification_types.manual'),
        ];
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_QUEUED => 'gray',
            self::STATUS_SENDING => 'info',
            self::STATUS_SENT => 'info',
            self::STATUS_DELIVERED => 'success',
            self::STATUS_READ => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_BOUNCED => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the template used.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    /**
     * Get the campaign if this was part of a campaign.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the referenced model (appointment, invoice, etc.).
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get cost as decimal.
     */
    public function getCostAttribute(): float
    {
        return $this->cost_minor / 100;
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(?string $providerMessageId = null, ?array $providerResponse = null): bool
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = now();
        if ($providerMessageId) {
            $this->provider_message_id = $providerMessageId;
        }
        if ($providerResponse) {
            $this->provider_response_json = $providerResponse;
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
     * Scope to specific channel.
     */
    public function scopeForChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to specific type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to today's notifications.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }
}
