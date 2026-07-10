<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single platform-marketing broadcast. Composed by SuperAdmin staff,
 * materialized into per-recipient rows at Send Now / scheduler fire.
 */
class PlatformEmailCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SCHEDULED => 'Scheduled',
        self::STATUS_SENDING => 'Sending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    protected $fillable = [
        'name',
        'subject',
        'preheader',
        'from_name',
        'reply_to',
        'body_html',
        'body_text',
        'list_id',
        'status',
        'scheduled_at',
        'started_at',
        'finished_at',
        'ai_personalize',
        'ai_model',
        'ai_prompt_template',
        'ai_use_batch_api',
        'created_by_user_id',
    ];

    // In-memory defaults so freshly-created models act consistent with
    // the DB defaults defined in the migration. Prevents "$camp->status
    // is empty" until the record is re-fetched.
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'sent_count' => 0,
        'delivered_count' => 0,
        'opened_count' => 0,
        'clicked_count' => 0,
        'bounced_count' => 0,
        'unsubscribed_count' => 0,
        'complained_count' => 0,
        'failed_count' => 0,
        'ai_personalize' => false,
        'ai_use_batch_api' => false,
        'ai_total_cost_usd_cents' => 0,
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'ai_personalize' => 'boolean',
        'ai_use_batch_api' => 'boolean',
        'sent_count' => 'integer',
        'delivered_count' => 'integer',
        'opened_count' => 'integer',
        'clicked_count' => 'integer',
        'bounced_count' => 'integer',
        'unsubscribed_count' => 'integer',
        'complained_count' => 'integer',
        'failed_count' => 'integer',
        'ai_total_cost_usd_cents' => 'integer',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailList::class, 'list_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(PlatformEmailCampaignRecipient::class, 'campaign_id');
    }

    public function sends(): HasMany
    {
        return $this->hasMany(PlatformEmailSend::class, 'campaign_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_CANCELLED], true);
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }
}
