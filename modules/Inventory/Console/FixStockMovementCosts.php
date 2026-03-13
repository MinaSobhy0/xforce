<?php

namespace Modules\Inventory\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixStockMovementCosts extends Command
{
    protected $signature = 'inventory:fix-movement-costs
                            {--tenant= : Specific tenant slug to fix (default: all tenants)}
                            {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix stock movement unit costs that were not converted from purchase UOM to stock UOM';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        if ($dryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        // Get tenants to process
        $tenants = DB::table('tenants')
            ->when($tenantSlug, fn($q) => $q->where('slug', $tenantSlug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found');
            return 1;
        }

        $totalFixed = 0;

        foreach ($tenants as $tenant) {
            $this->info("\nProcessing tenant: {$tenant->slug}");

            $schema = "tenant_{$tenant->slug}";

            try {
                DB::connection('tenant')->statement("SET search_path TO \"{$schema}\"");
            } catch (\Exception $e) {
                $this->warn("  Could not switch to schema {$schema}: {$e->getMessage()}");
                continue;
            }

            // Find stock movements from purchase receives that need fixing
            // We need to join with stock_transfer_lines and purchase_order_lines to get the original UOM
            $movements = DB::connection('tenant')
                ->table('stock_movements as sm')
                ->join('stock_transfer_lines as stl', function ($join) {
                    $join->on('stl.stock_movement_id', '=', 'sm.id');
                })
                ->join('stock_transfers as st', 'st.id', '=', 'stl.stock_transfer_id')
                ->join('products as p', 'p.id', '=', 'sm.product_id')
                ->join('uoms as line_uom', 'line_uom.id', '=', 'stl.uom_id')
                ->leftJoin('uoms as stock_uom', 'stock_uom.id', '=', 'p.sales_uom_id')
                ->where('sm.movement_type', 'purchase_receive')
                ->where('sm.unit_cost_minor', '>', 0)
                ->whereRaw('stl.uom_id != COALESCE(p.sales_uom_id, stl.uom_id)')
                ->select([
                    'sm.id as movement_id',
                    'sm.product_id',
                    'sm.unit_cost_minor as current_cost',
                    'sm.quantity',
                    'p.sku',
                    'p.name as product_name',
                    'stl.uom_id as line_uom_id',
                    'line_uom.name as line_uom_name',
                    'line_uom.ratio as line_uom_ratio',
                    'p.sales_uom_id as stock_uom_id',
                    'stock_uom.name as stock_uom_name',
                    'stock_uom.ratio as stock_uom_ratio',
                ])
                ->get();

            if ($movements->isEmpty()) {
                $this->info("  No movements need fixing");
                continue;
            }

            $this->info("  Found {$movements->count()} movements to fix");

            foreach ($movements as $movement) {
                $lineRatio = (float) $movement->line_uom_ratio;
                $stockRatio = (float) ($movement->stock_uom_ratio ?? 1);

                if ($lineRatio <= 0) {
                    $this->warn("  Skipping movement #{$movement->movement_id}: invalid line UOM ratio");
                    continue;
                }

                // Calculate correct cost per stock UOM
                $correctCost = (int) round($movement->current_cost * ($stockRatio / $lineRatio));

                $lineUomName = $this->decodeJsonName($movement->line_uom_name);
                $stockUomName = $this->decodeJsonName($movement->stock_uom_name);
                $productName = $this->decodeJsonName($movement->product_name);

                $this->line(sprintf(
                    "  Movement #%d: %s (%s)",
                    $movement->movement_id,
                    $productName,
                    $movement->sku
                ));
                $this->line(sprintf(
                    "    UOM: %s (ratio %.2f) -> %s (ratio %.2f)",
                    $lineUomName,
                    $lineRatio,
                    $stockUomName,
                    $stockRatio
                ));
                $this->line(sprintf(
                    "    Cost: %s -> %s EGP",
                    number_format($movement->current_cost / 100, 2),
                    number_format($correctCost / 100, 2)
                ));

                if (!$dryRun) {
                    DB::connection('tenant')
                        ->table('stock_movements')
                        ->where('id', $movement->movement_id)
                        ->update(['unit_cost_minor' => $correctCost]);

                    $this->info("    FIXED");
                    $totalFixed++;
                } else {
                    $this->info("    Would fix (dry run)");
                    $totalFixed++;
                }
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("DRY RUN: Would fix {$totalFixed} movements");
        } else {
            $this->info("Fixed {$totalFixed} movements");
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
