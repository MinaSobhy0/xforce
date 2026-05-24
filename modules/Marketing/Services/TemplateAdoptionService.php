<?php

namespace Modules\Marketing\Services;

use App\Models\PlatformWhatsAppTemplate;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Tenant;
use Modules\Marketing\Jobs\SubmitTemplateToMetaJob;
use Modules\Marketing\Models\MessageTemplate;

/**
 * Clones a PlatformWhatsAppTemplate (catalog blueprint) into a tenant's
 * local message_templates and queues a Meta submission for that tenant's
 * WABA. Idempotent — re-adopting returns the existing local row.
 *
 * Meta enforces per-WABA approval, so even when 100 clinics adopt the
 * same body, each tenant goes through their own PENDING→APPROVED cycle.
 */
class TemplateAdoptionService
{
    /**
     * @return array{template:MessageTemplate, already_adopted:bool}
     */
    public function adopt(PlatformWhatsAppTemplate $catalog, Tenant $tenant): array
    {
        // Schema is expected to already be switched to the tenant.
        $existing = MessageTemplate::where('platform_template_id', $catalog->id)->first();
        if ($existing) {
            return [
                'template' => $existing,
                'already_adopted' => true,
            ];
        }

        $template = MessageTemplate::create([
            'tenant_id' => $tenant->id,
            'code' => $catalog->code,
            'channel' => MessageTemplate::CHANNEL_WHATSAPP,
            'type' => MessageTemplate::TYPE_CUSTOM,
            'whatsapp_template_name' => $catalog->code,
            'name' => $catalog->name,         // translatable arrays
            'content' => $catalog->body,      // translatable arrays
            'footer' => $catalog->footer ? ($catalog->getTranslation('footer', app()->getLocale()) ?: null) : null,
            'header_type' => $catalog->header_type,
            'header_content' => $catalog->header_content,
            'buttons_json' => $catalog->buttons_json,
            'variables_json' => $catalog->variables_json,
            'meta_template_category' => $catalog->category,
            'meta_template_language' => $catalog->default_language,
            'is_system' => true,
            'is_active' => true,
            'platform_template_id' => $catalog->id,
        ]);

        Log::info('whatsapp.template.adopted', [
            'tenant_id' => $tenant->id,
            'platform_template_id' => $catalog->id,
            'local_template_id' => $template->id,
            'code' => $catalog->code,
        ]);

        // Fire the per-WABA submission to Meta out of band.
        SubmitTemplateToMetaJob::dispatch($tenant->id, $template->id);

        return [
            'template' => $template,
            'already_adopted' => false,
        ];
    }
}
