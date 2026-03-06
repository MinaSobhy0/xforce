<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceCalculationService;
use Modules\Billing\Services\PaymentIntegrationService;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\SessionProduct;
use Modules\Packages\Services\PackageService;

class CreateInvoiceOnAppointmentComplete
{
    protected InvoiceCalculationService $calculationService;
    protected PaymentIntegrationService $paymentService;

    public function __construct(
        InvoiceCalculationService $calculationService,
        PaymentIntegrationService $paymentService
    ) {
        $this->calculationService = $calculationService;
        $this->paymentService = $paymentService;
    }

    /**
     * Handle the event.
     */
    public function handle(AppointmentCompleted $event): void
    {
        $appointment = $event->appointment;

        // Handle package session - ALWAYS record usage (revenue recognition happens via event)
        // This must happen regardless of auto-invoice setting
        if ($appointment->isPackageSession()) {
            $this->handlePackageSession($appointment);
            return;
        }

        // Check if auto-invoice is enabled
        // When using visit-based invoicing, this should be false
        if (!config('billing.auto_invoice_on_complete', true)) {
            Log::debug("Auto-invoice disabled, skipping invoice creation for appointment {$appointment->id}");
            return;
        }

        // Skip if invoice already exists for this appointment
        if ($this->invoiceExistsForAppointment($appointment)) {
            Log::debug("Invoice already exists for appointment {$appointment->id}");
            return;
        }

        // Skip if appointment has no service or price
        if (!$appointment->service_id || !$appointment->price_minor) {
            Log::debug("Appointment {$appointment->id} has no service or price, skipping auto-invoice");
            return;
        }

        try {
            Log::info("Creating invoice for appointment", [
                'appointment_id' => $appointment->id,
                'service_id' => $appointment->service_id,
                'price_minor' => $appointment->price_minor,
            ]);

            $invoice = $this->createInvoice($appointment);

            // Apply member discount if applicable
            $this->paymentService->applyMemberDiscountToInvoice($invoice);

            Log::info("Auto-created invoice {$invoice->code} for appointment {$appointment->code}", [
                'invoice_id' => $invoice->id,
                'lines_count' => $invoice->lines()->count(),
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to auto-create invoice for appointment {$appointment->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle package session completion.
     * Records package usage and creates invoice only for sold products.
     */
    protected function handlePackageSession(Appointment $appointment): void
    {
        try {
            $subscription = $appointment->packageSubscription;

            if (!$subscription) {
                Log::warning("Package session appointment has no subscription", [
                    'appointment_id' => $appointment->id,
                ]);
                return;
            }

            // Determine quantity and unit type based on package item consumption type
            $quantityUsed = 1;
            $unitType = null; // Let useSession determine from item

            // Check if this is a pulse-based service in the package
            $packageItem = $subscription->package?->items()
                ->where('service_id', $appointment->service_id)
                ->first();

            if ($packageItem && $packageItem->isPulseBased()) {
                // Get pulses from equipment dynamic parameters
                $sessionData = $appointment->sessionData;

                if ($sessionData) {
                    $pulsesFromEquipment = $sessionData->getPulsesFromEquipmentParameters();

                    if ($pulsesFromEquipment > 0) {
                        $quantityUsed = $pulsesFromEquipment;
                        $unitType = 'pulse';

                        Log::info("Pulse-based package session: consuming {$quantityUsed} pulses from equipment parameters", [
                            'appointment_id' => $appointment->id,
                            'subscription_id' => $subscription->id,
                        ]);
                    } else {
                        // Fallback: check treatment areas for pulses
                        $pulsesFromAreas = $sessionData->getTotalPulses();
                        if ($pulsesFromAreas > 0) {
                            $quantityUsed = $pulsesFromAreas;
                            $unitType = 'pulse';

                            Log::info("Pulse-based package session: consuming {$quantityUsed} pulses from treatment areas", [
                                'appointment_id' => $appointment->id,
                                'subscription_id' => $subscription->id,
                            ]);
                        } else {
                            // No pulses recorded - log warning but still record 1 session
                            Log::warning("Pulse-based package session but no pulses recorded in equipment parameters or treatment areas", [
                                'appointment_id' => $appointment->id,
                                'subscription_id' => $subscription->id,
                            ]);
                        }
                    }
                }
            }

            // Record package usage (this triggers revenue recognition via event)
            $packageService = app(PackageService::class);
            $packageService->useSession(
                $subscription,
                $appointment->service_id,
                $appointment->id,
                $quantityUsed,
                $unitType
            );

            Log::info("Package session recorded for appointment {$appointment->code}", [
                'subscription_id' => $subscription->id,
                'quantity_used' => $quantityUsed,
                'unit_type' => $unitType ?? 'session',
                'sessions_remaining' => $subscription->sessions_remaining,
            ]);

            // Check if there are sold products - if so, create invoice for products only
            // BUT only if auto-invoice is enabled (not using visit-based invoicing)
            if (config('billing.auto_invoice_on_complete', true)) {
                $soldProducts = SessionProduct::where('appointment_id', $appointment->id)
                    ->where('usage_type', SessionProduct::USAGE_SOLD)
                    ->exists();

                if ($soldProducts) {
                    $this->createProductsOnlyInvoice($appointment);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to handle package session for appointment {$appointment->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create invoice for sold products only (no service line).
     * Used when appointment is a package session but products were upsold.
     */
    protected function createProductsOnlyInvoice(Appointment $appointment): ?Invoice
    {
        $soldProducts = SessionProduct::where('appointment_id', $appointment->id)
            ->where('usage_type', SessionProduct::USAGE_SOLD)
            ->with('product')
            ->get();

        if ($soldProducts->isEmpty()) {
            return null;
        }

        $taxRates = $this->calculationService->getDefaultTaxRates();
        $totalMinor = 0;
        $taxMinor = 0;

        // Calculate totals from products
        foreach ($soldProducts as $product) {
            $lineCalc = $this->calculationService->calculateLine(
                $product->unit_price_minor,
                $product->quantity,
                0,
                'fixed',
                $taxRates
            );
            $totalMinor += $lineCalc['total_minor'];
            $taxMinor += $lineCalc['tax_minor'];
        }

        $subtotalMinor = $totalMinor - $taxMinor;

        // Create invoice
        $invoice = Invoice::create([
            'tenant_id' => $appointment->tenant_id,
            'patient_id' => $appointment->patient_id,
            'branch_id' => $appointment->branch_id,
            'appointment_id' => $appointment->id,
            'type' => Invoice::TYPE_STANDARD,
            'subtotal_minor' => $subtotalMinor,
            'discount_minor' => 0,
            'tax_minor' => $taxMinor,
            'total_minor' => $totalMinor,
            'created_by_user_id' => auth()->id(),
        ]);

        // Create product lines
        $sortOrder = 0;
        foreach ($soldProducts as $sessionProduct) {
            $product = $sessionProduct->product;
            $lineCalc = $this->calculationService->calculateLine(
                $sessionProduct->unit_price_minor,
                $sessionProduct->quantity,
                0,
                'fixed',
                $taxRates
            );

            $invoiceLine = $invoice->lines()->create([
                'tenant_id' => $invoice->tenant_id,
                'product_id' => $product?->id,
                'session_product_id' => $sessionProduct->id,
                'line_type' => \Modules\Billing\Models\InvoiceLine::LINE_TYPE_PRODUCT,
                'description' => $product?->name ?? 'Product',
                'quantity' => $sessionProduct->quantity,
                'unit_price_minor' => $sessionProduct->unit_price_minor,
                'discount_minor' => 0,
                'discount_type' => 'fixed',
                'tax_rates' => $taxRates,
                'tax_minor' => $lineCalc['tax_minor'],
                'total_minor' => $lineCalc['total_minor'],
                'sort_order' => $sortOrder++,
            ]);

            // Mark session product as invoiced
            $sessionProduct->update([
                'is_invoiced' => true,
                'invoice_line_id' => $invoiceLine->id,
            ]);
        }

        Log::info("Created products-only invoice for package session", [
            'appointment_id' => $appointment->id,
            'invoice_id' => $invoice->id,
            'products_count' => $soldProducts->count(),
        ]);

        return $invoice;
    }

    /**
     * Check if an invoice already exists for this appointment.
     */
    protected function invoiceExistsForAppointment(Appointment $appointment): bool
    {
        return Invoice::where('appointment_id', $appointment->id)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_REFUNDED])
            ->exists();
    }

    /**
     * Create the invoice for the appointment.
     * Uses the new session invoice method which includes sold products and proper discount handling.
     */
    protected function createInvoice(Appointment $appointment): Invoice
    {
        // Load required relationships for session invoice
        $appointment->load([
            'service',
            'treatmentPlanAppointment.item.treatmentPlan',
            'products' => fn ($q) => $q->where('usage_type', \Modules\Booking\Models\SessionProduct::USAGE_SOLD),
        ]);

        return $this->calculationService->createInvoiceForSession(
            $appointment,
            auth()->id()
        );
    }

}
