<?php

namespace Modules\Assets\Services;

use Modules\Assets\Models\Asset;
use Modules\Assets\Models\AssetType;
use Modules\Assets\Models\AssetDepreciationEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AssetService
{
    protected AssetGLService $glService;

    public function __construct(AssetGLService $glService)
    {
        $this->glService = $glService;
    }

    /**
     * Create a new asset
     */
    public function createAsset(array $data): Asset
    {
        return DB::transaction(function () use ($data) {
            $asset = Asset::create($data);

            // Calculate and set derived values
            if ($asset->assetType) {
                $asset->salvage_value_minor = $asset->assetType->calculateSalvageValue($asset->acquisition_cost_minor);
                $asset->depreciable_value_minor = $asset->acquisition_cost_minor - $asset->salvage_value_minor;
                $asset->book_value_minor = $asset->acquisition_cost_minor;

                // Set depreciation start date if not provided
                if (!$asset->depreciation_start_date && $asset->acquisition_date) {
                    // Default: start depreciation from first day of next month
                    $asset->depreciation_start_date = $asset->acquisition_date
                        ->copy()
                        ->addMonth()
                        ->startOfMonth();
                }

                $asset->save();
            }

            return $asset->fresh();
        });
    }

    /**
     * Create asset from purchase order line
     */
    public function createAssetFromPurchase(
        string $tenantId,
        AssetType $assetType,
        array $purchaseData
    ): Asset {
        return $this->createAsset([
            'tenant_id' => $tenantId,
            'name' => $purchaseData['name'] ?? $purchaseData['product_name'] ?? 'Asset',
            'asset_type_id' => $assetType->id,
            'branch_id' => $purchaseData['branch_id'] ?? null,
            'acquisition_date' => $purchaseData['received_date'] ?? now(),
            'acquisition_cost_minor' => $purchaseData['cost_minor'],
            'acquisition_method' => Asset::ACQUISITION_PURCHASE,
            'purchase_order_id' => $purchaseData['purchase_order_id'] ?? null,
            'purchase_order_line_id' => $purchaseData['purchase_order_line_id'] ?? null,
            'product_id' => $purchaseData['product_id'] ?? null,
            'serial_number' => $purchaseData['serial_number'] ?? null,
            'location' => $purchaseData['location'] ?? null,
            'notes' => $purchaseData['notes'] ?? null,
            'status' => Asset::STATUS_DRAFT,
        ]);
    }

    /**
     * Activate an asset (post acquisition journal entry)
     */
    public function activateAsset(Asset $asset): bool
    {
        if (!$asset->canActivate()) {
            Log::warning('Cannot activate asset', [
                'asset_id' => $asset->id,
                'status' => $asset->status,
            ]);
            return false;
        }

        return DB::transaction(function () use ($asset) {
            // Post acquisition journal entry
            $journalEntry = $this->glService->postAssetAcquisition($asset);

            if (!$journalEntry) {
                Log::error('Failed to post asset acquisition journal entry', [
                    'asset_id' => $asset->id,
                ]);
                return false;
            }

            // Update asset status
            $asset->update([
                'status' => Asset::STATUS_ACTIVE,
                'acquisition_journal_entry_id' => $journalEntry->id,
            ]);

            return true;
        });
    }

    /**
     * Process monthly depreciation for a specific period
     */
    public function processMonthlyDepreciation(
        string $tenantId,
        string $periodLabel,
        bool $dryRun = false
    ): array {
        $period = Carbon::createFromFormat('Y-m', $periodLabel);
        $periodStart = $period->copy()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();

        $results = [
            'processed' => 0,
            'skipped' => 0,
            'errors' => 0,
            'total_depreciation' => 0,
            'entries' => [],
        ];

        // Get all active assets that can be depreciated
        $assets = Asset::where('tenant_id', $tenantId)
            ->where('status', Asset::STATUS_ACTIVE)
            ->whereHas('assetType', function ($q) {
                $q->where('depreciation_method', '!=', AssetType::METHOD_NO_DEPRECIATION);
            })
            ->where('depreciation_start_date', '<=', $periodEnd)
            ->where('book_value_minor', '>', DB::raw('COALESCE(salvage_value_minor, 0)'))
            ->get();

        foreach ($assets as $asset) {
            // Check if depreciation already exists for this period
            $existingEntry = AssetDepreciationEntry::where('asset_id', $asset->id)
                ->where('period_label', $periodLabel)
                ->first();

            if ($existingEntry) {
                $results['skipped']++;
                continue;
            }

            // Calculate depreciation
            $depreciationAmount = $asset->calculateMonthlyDepreciation($period);

            if ($depreciationAmount <= 0) {
                $results['skipped']++;
                continue;
            }

            $newAccumulated = $asset->accumulated_depreciation_minor + $depreciationAmount;
            $newBookValue = $asset->acquisition_cost_minor - $newAccumulated;

            $entryData = [
                'tenant_id' => $tenantId,
                'asset_id' => $asset->id,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'period_label' => $periodLabel,
                'depreciation_amount_minor' => $depreciationAmount,
                'accumulated_depreciation_minor' => $newAccumulated,
                'book_value_minor' => $newBookValue,
                'status' => AssetDepreciationEntry::STATUS_DRAFT,
            ];

            if ($dryRun) {
                $results['entries'][] = [
                    'asset_code' => $asset->code,
                    'asset_name' => $asset->name,
                    'depreciation_amount' => $depreciationAmount,
                    'new_accumulated' => $newAccumulated,
                    'new_book_value' => $newBookValue,
                ];
                $results['processed']++;
                $results['total_depreciation'] += $depreciationAmount;
                continue;
            }

            try {
                DB::transaction(function () use ($asset, $entryData, &$results) {
                    // Create depreciation entry
                    $entry = AssetDepreciationEntry::create($entryData);

                    // Post journal entry
                    $journalEntry = $this->glService->postDepreciationEntry($asset, $entry);

                    if ($journalEntry) {
                        $entry->update([
                            'journal_entry_id' => $journalEntry->id,
                            'status' => AssetDepreciationEntry::STATUS_POSTED,
                        ]);
                    }

                    // Update asset
                    $asset->update([
                        'accumulated_depreciation_minor' => $entryData['accumulated_depreciation_minor'],
                        'book_value_minor' => $entryData['book_value_minor'],
                        'last_depreciation_date' => $entryData['period_end'],
                    ]);

                    // Check if fully depreciated
                    if ($asset->book_value_minor <= $asset->salvage_value_minor) {
                        $asset->update(['status' => Asset::STATUS_FULLY_DEPRECIATED]);
                    }

                    $results['processed']++;
                    $results['total_depreciation'] += $entryData['depreciation_amount_minor'];
                    $results['entries'][] = [
                        'asset_code' => $asset->code,
                        'entry_id' => $entry->id,
                        'depreciation_amount' => $entryData['depreciation_amount_minor'],
                    ];
                });
            } catch (\Exception $e) {
                Log::error('Failed to process depreciation for asset', [
                    'asset_id' => $asset->id,
                    'period' => $periodLabel,
                    'error' => $e->getMessage(),
                ]);
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Dispose an asset
     */
    public function disposeAsset(
        Asset $asset,
        string $disposalMethod,
        int $proceedsMinor = 0,
        ?string $notes = null,
        ?Carbon $disposalDate = null
    ): bool {
        if (!$asset->canDispose()) {
            Log::warning('Cannot dispose asset', [
                'asset_id' => $asset->id,
                'status' => $asset->status,
            ]);
            return false;
        }

        return DB::transaction(function () use ($asset, $disposalMethod, $proceedsMinor, $notes, $disposalDate) {
            // Post disposal journal entry
            $journalEntry = $this->glService->postAssetDisposal($asset, $proceedsMinor);

            // Update asset
            $asset->update([
                'status' => Asset::STATUS_DISPOSED,
                'disposal_date' => $disposalDate ?? now(),
                'disposal_method' => $disposalMethod,
                'disposal_value_minor' => $proceedsMinor,
                'disposal_notes' => $notes,
                'disposal_journal_entry_id' => $journalEntry?->id,
            ]);

            return true;
        });
    }

    /**
     * Write off an asset (similar to disposal but typically due to damage/theft with no proceeds)
     */
    public function writeOffAsset(Asset $asset, ?string $reason = null): bool
    {
        return $this->disposeAsset(
            $asset,
            Asset::DISPOSAL_DAMAGE,
            0,
            $reason ?? 'Asset written off'
        );
    }

    /**
     * Get depreciation summary for a tenant
     */
    public function getDepreciationSummary(string $tenantId, ?string $periodLabel = null): array
    {
        $query = AssetDepreciationEntry::where('tenant_id', $tenantId)
            ->where('status', AssetDepreciationEntry::STATUS_POSTED);

        if ($periodLabel) {
            $query->where('period_label', $periodLabel);
        }

        return [
            'total_entries' => $query->count(),
            'total_depreciation' => $query->sum('depreciation_amount_minor'),
            'by_period' => $query->select('period_label')
                ->selectRaw('SUM(depreciation_amount_minor) as total')
                ->selectRaw('COUNT(*) as count')
                ->groupBy('period_label')
                ->orderBy('period_label', 'desc')
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Get asset statistics for a tenant
     */
    public function getAssetStatistics(string $tenantId): array
    {
        $assets = Asset::where('tenant_id', $tenantId);

        return [
            'total_assets' => $assets->count(),
            'total_acquisition_value' => $assets->sum('acquisition_cost_minor'),
            'total_accumulated_depreciation' => $assets->sum('accumulated_depreciation_minor'),
            'total_book_value' => $assets->sum('book_value_minor'),
            'by_status' => $assets->select('status')
                ->selectRaw('COUNT(*) as count')
                ->selectRaw('SUM(acquisition_cost_minor) as total_cost')
                ->selectRaw('SUM(book_value_minor) as total_book_value')
                ->groupBy('status')
                ->get()
                ->keyBy('status')
                ->toArray(),
        ];
    }
}
