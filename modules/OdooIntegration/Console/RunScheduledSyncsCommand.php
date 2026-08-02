<?php

namespace Modules\OdooIntegration\Console;

use Illuminate\Console\Command;
use Modules\Core\Models\Tenant;
use Modules\Core\Services\TenantSchemaSwitcher;
use Modules\OdooIntegration\Enums\SyncFrequency;
use Modules\OdooIntegration\Models\OdooEntityMapping;
use Modules\OdooIntegration\Services\Sync\SyncEngine;

/**
 * Runs the "hourly" and "daily" sync_frequency options on
 * OdooEntityMapping. The realtime frequency is already handled by
 * eloquent listeners in the service provider; this fills the gap for
 * everything else so ops don't have to click the manual sync button.
 *
 *   php artisan odoo:run-scheduled-syncs                  # hourly (default)
 *   php artisan odoo:run-scheduled-syncs --frequency=daily
 *   php artisan odoo:run-scheduled-syncs --tenant=demo    # only one tenant
 *
 * Iterates every tenant (or the one you pass), switches into their
 * schema, then dispatches SyncEngine::syncEntity() on each active
 * mapping whose sync_frequency matches. Each mapping error is caught
 * so one bad connection can't stop the rest.
 *
 * Direction of the sync is taken from the mapping's own sync_direction —
 * an import-only mapping imports, an export-only mapping exports,
 * bidirectional runs a delta both ways (SyncEngine decides).
 */
class RunScheduledSyncsCommand extends Command
{
    protected $signature = 'odoo:run-scheduled-syncs
                            {--frequency=hourly : hourly|daily|every_two_days}
                            {--tenant= : Limit to a single tenant slug}';

    protected $description = 'Sweep every tenant\'s Odoo entity mappings at the given cadence and pull/push whatever is pending.';

    public function handle(TenantSchemaSwitcher $switcher, SyncEngine $engine): int
    {
        $frequency = strtolower((string) $this->option('frequency'));
        $enum = match ($frequency) {
            'hourly' => SyncFrequency::HOURLY,
            'daily' => SyncFrequency::DAILY,
            'every_two_days' => SyncFrequency::EVERY_TWO_DAYS,
            default => null,
        };

        if (! $enum) {
            $this->error("Unknown --frequency={$frequency}. Use 'hourly', 'daily' or 'every_two_days'.");

            return self::FAILURE;
        }

        $query = Tenant::query();
        if ($slug = $this->option('tenant')) {
            $query->where('slug', $slug);
        }
        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants matched.');

            return self::SUCCESS;
        }

        $totalMappings = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            try {
                $switcher->switchTo($tenant);
            } catch (\Throwable $e) {
                $this->warn("  {$tenant->slug}: schema switch failed — ".$e->getMessage());

                continue;
            }

            // A realtime mapping can pin its IMPORT direction to a slower
            // cadence via settings.import_frequency (e.g. attendance: export
            // realtime, import daily) — those join the matching sweep here.
            $mappings = OdooEntityMapping::query()
                ->where('is_active', true)
                ->where(function ($q) use ($enum) {
                    $q->where('sync_frequency', $enum->value)
                        ->orWhere(function ($q2) use ($enum) {
                            $q2->where('sync_frequency', SyncFrequency::REALTIME->value)
                                ->where('settings->import_frequency', $enum->value);
                        });
                })
                ->get();

            if ($mappings->isEmpty()) {
                continue;
            }

            $this->line("→ {$tenant->slug} — {$mappings->count()} mapping(s)");

            foreach ($mappings as $mapping) {
                try {
                    $log = $engine->syncEntity($mapping, 'delta');
                    $this->info(sprintf(
                        '  ✓ %-30s processed=%d created=%d updated=%d failed=%d',
                        $mapping->odoo_model,
                        $log->records_processed,
                        $log->records_created,
                        $log->records_updated,
                        $log->records_failed,
                    ));
                    $totalMappings++;
                } catch (\Throwable $e) {
                    $this->error("  ✗ {$mapping->odoo_model}: ".$e->getMessage());
                    $totalErrors++;
                }
            }
        }

        $this->info("Done. {$totalMappings} mapping(s) synced, {$totalErrors} error(s).");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
