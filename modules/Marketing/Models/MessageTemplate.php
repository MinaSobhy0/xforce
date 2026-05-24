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

    // Meta template approval status
    public const META_STATUS_PENDING = 'PENDING';
    public const META_STATUS_APPROVED = 'APPROVED';
    public const META_STATUS_REJECTED = 'REJECTED';
    public const META_STATUS_PAUSED = 'PAUSED';
    public const META_STATUS_DISABLED = 'DISABLED';

    // Meta template categories
    public const META_CATEGORY_MARKETING = 'MARKETING';
    public const META_CATEGORY_UTILITY = 'UTILITY';
    public const META_CATEGORY_AUTHENTICATION = 'AUTHENTICATION';

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
        'meta_template_id',
        'meta_template_status',
        'meta_template_category',
        'meta_template_language',
        'meta_synced_at',
        'meta_last_error',
        'platform_template_id',
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
        'meta_synced_at' => 'datetime',
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
     * SECURITY: HTML escapes variables for email channel to prevent XSS attacks.
     *
     * @param array $variables The variables to substitute
     * @param string|null $locale The locale to use
     * @param bool|null $escapeHtml Whether to HTML-escape values (defaults to true for email channel)
     */
    public function render(array $variables, ?string $locale = null, ?bool $escapeHtml = null): array
    {
        $locale = $locale ?? app()->getLocale();

        // SECURITY: Auto-enable HTML escaping for email channel
        $shouldEscapeHtml = $escapeHtml ?? ($this->channel === self::CHANNEL_EMAIL);

        $content = $this->getTranslation('content', $locale);
        $subject = $this->getTranslation('subject', $locale);

        foreach ($variables as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            // SECURITY: Escape HTML entities to prevent XSS in email content
            $safeValue = $shouldEscapeHtml ? e($value) : $value;
            $content = str_replace($placeholder, $safeValue, $content);
            if ($subject) {
                // Subject is always escaped (no HTML in email subjects)
                $subject = str_replace($placeholder, e($value), $subject);
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
     * SECURITY: Uses plain text (no HTML) for WhatsApp messages - no escaping needed.
     */
    public function buildInteractivePayload(array $variables, ?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        // WhatsApp uses plain text, not HTML - disable escaping
        $rendered = $this->render($variables, $locale, false);

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
                // SECURITY: Sanitize values to remove potential injection characters
                $safeValue = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value);
                $footerText = str_replace('{{' . $key . '}}', $safeValue, $footerText);
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
     * SECURITY: Sanitizes variable values and validates URLs.
     */
    protected function buildHeader(array $variables, string $locale): array
    {
        $headerContent = $this->header_content ?? [];

        switch ($this->header_type) {
            case self::HEADER_TEXT:
                $text = $headerContent[$locale] ?? $headerContent['en'] ?? '';
                foreach ($variables as $key => $value) {
                    // SECURITY: Remove control characters from values
                    $safeValue = preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value);
                    $text = str_replace('{{' . $key . '}}', $safeValue, $text);
                }
                return ['type' => 'text', 'text' => $text];

            case self::HEADER_IMAGE:
                $url = $headerContent['url'] ?? '';
                // SECURITY: Validate URL scheme to prevent javascript: or data: URLs
                if (!$this->isValidMediaUrl($url)) {
                    return [];
                }
                return [
                    'type' => 'image',
                    'image' => ['link' => $url],
                ];

            case self::HEADER_DOCUMENT:
                $url = $headerContent['url'] ?? '';
                // SECURITY: Validate URL scheme
                if (!$this->isValidMediaUrl($url)) {
                    return [];
                }
                return [
                    'type' => 'document',
                    'document' => [
                        'link' => $url,
                        'filename' => preg_replace('/[^a-zA-Z0-9._-]/', '', $headerContent['filename'] ?? 'document.pdf'),
                    ],
                ];

            default:
                return [];
        }
    }

    /**
     * Validate media URL to prevent injection attacks.
     */
    protected function isValidMediaUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        // Only allow http and https schemes
        return in_array(strtolower($scheme ?? ''), ['http', 'https'], true);
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

    /**
     * Render this template into Meta's message-templates API shape.
     *
     * Meta expects components: HEADER (optional), BODY (required),
     * FOOTER (optional), BUTTONS (optional). Our `{{patient_name}}`-style
     * named placeholders are translated to Meta's positional `{{1}}`,
     * `{{2}}` in stable order. The positional mapping is returned so the
     * caller can persist it (and `render()` keeps using named vars at
     * send time without divergence).
     *
     * Returns the full payload ready to POST to /{waba_id}/message_templates.
     *
     * @return array{name:string,language:string,category:string,components:array}
     */
    public function toMetaPayload(?string $locale = null): array
    {
        $locale = $locale ?: ($this->meta_template_language
            ? substr($this->meta_template_language, 0, 2)
            : app()->getLocale());

        $bodyText = $this->getTranslation('content', $locale);
        [$positionalBody, $orderedVars] = $this->convertToPositional((string) $bodyText);

        $components = [];

        // HEADER (optional)
        if ($this->header_type && $this->header_type !== self::HEADER_NONE) {
            $components[] = $this->headerComponentForMeta($locale);
        }

        // BODY (required)
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $positionalBody,
        ];
        if ($orderedVars !== []) {
            // Meta needs an example for each variable so it can preview the
            // template during review. Pull from variables_json hints when
            // available, else use the placeholder name itself.
            $hints = is_array($this->variables_json) ? $this->variables_json : [];
            $bodyComponent['example'] = [
                'body_text' => [
                    array_map(fn ($name) => (string) ($hints[$name] ?? $name), $orderedVars),
                ],
            ];
        }
        $components[] = $bodyComponent;

        // FOOTER (optional)
        if (filled($this->footer)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => (string) $this->footer,
            ];
        }

        // BUTTONS (optional, max 3 quick-reply for now)
        if (! empty($this->buttons_json)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => array_slice(array_map(function ($btn) {
                    return [
                        'type' => 'QUICK_REPLY',
                        'text' => mb_substr((string) ($btn['label'] ?? 'Button'), 0, 25),
                    ];
                }, $this->buttons_json ?? []), 0, 3),
            ];
        }

        return [
            'name' => (string) $this->whatsapp_template_name,
            'language' => $this->meta_template_language ?: 'en_US',
            'category' => $this->meta_template_category ?: self::META_CATEGORY_UTILITY,
            'components' => $components,
        ];
    }

    /**
     * Walk the body text and replace `{{patient_name}}` with `{{1}}` etc.
     * Returns the rewritten text and the ordered list of original variable
     * names (so the caller can build Meta's "example" array in matching order).
     *
     * @return array{0:string,1:array<int,string>}
     */
    protected function convertToPositional(string $text): array
    {
        $names = [];

        $rewritten = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use (&$names) {
            $name = $m[1];
            $existingIdx = array_search($name, $names, true);
            if ($existingIdx === false) {
                $names[] = $name;
                $existingIdx = count($names) - 1;
            }

            return '{{'.($existingIdx + 1).'}}';
        }, $text);

        return [(string) $rewritten, $names];
    }

    protected function headerComponentForMeta(string $locale): array
    {
        $headerContent = $this->header_content ?? [];

        return match ($this->header_type) {
            self::HEADER_TEXT => [
                'type' => 'HEADER',
                'format' => 'TEXT',
                'text' => $headerContent[$locale] ?? $headerContent['en'] ?? '',
            ],
            self::HEADER_IMAGE => [
                'type' => 'HEADER',
                'format' => 'IMAGE',
                'example' => [
                    'header_handle' => [$headerContent['url'] ?? ''],
                ],
            ],
            self::HEADER_DOCUMENT => [
                'type' => 'HEADER',
                'format' => 'DOCUMENT',
                'example' => [
                    'header_handle' => [$headerContent['url'] ?? ''],
                ],
            ],
            default => [],
        };
    }
}
