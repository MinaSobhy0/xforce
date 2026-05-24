<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Marketing\Models\MessageTemplate;
use Spatie\Translatable\HasTranslations;

/**
 * Master WhatsApp template authored once by the platform admin.
 *
 * Lives in the public schema (central connection) — NOT scoped to any
 * tenant. Tenants discover these via the Catalog page and "adopt" via
 * TemplateAdoptionService, which clones into the tenant's per-schema
 * message_templates table and submits to Meta.
 *
 * Mirrors the storage pattern of PlatformSetting at app/Models/PlatformSetting.php.
 */
class PlatformWhatsAppTemplate extends Model
{
    use HasTranslations;

    protected $connection = 'central';

    protected $table = 'public.platform_whatsapp_templates';

    public array $translatable = ['name', 'description', 'body', 'footer'];

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'body',
        'header_type',
        'header_content',
        'footer',
        'buttons_json',
        'variables_json',
        'default_language',
        'is_active',
        'sort_order',
        'created_by_user_id',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'body' => 'array',
        'footer' => 'array',
        'header_content' => 'array',
        'buttons_json' => 'array',
        'variables_json' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function categories(): array
    {
        return [
            MessageTemplate::META_CATEGORY_UTILITY => 'Utility',
            MessageTemplate::META_CATEGORY_MARKETING => 'Marketing',
            MessageTemplate::META_CATEGORY_AUTHENTICATION => 'Authentication',
        ];
    }

    /**
     * Render this catalog template into Meta's API shape. Used when the
     * adoption flow needs to know the exact payload Meta will receive.
     * Mirrors MessageTemplate::toMetaPayload() — same positional-variable
     * conversion logic, just sourced from this row's translatable fields.
     */
    public function toMetaPayload(?string $locale = null): array
    {
        $locale = $locale ?: substr($this->default_language, 0, 2);

        $bodyText = $this->getTranslation('body', $locale);
        [$positionalBody, $orderedVars] = $this->convertToPositional((string) $bodyText);

        $components = [];

        if ($this->header_type && $this->header_type !== 'none') {
            $hc = $this->header_content ?? [];
            $components[] = match ($this->header_type) {
                'text' => [
                    'type' => 'HEADER',
                    'format' => 'TEXT',
                    'text' => $hc[$locale] ?? $hc['en'] ?? '',
                ],
                'image', 'document' => [
                    'type' => 'HEADER',
                    'format' => strtoupper($this->header_type),
                    'example' => ['header_handle' => [$hc['url'] ?? '']],
                ],
                default => [],
            };
        }

        $bodyComponent = ['type' => 'BODY', 'text' => $positionalBody];
        if ($orderedVars !== []) {
            $hints = is_array($this->variables_json) ? $this->variables_json : [];
            $bodyComponent['example'] = [
                'body_text' => [
                    array_map(fn ($n) => (string) ($hints[$n] ?? $n), $orderedVars),
                ],
            ];
        }
        $components[] = $bodyComponent;

        if ($footer = $this->getTranslation('footer', $locale)) {
            $components[] = ['type' => 'FOOTER', 'text' => (string) $footer];
        }

        if (! empty($this->buttons_json)) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => array_slice(array_map(fn ($b) => [
                    'type' => 'QUICK_REPLY',
                    'text' => mb_substr((string) ($b['label'] ?? 'Button'), 0, 25),
                ], $this->buttons_json), 0, 3),
            ];
        }

        return [
            'name' => $this->code,
            'language' => $this->default_language,
            'category' => $this->category,
            'components' => $components,
        ];
    }

    /** @return array{0:string,1:array<int,string>} */
    protected function convertToPositional(string $text): array
    {
        $names = [];
        $rewritten = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use (&$names) {
            $name = $m[1];
            $idx = array_search($name, $names, true);
            if ($idx === false) {
                $names[] = $name;
                $idx = count($names) - 1;
            }
            return '{{'.($idx + 1).'}}';
        }, $text);
        return [(string) $rewritten, $names];
    }
}
