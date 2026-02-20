<?php

namespace Modules\Inventory\Listeners;

use Modules\Booking\Events\AppointmentCompleted;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;

class DeductStockOnAppointmentCompleted
{
    /**
     * Handle the event.
     *
     * This listener auto-deducts consumable products when an appointment is completed.
     * The deduction is based on the treatment's consumable product mappings.
     */
    public function handle(AppointmentCompleted $event): void
    {
        $appointment = $event->appointment;

        // Skip if no treatment or no branch
        if (!$appointment->treatment || !$appointment->branch_id) {
            return;
        }

        // Get consumable products for the treatment
        // This assumes treatments have a consumables relationship defined
        $consumables = $this->getConsumablesForTreatment($appointment->treatment);

        if (empty($consumables)) {
            return;
        }

        foreach ($consumables as $consumable) {
            $this->deductStock(
                $consumable['product_id'],
                $consumable['quantity'],
                $appointment->branch_id,
                $appointment->tenant_id,
                $appointment->id
            );
        }
    }

    /**
     * Get consumable products for a treatment.
     */
    protected function getConsumablesForTreatment($treatment): array
    {
        // Check if treatment has consumables relationship
        if (method_exists($treatment, 'consumables') && $treatment->consumables) {
            return $treatment->consumables->map(function ($consumable) {
                return [
                    'product_id' => $consumable->product_id,
                    'quantity' => $consumable->quantity ?? 1,
                ];
            })->toArray();
        }

        // Alternative: Check treatment_consumables pivot table
        // This would be implemented when treatment-product mapping is set up

        return [];
    }

    /**
     * Deduct stock for a product at a branch.
     */
    protected function deductStock(
        string $productId,
        int $quantity,
        string $branchId,
        string $tenantId,
        string $appointmentId
    ): void {
        // Get or create stock level
        $stockLevel = StockLevel::getOrCreate($productId, $branchId, $tenantId);

        // Deduct stock
        $stockLevel->decrease(
            $quantity,
            StockMovement::TYPE_APPOINTMENT_CONSUME,
            'appointment',
            $appointmentId,
            'Auto-deducted on appointment completion'
        );
    }
}
