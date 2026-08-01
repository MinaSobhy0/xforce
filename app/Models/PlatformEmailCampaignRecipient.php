<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (campaign, address) pairing, frozen at materialize time.
 * The Send + Render jobs write here; the tracking routes update here.
 */
class PlatformEmailCampaignRecipient extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    public const STATUS_SUPPRESSED = 'suppressed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_RENDERING => 'Rendering',
        self::STATUS_SENDING => 'Sending',
        self::STATUS_SENT => 'Sent',
        self::STATUS_DELIVERED => 'Delivered',
        self::STATUS_FAILED => 'Failed',
        self::STATUS_BOUNCED => 'Bounced',
        self::STATUS_UNSUBSCRIBED => 'Unsubscribed',
        self::STATUS_SUPPRESSED => 'Suppressed',
    ];

    protected $fillable = [
        'campaign_id',
        'email',
        'name_hint',
        'status',
        'context',
        'rendered_body_html',
        'rendered_at',
        'ai_tokens_input',
        'ai_tokens_output',
        'ai_cost_usd_cents',
        'provider_message_id',
        'sent_at',
        'delivered_at',
        'opened_at',
        'first_clicked_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'context' => 'array',
        'rendered_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'first_clicked_at' => 'datetime',
        'failed_at' => 'datetime',
        'ai_tokens_input' => 'integer',
        'ai_tokens_output' => 'integer',
        'ai_cost_usd_cents' => 'integer',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailCampaign::class, 'campaign_id');
    }
}
