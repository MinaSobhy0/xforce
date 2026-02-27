<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceCalculationService;
use Modules\Billing\Services\PaymentIntegrationService;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\Appointment;

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

        // Check if auto-invoice is enabled
        if (!config('billing.auto_invoice_on_complete', true)) {
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
