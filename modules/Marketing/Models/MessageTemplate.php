<?php

namespace Modules\Marketing\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class MessageTemplate extends BaseModel
{
    use HasTranslations;

    protected $table = 'message_templates';

    // Channel types
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';

    // Template types
    public const TYPE_APPOINTMENT_CONFIRMATION = 'appointment_confirmation';
    public const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    public const TYPE_APPOINTMENT_FOLLOWUP = 'appointment_followup';
    public const TYPE_INVOICE_RECEIPT = 'invoice_receipt';
    public const TYPE_PAYMENT_REMINDER = 'payment_reminder';
    public const TYPE_BIRTHDAY = 'birthday';
    public const TYPE_PROMOTIONAL = 'promotional';
    public const TYPE_CUSTOM = 'custom';

    public array $translatable = ['name', 'subject', 'content'];

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'channel',
        'type',
        'subject',
        'content',
        'whatsapp_template_name',
        'whatsapp_template_namespace',
        'variables_json',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'subject' => 'array',
        'content' => 'array',
        'variables_json' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
        'sort_order' => 0,
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
     * Get all template types.
     */
    public static function types(): array
    {
        return [
            self::TYPE_APPOINTMENT_CONFIRMATION => __('marketing::marketing.template_types.appointment_confirmation'),
            self::TYPE_APPOINTMENT_REMINDER => __('marketing::marketing.template_types.appointment_reminder'),
            self::TYPE_APPOINTMENT_FOLLOWUP => __('marketing::marketing.template_types.appointment_followup'),
            self::TYPE_INVOICE_RECEIPT => __('marketing::marketing.template_types.invoice_receipt'),
            self::TYPE_PAYMENT_REMINDER => __('marketing::marketing.template_types.payment_reminder'),
            self::TYPE_BIRTHDAY => __('marketing::marketing.template_types.birthday'),
            self::TYPE_PROMOTIONAL => __('marketing::marketing.template_types.promotional'),
            self::TYPE_CUSTOM => __('marketing::marketing.template_types.custom'),
        ];
    }

    /**
     * Get available template variables.
     */
    public function getAvailableVariables(): array
    {
        return $this->variables_json ?? config('marketing.templates.variables', []);
    }

    /**
     * Render template with variables.
     */
    public function render(array $variables, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();

        $content = $this->getTranslation('content', $locale);
        $subject = $this->getTranslation('subject', $locale);

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $content = str_replace($placeholder, $value, $content);
            if ($subject) {
                $subject = str_replace($placeholder, $value, $subject);
            }
        }

        return [
            'subject' => $subject,
            'content' => $content,
        ];
    }

    /**
     * Get campaigns using this template.
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'template_id');
    }

    /**
     * Scope to active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
}
