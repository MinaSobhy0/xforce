<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Narrow audit-log row per SMTP send. Enforced by the send job — the
 * Phase 6 weekly per-address limit and 24h duplicate check both read
 * from here.
 */
class PlatformEmailSend extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'campaign_id',
        'mailable_class',
        'recipient_email',
        'subject',
        'headers',
        'provider_message_id',
        'status',
        'error',
    ];

    protected $casts = [
        'headers' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PlatformEmailCampaign::class, 'campaign_id');
    }
}
