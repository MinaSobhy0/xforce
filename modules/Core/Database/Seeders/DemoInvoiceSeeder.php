<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Booking\Models\Appointment;
use Carbon\Carbon;

class DemoInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create invoices for completed appointments
        $completedAppointments = Appointment::where('status', Appointment::STATUS_COMPLETED)
            ->whereDoesntHave('invoice')
            ->with(['patient', 'treatment', 'branch'])
            ->get();

        $invoiceCount = 0;
        $paymentMethods = ['cash', 'card', 'bank_transfer', 'wallet'];

        foreach ($completedAppointments as $appointment) {
            $code = sprintf('INV-%06d', ++$invoiceCount);

            $subtotal = $appointment->price_minor;
            $discount = $appointment->discount_minor ?? 0;
            $taxRate = 14; // 14% VAT
            $taxable = $subtotal - $discount;
            $tax = (int) ($taxable * $taxRate / 100);
            $total = $taxable + $tax;

            // Randomly determine payment status
            $paymentStatus = ['paid', 'paid', 'paid', 'partially_paid', 'issued'][array_rand(['paid', 'paid', 'paid', 'partially_paid', 'issued'])];

            $paid = 0;
            if ($paymentStatus === 'paid') {
                $paid = $total;
            } elseif ($paymentStatus === 'partially_paid') {
                $paid = (int) ($total * rand(30, 70) / 100);
            }

            $invoice = Invoice::create([
                'code' => $code,
                'patient_id' => $appointment->patient_id,
                'branch_id' => $appointment->branch_id,
                'appointment_id' => $appointment->id,
                'type' => 'standard',
                'status' => $paymentStatus,
                'invoice_date' => $appointment->completed_at ?? $appointment->date,
                'due_date' => Carbon::parse($appointment->completed_at ?? $appointment->date)->addDays(7),
                'subtotal_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'total_minor' => $total,
                'paid_minor' => $paid,
                'remaining_minor' => $total - $paid,
                'notes' => null,
            ]);

            // Create invoice line
            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'treatment_id' => $appointment->treatment_id,
                'description' => is_array($appointment->treatment?->name)
                    ? ($appointment->treatment->name['en'] ?? 'Treatment')
                    : ($appointment->treatment?->name ?? 'Treatment'),
                'quantity' => 1,
                'unit_price_minor' => $subtotal,
                'discount_minor' => $discount,
                'tax_minor' => $tax,
                'total_minor' => $taxable + $tax,
            ]);

            // Create payment records for paid amounts
            if ($paid > 0) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'patient_id' => $appointment->patient_id,
                    'amount_minor' => $paid,
                    'method' => $paymentMethods[array_rand($paymentMethods)],
                    'paid_at' => $invoice->invoice_date,
                    'reference' => 'DEMO-' . rand(10000, 99999),
                    'notes' => 'Demo payment',
                ]);
            }
        }

        $this->command->info("Demo invoices seeded ({$invoiceCount} total).");
    }
}
