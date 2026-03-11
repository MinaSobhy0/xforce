<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\SessionProduct;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Modules\Services\Models\Service;

class InvoiceCalculationService
{
    /**
     * Calculate line item totals.
     * All calculations use integer minor units (cents/piasters).
     *
     * @param int $unitPriceMinor
     * @param float $quantity
     * @param int $discountMinor
     * @param string $discountType
     * @param array|float $taxRates Array of tax rates or single rate for backward compatibility
     */
    public function calculateLine(
        int $unitPriceMinor,
        float $quantity,
        int $discountMinor = 0,
        string $discountType = 'fixed',
        array|float $taxRates = []
    ): array {
        // Calculate subtotal
        $subtotal = (int) round($unitPriceMinor * $quantity);

        // Calculate discount amount
        $discountAmount = 0;
        if ($discountMinor > 0) {
            if ($discountType === 'percent') {
                $discountAmount = (int) round($subtotal * $discountMinor / 100);
            } else {
                $discountAmount = $discountMinor;
            }
        }

        // After discount
        $afterDiscount = max(0, $subtotal - $discountAmount);

        // Calculate tax - handle both array and single value for backward compatibility
        $ratesArray = is_array($taxRates) ? $taxRates : [$taxRates];
        $totalTaxPercent = array_sum(array_map('floatval', $ratesArray));
        $taxAmount = (int) round($afterDiscount * $totalTaxPercent / 100);

        // Total
        $total = $afterDiscount + $taxAmount;

        return [
            'subtotal_minor' => $subtotal,
            'discount_amount_minor' => $discountAmount,
            'tax_minor' => $taxAmount,
            'total_minor' => $total,
        ];
    }

    /**
     * Calculate invoice totals from lines.
     */
    public function calculateInvoice(
        array $lines,
        int $invoiceDiscountMinor = 0,
        string $invoiceDiscountType = 'fixed'
    ): array {
        $subtotal = 0;
        $totalTax = 0;

        foreach ($lines as $line) {
            $subtotal += $line['total_minor'] - ($line['tax_minor'] ?? 0);
            $totalTax += $line['tax_minor'] ?? 0;
        }

        // Apply invoice-level discount
        $discountAmount = 0;
        if ($invoiceDiscountMinor > 0) {
            if ($invoiceDiscountType === 'percent') {
                $discountAmount = (int) round($subtotal * $invoiceDiscountMinor / 100);
            } else {
                $discountAmount = $invoiceDiscountMinor;
            }
        }

        $total = max(0, $subtotal + $totalTax - $discountAmount);

        return [
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discountAmount,
            'tax_minor' => $totalTax,
            'total_minor' => $total,
        ];
    }

    /**
     * Get the default tax rate.
     */
    public function getDefaultTaxRate(): float
    {
        $taxRate = TaxRate::getDefault();
        return $taxRate?->rate ?? config('billing.default_tax_rate', 0);
    }

    /**
     * Get the default tax rates as array.
     */
    public function getDefaultTaxRates(): array
    {
        $taxRate = TaxRate::getDefault();
        return $taxRate ? [(string) $taxRate->rate] : [];
    }

    /**
     * Create invoice line from service.
     */
    public function createLineFromService(
        Service $service,
        float $quantity = 1,
        ?string $branchId = null
    ): array {
        $price = $branchId
            ? $service->getEffectivePrice($branchId)
            : $service->base_price_minor;

        $taxRates = $this->getDefaultTaxRates();

        $calculated = $this->calculateLine($price, $quantity, 0, 'fixed', $taxRates);

        return [
            'service_id' => $service->id,
            'description' => $service->name,
            'quantity' => $quantity,
            'unit_price_minor' => $price,
            'discount_minor' => 0,
            'discount_type' => 'fixed',
            'tax_rates' => $taxRates,
            'tax_minor' => $calculated['tax_minor'],
            'total_minor' => $calculated['total_minor'],
        ];
    }

    /**
     * Create a quick invoice for an appointment.
     *
     * @deprecated Use createInvoiceForSession instead, which includes sold products
     */
    public function createInvoiceForAppointment(
        string $patientId,
        string $branchId,
        string $appointmentId,
        string $serviceId,
        int $priceMinor,
        int $discountMinor = 0,
        ?string $createdByUserId = null
    ): Invoice {
        $taxRates = $this->getDefaultTaxRates();

        $lineCalculation = $this->calculateLine(
            $priceMinor,
            1,
            $discountMinor,
            'fixed',
            $taxRates
        );

        $invoice = Invoice::create([
            'patient_id' => $patientId,
            'branch_id' => $branchId,
            'appointment_id' => $appointmentId,
            'type' => Invoice::TYPE_STANDARD,
            'subtotal_minor' => $lineCalculation['subtotal_minor'],
            'discount_minor' => $lineCalculation['discount_amount_minor'],
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
            'created_by_user_id' => $createdByUserId,
        ]);

        $invoice->lines()->create([
            'service_id' => $serviceId,
            'description' => Service::find($serviceId)?->name ?? 'Service',
            'quantity' => 1,
            'unit_price_minor' => $priceMinor,
            'discount_minor' => $discountMinor,
            'discount_type' => 'fixed',
            'tax_rates' => $taxRates,
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
        ]);

        return $invoice;
    }

