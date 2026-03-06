<?php

namespace Modules\Inventory\Listeners;

use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\SessionConsumable;
use Modules\Inventory\Models\StockLevel;
use Modules\Inventory\Models\StockMovement;

class DeductStockOnAppointmentCompleted
{
    /**
     * Handle the event.
     *
     * This listener auto-deducts products from stock when an appointment is completed.
     * It uses the SessionConsumable records which are auto-populated from service/category.
     *
     * Odoo-like behavior:
     * - Storable products: Stock is deducted and tracked
     * - Consumable products: No stock deduction (assumed always available)
     */
    public function handle(AppointmentCompleted $event): void
    {
        $appointment = $event->appointment;

        // Skip if no branch
        if (!$appointment->branch_id) {
            return;
        }

        // Get all session consumables that haven't been stock-deducted yet
        $consumables = SessionConsumable::where('appointment_id', $appointment->id)
            ->where('is_deducted', true) // Already marked as deducted in TreatmentSession
            ->whereNull('stock_movement_id') // But no actual stock movement created yet
            ->with('product')
            ->get();

        if ($consumables->isEmpty()) {
            return;
        }

        foreach ($consumables as $consumable) {
            $this->deductStock($consumable, $appointment);
        }
    }

    /**
     * Deduct stock for a session consumable.
     * Only storable products have their stock deducted.
     */
    protected function deductStock(SessionConsumable $consumable, $appointment): void
    {
        if (!$consumable->product) {
            return;
        }

        // Odoo-like: Only deduct stock for storable products
        // Consumable products are assumed always available
        if (!$consumable->product->tracksInventory()) {
            return;
        }

        // Get branch - use consumable's branch if set, otherwise appointment's branch
        $branchId = $consumable->branch_id ?? $appointment->branch_id;

        // Get or create stock level
        $stockLevel = StockLevel::getOrCreate(
            $consumable->product_id,
            $branchId,
            $appointment->tenant_id
        );

        // Check if we have enough stock
        $availableQty = $stockLevel->available_quantity ?? $stockLevel->quantity_on_hand;
        $requiredQty = (int) ceil($consumable->quantity);

        // Deduct stock (allow negative for tracking purposes, business logic can handle alerts)
        $movement = $stockLevel->decrease(
            $requiredQty,
            StockMovement::TYPE_APPOINTMENT_CONSUME,
            'appointment',
            $appointment->id,
            sprintf(
                'Auto-deducted for session: %s - %s',
                $consumable->product?->getTranslation('name', 'en') ?? 'Unknown',
                $appointment->id
            )
        );

        // Link the stock movement to the session consumable
        $consumable->update([
            'stock_movement_id' => $movement->id,
        ]);
    }
}
