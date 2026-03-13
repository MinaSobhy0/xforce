<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateProductCosts extends Command
{
    protected $signature = 'inventory:recalculate-costs
                            {--tenant= : Specific tenant slug (default: all tenants)}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Recalculate product costs from stock movement data (weighted average)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $tenants = DB::table('tenants')
            ->when($tenantSlug, fn($q) => $q->where('slug', $tenantSlug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found');
            return 1;
        }

        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $this->info("\nProcessing tenant: {$tenant->slug}");

            $schema = "tenant_{$tenant->slug}";

            try {
                DB::connection('tenant')->statement("SET search_path TO \"{$schema}\"");
            } catch (\Exception $e) {
                $this->warn("  Could not switch to schema {$schema}: {$e->getMessage()}");
                continue;
            }

            // Get all products with stock movements
            $products = DB::connection('tenant')
                ->table('products')
                ->select('id', 'sku', 'name', 'cost_price_minor')
                ->where('is_active', true)
                ->get();

            foreach ($products as $product) {
                // Calculate weighted average from remaining receipt layers
                $layers = DB::connection('tenant')
                    ->table('stock_movements')
                    ->where('product_id', $product->id)
                    ->whereIn('movement_type', ['purchase_receive', 'in', 'return'])
                    ->where('remaining_quantity', '>', 0)
                    ->selectRaw('SUM(remaining_quantity) as total_qty, SUM(remaining_quantity * unit_cost_minor) as total_value')
                    ->first();

                if (!$layers || !$layers->total_qty || $layers->total_qty <= 0) {
                    continue;
                }

                $newCost = (int) round($layers->total_value / $layers->total_qty);
                $oldCost = $product->cost_price_minor ?? 0;

                if ($newCost === $oldCost) {
                    continue;
                }

                $productName = $this->decodeJsonName($product->name);

                $this->line(sprintf(
                    "  %s (%s): %s -> %s EGP",
                    $productName,
                    $product->sku,
                    number_format($oldCost / 100, 2),
                    number_format($newCost / 100, 2)
                ));

                if (!$dryRun) {
                    DB::connection('tenant')
                        ->table('products')
                        ->where('id', $product->id)
                        ->update(['cost_price_minor' => $newCost]);

                    $this->info("    UPDATED");
                    $totalUpdated++;
                } else {
                    $this->info("    Would update (dry run)");
                    $totalUpdated++;
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN: Would update {$totalUpdated} products");
        } else {
            $this->info("Updated {$totalUpdated} products");
        }

        return 0;
    }

    protected function decodeJsonName($name): string
    {
        if (is_string($name) && str_starts_with($name, '{')) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                return $decoded['en'] ?? $decoded['ar'] ?? $name;
            }
        }
        return $name ?? 'Unknown';
    }
}
