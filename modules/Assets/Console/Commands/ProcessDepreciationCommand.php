<?php

namespace Modules\Assets\Console\Commands;

use Illuminate\Console\Command;
use Modules\Assets\Services\AssetService;
use Modules\Core\Models\Tenant;
use Carbon\Carbon;

class ProcessDepreciationCommand extends Command
{
    protected $signature = 'assets:depreciate
                            {--tenant= : Process for specific tenant ID}
                            {--month= : Period to process (YYYY-MM format, defaults to current month)}
                            {--dry-run : Preview depreciation without posting}';

    protected $description = 'Process monthly depreciation for active assets';

    public function handle(): int
    {
        $month = $this->option('month') ?? now()->format('Y-m');

        // Validate month format
        try {
            Carbon::createFromFormat('Y-m', $month);
        } catch (\Exception $e) {
            $this->error("Invalid month format. Please use YYYY-MM format.");
            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $service = app(AssetService::class);

        if ($dryRun) {
            $this->warn(__('assets::assets.command.dry_run'));
        }

        $this->info(__('assets::assets.command.processing', ['period' => $month]));

        // Get tenants to process
        if ($this->option('tenant')) {
            $tenants = Tenant::where('id', $this->option('tenant'))->get();
        } else {
            $tenants = Tenant::where('is_active', true)->get();
        }

        $totalProcessed = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $totalDepreciation = 0;

        foreach ($tenants as $tenant) {
            $this->line("Processing tenant: {$tenant->name}");

            $results = $service->processMonthlyDepreciation(
                $tenant->id,
                $month,
                $dryRun
            );

            $totalProcessed += $results['processed'];
            $totalSkipped += $results['skipped'];
            $totalErrors += $results['errors'];
            $totalDepreciation += $results['total_depreciation'];

            if ($results['processed'] > 0) {
                $this->info("  - Processed: {$results['processed']} assets");

                if ($this->getOutput()->isVerbose()) {
                    foreach ($results['entries'] as $entry) {
                        $this->line("    - {$entry['asset_code']}: " . format_money($entry['depreciation_amount'] ?? $entry['depreciation_amount_minor'] ?? 0));
                    }
                }
            }

            if ($results['skipped'] > 0) {
                $this->line("  - Skipped: {$results['skipped']} assets");
            }

            if ($results['errors'] > 0) {
                $this->error("  - Errors: {$results['errors']}");
            }
        }

        $this->newLine();
        $this->info(__('assets::assets.command.completed'));
        $this->table(
            ['Metric', 'Value'],
            [
                [__('assets::assets.command.processed'), $totalProcessed],
                [__('assets::assets.command.skipped'), $totalSkipped],
                [__('assets::assets.command.errors'), $totalErrors],
                [__('assets::assets.command.total_depreciation'), format_money($totalDepreciation)],
            ]
        );

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
