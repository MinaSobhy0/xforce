<?php

namespace Modules\Assets\Listeners;

use Modules\Inventory\Events\PurchaseOrderReceived;
use Modules\Assets\Services\AssetService;
use Illuminate\Support\Facades\Log;

class CreateAssetOnPurchaseReceived
{
    protected AssetService $assetService;

    public function __construct(AssetService $assetService)
    {
        $this->assetService = $assetService;
    }

    public function handle(PurchaseOrderReceived $event): void
    {
        $line = $event->line;
        $product = $line->product;

        // Check if product has an asset type assigned
        if (!$product || !$product->is_asset || !$product->asset_type_id) {
            return;
        }

        $assetType = $product->assetType;

        // Check if asset type is configured for auto-creation
        if (!$assetType || !$assetType->auto_create_on_purchase) {
            return;
        }

        try {
            // Create one asset for each unit received
            for ($i = 0; $i < $event->quantityReceived; $i++) {
                $this->assetService->createAssetFromPurchase(
                    $event->tenantId,
                    $assetType,
                    [
                        'name' => $product->name,
                        'product_name' => $product->name,
                        'product_id' => $product->id,
                        'branch_id' => $line->purchaseOrder?->branch_id,
                        'purchase_order_id' => $line->purchase_order_id,
                        'purchase_order_line_id' => $line->id,
                        'cost_minor' => $line->unit_price_minor,
                        'received_date' => now(),
                    ]
                );
            }

            Log::info('Assets created from purchase order', [
                'purchase_order_line_id' => $line->id,
                'product_id' => $product->id,
                'quantity' => $event->quantityReceived,
                'asset_type_id' => $assetType->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create assets from purchase order', [
                'purchase_order_line_id' => $line->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
