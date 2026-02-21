<?php

namespace Modules\Billing\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceCalculationService;
use Modules\Billing\Services\PaymentIntegrationService;
use Modules\Booking\Events\AppointmentCompleted;
use Modules\Booking\Models\Appointment;

class CreateInvoiceOnAppointmentComplete implements ShouldQueue
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
            $invoice = $this->createInvoice($appointment);

            // Apply member discount if applicable
            $this->paymentService->applyMemberDiscountToInvoice($invoice);

            Log::info("Auto-created invoice {$invoice->code} for appointment {$appointment->code}");
        } catch (\Exception $e) {
            Log::error("Failed to auto-create invoice for appointment {$appointment->id}: " . $e->getMessage());
        }
    }

    /**
     * Check if an invoice already exists for this appointment.
     */
    protected function invoiceExistsForAppointment(Appointment $appointment): bool
    {
        return Invoice::where('appointment_id', $appointment->id)
            ->whereNotIn('status', [Invoice::STATUS_CANCELLED, Invoice::STATUS_VOIDED])
            ->exists();
    }

    /**
     * Create the invoice for the appointment.
     */
    protected function createInvoice(Appointment $appointment): Invoice
    {
        return $this->calculationService->createInvoiceForAppointment(
            $appointment->patient_id,
            $appointment->branch_id,
            $appointment->id,
            $appointment->service_id,
            $appointment->price_minor,
            $appointment->discount_minor ?? 0,
            auth()->id()
        );
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(AppointmentCompleted $event): bool
    {
        // Only queue if auto-invoice is enabled and no invoice exists
        return config('billing.auto_invoice_on_complete', true)
            && !$this->invoiceExistsForAppointment($event->appointment);
    }
}
