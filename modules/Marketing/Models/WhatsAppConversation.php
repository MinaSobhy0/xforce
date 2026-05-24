<?php

namespace Modules\Marketing\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Patients\Models\Patient;
use XLinic\Framework\Core\Model\BaseModel;

/**
 * A conversation thread with one remote WhatsApp user on one of our
 * WABA phone numbers. Auto-created by InboundMessageProcessor on first
 * inbound; also auto-created by WhatsAppService when we initiate outbound
 * to a new number.
 */
class WhatsAppConversation extends BaseModel
{
    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'remote_phone_e164',
        'remote_display_name',
        'phone_number_id',
        'last_message_at',
        'last_inbound_at',
        'unread_count',
        'metadata',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_inbound_at' => 'datetime',
        'unread_count' => 'integer',
        'metadata' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'conversation_id')->latestOfMany();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Within Meta's 24-hour customer-service window — free-form replies allowed.
     * Outside the window, only approved templates can be sent.
     */
    public function isWithinServiceWindow(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours(24));
    }
}
