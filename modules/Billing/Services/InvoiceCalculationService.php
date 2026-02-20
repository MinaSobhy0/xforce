<?php

namespace Modules\Billing\Services;

use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\TaxRate;
use Modules\Treatments\Models\Treatment;

class InvoiceCalculationService
{
    /**
     * Calculate line item totals.
     * All calculations use integer minor units (cents/piasters).
     */
    public function calculateLine(
        int $unitPriceMinor,
        float $quantity,
        int $discountMinor = 0,
        string $discountType = 'fixed',
        float $taxRate = 0
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

        // Calculate tax
        $taxAmount = (int) round($afterDiscount * $taxRate / 100);

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
        return $taxRate?->rate ?? config('billing.default_tax_rate', 14);
    }

    /**
     * Create invoice line from treatment.
     */
    public function createLineFromTreatment(
        Treatment $treatment,
        float $quantity = 1,
        ?string $branchId = null
    ): array {
        $price = $branchId
            ? $treatment->getEffectivePrice($branchId)
            : $treatment->base_price_minor;

        $taxRate = $this->getDefaultTaxRate();

        $calculated = $this->calculateLine($price, $quantity, 0, 'fixed', $taxRate);

        return [
            'treatment_id' => $treatment->id,
            'description' => $treatment->name,
            'quantity' => $quantity,
            'unit_price_minor' => $price,
            'discount_minor' => 0,
            'discount_type' => 'fixed',
            'tax_rate' => $taxRate,
            'tax_minor' => $calculated['tax_minor'],
            'total_minor' => $calculated['total_minor'],
        ];
    }

    /**
     * Create a quick invoice for an appointment.
     */
    public function createInvoiceForAppointment(
        string $patientId,
        string $branchId,
        string $appointmentId,
        string $treatmentId,
        int $priceMinor,
        int $discountMinor = 0,
        ?string $createdByUserId = null
    ): Invoice {
        $taxRate = $this->getDefaultTaxRate();

        $lineCalculation = $this->calculateLine(
            $priceMinor,
            1,
            $discountMinor,
            'fixed',
            $taxRate
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
            'treatment_id' => $treatmentId,
            'description' => Treatment::find($treatmentId)?->name ?? 'Treatment',
            'quantity' => 1,
            'unit_price_minor' => $priceMinor,
            'discount_minor' => $discountMinor,
            'discount_type' => 'fixed',
            'tax_rate' => $taxRate,
            'tax_minor' => $lineCalculation['tax_minor'],
            'total_minor' => $lineCalculation['total_minor'],
        ]);

        return $invoice;
    }
}
