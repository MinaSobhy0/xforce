<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Data migration to assign existing stock levels to default locations.
 *
 * This migration runs AFTER the StockLocationSeeder creates default locations.
 * For new tenants, this is a no-op (no existing stock to migrate).
 * For existing tenants, run StockLocationSeeder first, then this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Get all stock_levels that don't have a location_id set
        $stockLevelsWithoutLocation = DB::table('stock_levels')
            ->whereNull('location_id')
            ->select('id', 'branch_id')
            ->get();

        if ($stockLevelsWithoutLocation->isEmpty()) {
            return;
        }

        // Group by branch_id
        $byBranch = $stockLevelsWithoutLocation->groupBy('branch_id');

        foreach ($byBranch as $branchId => $levels) {
            // Find the default WH/STOCK location for this branch
            $defaultLocation = DB::table('stock_locations')
                ->where('branch_id', $branchId)
                ->where('code', 'WH/STOCK')
                ->where('location_type', 'internal')
                ->first();

            if (!$defaultLocation) {
                Log::warning("No default WH/STOCK location found for branch {$branchId}. Stock levels not migrated.");
                continue;
            }

            // Update all stock levels for this branch to use the default location
            DB::table('stock_levels')
                ->whereNull('location_id')
                ->where('branch_id', $branchId)
                ->update(['location_id' => $defaultLocation->id]);

            Log::info("Migrated " . $levels->count() . " stock levels to location {$defaultLocation->code} for branch {$branchId}");
        }

        // Also update stock_movements without locations
        $movementsWithoutLocation = DB::table('stock_movements')
            ->whereNull('destination_location_id')
            ->select('id', 'branch_id', 'movement_type')
            ->get();

        if ($movementsWithoutLocation->isEmpty()) {
            return;
        }

        $byBranch = $movementsWithoutLocation->groupBy('branch_id');

        foreach ($byBranch as $branchId => $movements) {
            $defaultLocation = DB::table('stock_locations')
                ->where('branch_id', $branchId)
                ->where('code', 'WH/STOCK')
                ->where('location_type', 'internal')
                ->first();

            if (!$defaultLocation) {
                continue;
            }

            // For incoming movements, set destination_location_id
            // For outgoing movements, set source_location_id
            $incomingTypes = ['in', 'transfer_in', 'purchase_receive', 'return'];
            $outgoingTypes = ['out', 'transfer_out', 'appointment_consume', 'invoice_sale', 'waste'];

            DB::table('stock_movements')
                ->whereNull('destination_location_id')
                ->where('branch_id', $branchId)
                ->whereIn('movement_type', $incomingTypes)
                ->update(['destination_location_id' => $defaultLocation->id]);

            DB::table('stock_movements')
                ->whereNull('source_location_id')
                ->where('branch_id', $branchId)
                ->whereIn('movement_type', $outgoingTypes)
                ->update(['source_location_id' => $defaultLocation->id]);
        }
    }

    public function down(): void
    {
        // Set location_id back to null for all stock levels
        DB::table('stock_levels')->update(['location_id' => null]);

        // Clear location references from movements
        DB::table('stock_movements')->update([
            'source_location_id' => null,
            'destination_location_id' => null,
        ]);
    }
};
