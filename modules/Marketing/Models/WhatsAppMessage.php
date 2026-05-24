<?php

namespace Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use XLinic\Framework\Core\Model\BaseModel;

class WhatsAppMessage extends BaseModel
{
    protected $table = 'whatsapp_messages';

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_INTERACTIVE = 'interactive';
    public const TYPE_BUTTON_REPLY = 'button_reply';
    public const TYPE_TEMPLATE = 'template';
    public const TYPE_REACTION = 'reaction';
    public const TYPE_LOCATION = 'location';
    public const TYPE_UNKNOWN = 'unknown';

    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'wamid',
        'direction',
        'type',
        'body',
        'media_id',
        'media_path',
        'media_mime',
        'media_filename',
        'media_sha256',
        'interactive_payload',
        'context_wamid',
        'status',
        'template_id',
        'notification_log_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'interactive_payload' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'conversation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(NotificationLog::class, 'notification_log_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === self::DIRECTION_INBOUND;
    }

    public function hasMedia(): bool
    {
        return in_array($this->type, [self::TYPE_IMAGE, self::TYPE_DOCUMENT, self::TYPE_VIDEO, self::TYPE_AUDIO], true);
    }
}
