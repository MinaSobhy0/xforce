<?php

namespace Modules\Marketing\Models;

use XLinic\Framework\Core\Model\BaseModel;
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

    // Button types for WhatsApp interactive messages
    public const BUTTON_TYPE_QUICK_REPLY = 'quick_reply';
    public const BUTTON_TYPE_URL = 'url';
    public const BUTTON_TYPE_PHONE = 'phone';

    // Quick reply button actions
    public const BUTTON_ACTION_CONFIRM = 'confirm_appointment';
    public const BUTTON_ACTION_RESCHEDULE = 'reschedule_appointment';
    public const BUTTON_ACTION_CANCEL = 'cancel_appointment';
    public const BUTTON_ACTION_CUSTOM = 'custom';

    // Header types
    public const HEADER_NONE = 'none';
    public const HEADER_TEXT = 'text';
    public const HEADER_IMAGE = 'image';
    public const HEADER_DOCUMENT = 'document';

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
        'buttons_json',
        'header_type',
        'header_content',
        'footer',
        'is_system',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'name' => 'array',
        'subject' => 'array',
        'content' => 'array',
        'variables_json' => 'array',
        'buttons_json' => 'array',
        'header_content' => 'array',
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

    /**
     * Get button types.
     */
    public static function buttonTypes(): array
    {
        return [
            self::BUTTON_TYPE_QUICK_REPLY => __('marketing::marketing.button_types.quick_reply'),
            self::BUTTON_TYPE_URL => __('marketing::marketing.button_types.url'),
            self::BUTTON_TYPE_PHONE => __('marketing::marketing.button_types.phone'),
        ];
    }

    /**
     * Get button actions for quick reply.
     */
    public static function buttonActions(): array
    {
        return [
            self::BUTTON_ACTION_CONFIRM => __('marketing::marketing.button_actions.confirm_appointment'),
            self::BUTTON_ACTION_RESCHEDULE => __('marketing::marketing.button_actions.reschedule_appointment'),
            self::BUTTON_ACTION_CANCEL => __('marketing::marketing.button_actions.cancel_appointment'),
            self::BUTTON_ACTION_CUSTOM => __('marketing::marketing.button_actions.custom'),
        ];
    }

    /**
     * Get header types.
     */
    public static function headerTypes(): array
    {
        return [
            self::HEADER_NONE => __('marketing::marketing.header_types.none'),
            self::HEADER_TEXT => __('marketing::marketing.header_types.text'),
            self::HEADER_IMAGE => __('marketing::marketing.header_types.image'),
            self::HEADER_DOCUMENT => __('marketing::marketing.header_types.document'),
        ];
    }

    /**
     * Check if template has buttons.
     */
    public function hasButtons(): bool
    {
        return !empty($this->buttons_json);
    }

    /**
     * Get buttons for WhatsApp interactive message.
     */
    public function getButtons(): array
    {
        return $this->buttons_json ?? [];
    }

    /**
     * Build WhatsApp interactive message payload.
     */
    public function buildInteractivePayload(array $variables, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $rendered = $this->render($variables, $locale);

        $payload = [
            'type' => 'button',
            'body' => [
                'text' => $rendered['content'],
            ],
        ];

        // Add header if configured
        if ($this->header_type && $this->header_type !== self::HEADER_NONE) {
            $payload['header'] = $this->buildHeader($variables, $locale);
        }

        // Add footer if present
        if ($this->footer) {
            $footerText = $this->footer;
            foreach ($variables as $key => $value) {
                $footerText = str_replace('{{' . $key . '}}', $value, $footerText);
            }
            $payload['footer'] = ['text' => $footerText];
        }

        // Add buttons
        if ($this->hasButtons()) {
            $payload['action'] = [
                'buttons' => $this->buildButtonsPayload($variables),
            ];
        }

        return $payload;
    }

    /**
     * Build header for interactive message.
     */
    protected function buildHeader(array $variables, string $locale): array
    {
        $headerContent = $this->header_content ?? [];

        switch ($this->header_type) {
            case self::HEADER_TEXT:
                $text = $headerContent[$locale] ?? $headerContent['en'] ?? '';
                foreach ($variables as $key => $value) {
                    $text = str_replace('{{' . $key . '}}', $value, $text);
                }
                return ['type' => 'text', 'text' => $text];

            case self::HEADER_IMAGE:
                return [
                    'type' => 'image',
                    'image' => ['link' => $headerContent['url'] ?? ''],
                ];

            case self::HEADER_DOCUMENT:
                return [
                    'type' => 'document',
                    'document' => [
                        'link' => $headerContent['url'] ?? '',
                        'filename' => $headerContent['filename'] ?? 'document.pdf',
                    ],
                ];

            default:
                return [];
        }
    }

    /**
     * Build buttons payload for WhatsApp API.
     */
    protected function buildButtonsPayload(array $variables): array
    {
        $buttons = [];

        foreach ($this->buttons_json ?? [] as $index => $button) {
            $buttonPayload = [
                'type' => 'reply',
                'reply' => [
                    'id' => $this->buildButtonId($button, $variables),
                    'title' => mb_substr($button['label'] ?? 'Button', 0, 20), // WhatsApp limit: 20 chars
                ],
            ];

            $buttons[] = $buttonPayload;

            // WhatsApp allows max 3 buttons
            if (count($buttons) >= 3) {
                break;
            }
        }

        return $buttons;
    }

    /**
     * Build button ID with action and context data.
     */
    protected function buildButtonId(array $button, array $variables): string
    {
        $action = $button['action'] ?? self::BUTTON_ACTION_CUSTOM;

        // Include appointment_id or other reference in the button ID
        $referenceId = $variables['appointment_id'] ?? $variables['reference_id'] ?? '';

        // Format: action|reference_id|template_code
        return implode('|', [
            $action,
            $referenceId,
            $this->code,
        ]);
    }

    /**
     * Parse button callback ID.
     */
    public static function parseButtonCallback(string $buttonId): array
    {
        $parts = explode('|', $buttonId);

        return [
            'action' => $parts[0] ?? null,
            'reference_id' => $parts[1] ?? null,
            'template_code' => $parts[2] ?? null,
        ];
    }
}