    /**
     * Create a comprehensive invoice for a treatment session.
     * Includes the service line and any sold products.
     */
    public function createInvoiceForSession(Appointment $appointment, ?string $createdByUserId = null): Invoice
    {
        return DB::transaction(function () use ($appointment, $createdByUserId) {
            $taxRates = $this->getDefaultTaxRates();
            $sortOrder = 0;

            // Create the invoice
            $invoice = Invoice::create([
                'patient_id' => $appointment->patient_id,
                'branch_id' => $appointment->branch_id,
                'appointment_id' => $appointment->id,
                'treatment_plan_id' => $appointment->treatmentPlanAppointment?->item?->treatment_plan_id,
                'type' => Invoice::TYPE_STANDARD,
                'subtotal_minor' => 0,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => 0,
                'created_by_user_id' => $createdByUserId,
            ]);

            // Create service line
            $this->createServiceLine($invoice, $appointment, $taxRates, $sortOrder++);

            // Create product lines from sold session products
            $soldProducts = SessionProduct::where('appointment_id', $appointment->id)
                ->where('usage_type', SessionProduct::USAGE_SOLD)
                ->with('product')
                ->get();

            foreach ($soldProducts as $sessionProduct) {
                $this->createProductLine($invoice, $sessionProduct, $taxRates, $sortOrder++);
            }

            // Recalculate invoice totals
            $invoice->recalculateTotals();

            return $invoice;
        });
    }

    /**
     * Create a service line for the appointment.
     */
    protected function createServiceLine(
        Invoice $invoice,
        Appointment $appointment,
        array $taxRates,
        int $sortOrder
    ): InvoiceLine {
        $service = $appointment->service;
        $discountType = $appointment->discount_type ?? 'fixed';
        $discountMinor = $appointment->discount_minor ?? 0;

        // Build session description
        $description = $service?->name ?? 'Service';
        if ($planAppt = $appointment->treatmentPlanAppointment) {
            $description .= ' (Session ' . $planAppt->session_number . ' of ' . $planAppt->item->recommended_sessions . ')';
        }

        $lineCalculation = $this->calculateLine(
            $appointment->price_minor,
            1,
            $discountMinor,
            $discountType,
            $taxRates
        );

        // Get revenue account (service's own account, fallback to category)
        $accountId = $service?->getEffectiveServiceRevenueAccountId();

        return $invoice->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'service_id' => $appointment->service_id,
            'account_id' => $accountId,
            'appointment_id' => $appointment->id,
            'treatment_plan_item_id' => $appointment->treatmentPlanAppointment?->treatment_plan_item_id,
            'line_type' => InvoiceLine::LINE_TYPE_SERVICE,
            'description' => $description,
            'quantity' => 1,
            'unit_price_minor' => $appointment->price_minor,
            'discount_minor' => $discountMinor,
            'discount_type' => $discountType,
            'tax_rates' => $taxRates,
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * Create a product line from a session product.
     */
    protected function createProductLine(
        Invoice $invoice,
        SessionProduct $sessionProduct,
        array $taxRates,
        int $sortOrder
    ): InvoiceLine {
        $product = $sessionProduct->product;

        $lineCalculation = $this->calculateLine(
            $sessionProduct->unit_price_minor,
            $sessionProduct->quantity,
            0, // No line-level discount on products
            'fixed',
            $taxRates
        );

        // Get income account from product
        $accountId = $product?->income_account_id;

        $invoiceLine = $invoice->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'product_id' => $product?->id,
            'account_id' => $accountId,
            'session_product_id' => $sessionProduct->id,
            'line_type' => InvoiceLine::LINE_TYPE_PRODUCT,
            'description' => $product?->name ?? 'Product',
            'quantity' => $sessionProduct->quantity,
            'unit_price_minor' => $sessionProduct->unit_price_minor,
            'discount_minor' => 0,
            'discount_type' => 'fixed',
            'tax_rates' => $taxRates,
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
            'sort_order' => $sortOrder,
        ]);

        // Mark session product as invoiced
        $sessionProduct->update([
            'is_invoiced' => true,
            'invoice_line_id' => $invoiceLine->id,
        ]);

        return $invoiceLine;
    }

    /**
     * Create a package line for an invoice.
     */
    public function createPackageLine(
        Invoice $invoice,
        PackageSubscription $subscription,
        array $taxRates,
        int $sortOrder = 0
    ): InvoiceLine {
        $package = $subscription->package;

        // Build description
        $description = $package->translated_name ?? $package->name;
        if ($package->isSessionBased()) {
            $description .= " ({$package->total_sessions} Sessions)";
        } elseif ($package->isPulseBased()) {
            $description .= " ({$package->total_pulses} Pulses)";
        }
        $description .= " - Valid for {$package->validity_days} days";

        $lineCalculation = $this->calculateLine(
            $subscription->package_price_minor,
            1,
            0,
            'fixed',
            $taxRates
        );

        return $invoice->lines()->create([
            'tenant_id' => $invoice->tenant_id,
            'line_type' => InvoiceLine::LINE_TYPE_PACKAGE,
            'description' => $description,
            'quantity' => 1,
            'unit_price_minor' => $subscription->package_price_minor,
            'discount_minor' => 0,
            'discount_type' => 'fixed',
            'tax_rates' => $taxRates,
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
            'package_subscription_id' => $subscription->id,
            'sort_order' => $sortOrder,
        ]);
    }
}
