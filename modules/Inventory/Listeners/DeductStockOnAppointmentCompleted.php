<?php

namespace Modules\Inventory\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\SessionConsumable;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Services\StockMoveService;

class DeductStockOnAppointmentCompleted
{
    protected StockMoveService $stockMoveService;

    public function __construct(StockMoveService $stockMoveService)
    {
        $this->stockMoveService = $stockMoveService;
    }

    /**
     * Handle the event.
     *
     * This listener auto-deducts products from stock when an appointment is completed.
     * It uses the SessionConsumable records which are auto-populated from service/category.
     *
     * Odoo-like behavior:
     * - Storable products: Stock is deducted via transfer (Treatment Location → Customer Location)
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

        DB::transaction(function () use ($consumables, $appointment) {
            foreach ($consumables as $consumable) {
                $this->deductStock($consumable, $appointment);
            }
        });
    }

    /**
     * Deduct stock for a session consumable.
     * Uses Odoo-like transfer: Treatment Location → Customer Location
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

        // Get the treatment location (source for consumption)
        $sourceLocation = StockLocation::getTreatmentDefaultLocation($branchId);

        if (!$sourceLocation) {
            // Fallback to default stock location
            $sourceLocation = StockLocation::getDefaultLocation($branchId);
        }

        if (!$sourceLocation) {
            \Log::warning('No source location found for appointment consumption', [
                'appointment_id' => $appointment->id,
                'branch_id' => $branchId,
                'consumable_id' => $consumable->id,
            ]);
            return;
        }

        $requiredQty = (int) ceil($consumable->quantity);

        // Use StockMoveService for Odoo-like transfer (Treatment → Customer)
        // Uses the UOM from the session consumable (set when adding the consumable)
        $movement = $this->stockMoveService->createConsumption(
            $consumable->product,
            $sourceLocation,
            $requiredQty,
            $consumable->uom_id, // Use the UOM specified on the consumable
            'session_consumable',
            $consumable->id,
            sprintf(
                'Auto-deducted for session: %s - Appointment #%s',
                $consumable->product->getTranslation('name', 'en') ?? 'Unknown',
                $appointment->id
            )
        );

        // Link the stock movement to the session consumable
        $consumable->update([
            'stock_movement_id' => $movement->id,
        ]);
    }
}
