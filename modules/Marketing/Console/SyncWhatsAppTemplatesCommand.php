<?php

namespace Modules\Marketing\Console;

use Illuminate\Console\Command;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\Marketing\Services\Meta\TemplateSyncService;

/**
 * Pulls Meta's authoritative template state into each connected tenant's
 * local message_templates table. Runs daily; also invocable manually
 * with --tenant=SLUG to refresh one tenant immediately.
 *
 *   php artisan whatsapp:sync-templates
 *   php artisan whatsapp:sync-templates --tenant=demo
 */
class SyncWhatsAppTemplatesCommand extends Command
{
    protected $signature = 'whatsapp:sync-templates {--tenant= : Limit to a single tenant slug}';

    protected $description = 'Pull Meta WhatsApp template approval state into each tenant';

    public function handle(TenantSchemaSwitcher $switcher, TemplateSyncService $sync): int
    {
        $query = Tenant::query()
            ->whereRaw("settings->'messaging'->'whatsapp'->'meta'->>'status' = 'active'");

        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched (need messaging.whatsapp.meta.status = active).');

            return self::SUCCESS;
        }

        $totalRows = 0;
        foreach ($tenants as $tenant) {
            $this->line("→ {$tenant->slug}");

            try {
                $switcher->switchTo($tenant);
                $count = $sync->pullAll($tenant);
                $totalRows += $count;
                $this->info("  ✓ synced {$count} templates");
            } catch (\Throwable $e) {
                $this->error("  ✗ {$tenant->slug}: ".$e->getMessage());
            }
        }

        $this->info("Done — {$totalRows} template rows refreshed across {$tenants->count()} tenants.");

        return self::SUCCESS;
    }
}
