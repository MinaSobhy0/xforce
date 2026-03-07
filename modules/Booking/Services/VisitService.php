<?php

namespace Modules\Booking\Services;

use Modules\Booking\Models\Visit;
use Modules\Booking\Models\Appointment;
use Modules\Booking\Models\SessionProduct;
use Modules\Patients\Models\Patient;
use Modules\Core\Models\Branch;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Packages\Models\Package;
use Modules\Packages\Models\PackageSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class VisitService
{

    /**
     * Create a new visit for a patient
     */
    public function createVisit(
        Patient $patient,
        ?Branch $branch = null,
        ?string $source = null,
        ?string $chiefComplaint = null,
        ?string $notes = null
    ): Visit {
        return Visit::create([
            'tenant_id' => $patient->tenant_id,
            'branch_id' => $branch?->id,
            'patient_id' => $patient->id,
            'check_in_at' => now(),
            'checked_in_by' => auth()->id(),
            'status' => Visit::STATUS_OPEN,
            'source' => $source ?? Visit::SOURCE_APPOINTMENT,
            'chief_complaint' => $chiefComplaint,
            'notes' => $notes,
        ]);
    }

    /**
     * Find an open visit for a patient (same day)
     */
    public function findOpenVisit(Patient $patient, ?Branch $branch = null): ?Visit
    {
        $query = Visit::query()
            ->where('patient_id', $patient->id)
            ->where('status', Visit::STATUS_OPEN)
            ->whereDate('check_in_at', today());

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        return $query->latest('check_in_at')->first();
    }

    /**
     * Find or create a visit for a patient
     */
    public function findOrCreateVisit(
        Patient $patient,
        ?Branch $branch = null,
        ?string $source = null,
        ?string $chiefComplaint = null
    ): Visit {
        $visit = $this->findOpenVisit($patient, $branch);

        if (!$visit) {
            $visit = $this->createVisit($patient, $branch, $source, $chiefComplaint);
        }

        return $visit;
    }

    /**
     * Add an appointment to a visit
     */
    public function addAppointment(Visit $visit, Appointment $appointment): void
    {
        $visit->addAppointment($appointment);
    }

    /**
     * Link a product to the current visit
     */
    public function linkProductToVisit(SessionProduct $product, Visit $visit): void
    {
        $product->update(['visit_id' => $visit->id]);
    }

    /**
     * Get visit summary with all items
     */
    public function getVisitSummary(Visit $visit): array
    {
        $visit->load([
            'appointments.service',
            'appointments.practitioner',
            'products.product',
            'patient',
        ]);

        $appointments = $visit->appointments->map(function ($apt) {
            return [
                'id' => $apt->id,
                'service_name' => $apt->service?->translated_name,
                'practitioner_name' => $apt->practitioner?->name,
                'status' => $apt->status,
                'status_label' => $apt->status_label,
                'price_minor' => $apt->price_minor,
                'net_price' => $apt->net_price,
                'is_package_session' => $apt->is_package_session,
                'is_completed' => $apt->status === Appointment::STATUS_COMPLETED,
                'is_open' => in_array($apt->status, [
                    Appointment::STATUS_SCHEDULED,
                    Appointment::STATUS_CONFIRMED,
                    Appointment::STATUS_CHECKED_IN,
                    Appointment::STATUS_IN_PROGRESS,
                ]),
            ];
        });

        $products = $visit->products->where('usage_type', 'sold')->map(function ($prod) {
            return [
                'id' => $prod->id,
                'product_name' => $prod->product?->translated_name ?? $prod->product?->name,
                'quantity' => $prod->quantity,
                'unit_price_minor' => $prod->unit_price_minor,
                'total_price_minor' => $prod->total_price_minor,
                'discount_minor' => $prod->discount_minor,
            ];
        });

        $servicesTotal = $appointments
            ->where('is_completed', true)
            ->where('is_package_session', false)
            ->sum('net_price');

        $packageSessionsTotal = $appointments
            ->where('is_completed', true)
            ->where('is_package_session', true)
            ->sum('net_price');

        $productsTotal = $products->sum('total_price_minor');

        return [
            'visit' => $visit,
            'appointments' => $appointments,
            'products' => $products,
            'open_appointments' => $appointments->where('is_open', true),
            'completed_appointments' => $appointments->where('is_completed', true),
            'totals' => [
                'services' => $servicesTotal,
                'package_deduction' => $packageSessionsTotal,
                'products' => $productsTotal,
                'grand_total' => $servicesTotal + $productsTotal,
            ],
        ];
    }

    /**
     * Process checkout for a visit
     *
     * @param Visit $visit
     * @param array $sessionActions Actions for each open session ['appointment_id' => 'complete|cancel|reschedule']
     * @return Invoice
     */
    public function checkout(Visit $visit, array $sessionActions = []): Invoice
    {
        return DB::transaction(function () use ($visit, $sessionActions) {
            // Process open sessions based on actions
            foreach ($sessionActions as $appointmentId => $action) {
                $appointment = Appointment::find($appointmentId);
                if (!$appointment) continue;

                switch ($action) {
                    case 'complete':
                        $appointment->complete();
                        break;
                    case 'cancel':
                        $appointment->cancel('Cancelled at checkout');
                        break;
                    case 'reschedule':
                        // Remove from this visit - will be added to next visit
                        $visit->appointments()->detach($appointmentId);
                        break;
                }
            }

            // Reload visit with updated data
            $visit->refresh();
            $visit->load(['appointments', 'products']);

            // Create invoice
            $invoice = $this->createInvoiceFromVisit($visit);

            // Mark visit as invoiced
            $visit->markInvoiced($invoice);

            return $invoice;
        });
    }

    /**
     * Create invoice from visit
     */
    protected function createInvoiceFromVisit(Visit $visit): Invoice
    {
        // Get completed appointments (excluding package sessions)
        $billableAppointments = $visit->appointments()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->where(function ($q) {
                $q->where('is_package_session', false)
                    ->orWhereNull('is_package_session');
            })
            ->with('service.category')
            ->get();

        // Get sold products
        $soldProducts = $visit->products()
            ->where('usage_type', 'sold')
            ->with('product')
            ->get();

        // Get pending packages
        $pendingPackages = $visit->pendingPackages;

        // Calculate totals
        $subtotalMinor = 0;
        $lines = [];
        $createdSubscriptions = [];

        // Add service lines
        foreach ($billableAppointments as $apt) {
            $lineTotal = $apt->net_price ?? $apt->price_minor ?? 0;
            $subtotalMinor += $lineTotal;

            // Get revenue account from service category
            $accountId = $apt->service?->category?->service_revenue_account_id;

            $lines[] = [
                'line_type' => 'service',
                'description' => $apt->service?->translated_name ?? 'Service',
                'quantity' => $apt->quantity ?? 1,
                'unit_price_minor' => $apt->price_minor,
                'discount_minor' => $apt->discount_minor ?? 0,
                'total_minor' => $lineTotal,
                'service_id' => $apt->service_id,
                'account_id' => $accountId,
                'appointment_id' => $apt->id,
            ];
        }

        // Add product lines
        foreach ($soldProducts as $prod) {
            $subtotalMinor += $prod->total_price_minor;

            // Get income account from product
            $accountId = $prod->product?->income_account_id;

            $lines[] = [
                'line_type' => 'product',
                'description' => $prod->product?->translated_name ?? $prod->product?->name ?? 'Product',
                'quantity' => $prod->quantity,
                'unit_price_minor' => $prod->unit_price_minor,
                'discount_minor' => $prod->discount_minor ?? 0,
                'total_minor' => $prod->total_price_minor,
                'product_id' => $prod->product_id,
                'account_id' => $accountId,
            ];

            // Mark product as invoiced
            $prod->markAsInvoiced();
        }

        // Add package lines and create subscriptions
        foreach ($pendingPackages as $package) {
            $priceMinor = $package->pivot->package_price_minor;
            $subtotalMinor += $priceMinor;

            // Create the package subscription (active immediately)
            $subscription = $this->createPackageSubscription($visit, $package);
            $createdSubscriptions[] = $subscription;

            $lines[] = [
                'line_type' => 'package',
                'description' => $package->translated_name ?? $package->name,
                'quantity' => 1,
                'unit_price_minor' => $priceMinor,
                'discount_minor' => 0,
                'total_minor' => $priceMinor,
                'package_subscription_id' => $subscription->id,
            ];
        }

        // Create invoice using InvoiceService
        $invoice = Invoice::create([
            'tenant_id' => $visit->tenant_id,
            'branch_id' => $visit->branch_id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => Invoice::STATUS_DRAFT,
            'subtotal_minor' => $subtotalMinor,
            'tax_minor' => 0, // Calculate if needed
            'discount_minor' => 0,
            'total_minor' => $subtotalMinor,
            'notes' => "Visit: {$visit->code}",
        ]);

        // Create invoice lines
        foreach ($lines as $lineData) {
            InvoiceLine::create([
                'tenant_id' => $visit->tenant_id,
                'invoice_id' => $invoice->id,
                'line_type' => $lineData['line_type'],
                'description' => $lineData['description'],
                'quantity' => $lineData['quantity'],
                'unit_price_minor' => $lineData['unit_price_minor'],
                'discount_minor' => $lineData['discount_minor'],
                'tax_minor' => 0,
                'total_minor' => $lineData['total_minor'],
                'service_id' => $lineData['service_id'] ?? null,
                'product_id' => $lineData['product_id'] ?? null,
                'account_id' => $lineData['account_id'] ?? null,
                'appointment_id' => $lineData['appointment_id'] ?? null,
                'package_subscription_id' => $lineData['package_subscription_id'] ?? null,
            ]);
        }

        // Link subscriptions to invoice
        foreach ($createdSubscriptions as $subscription) {
            $subscription->update(['invoice_id' => $invoice->id]);
        }

        // Clear pending packages from visit
        $visit->pendingPackages()->detach();

        // Issue invoice
        $invoice->issue();

        return $invoice;
    }

    /**
     * Create a package subscription for a visit
     */
    protected function createPackageSubscription(Visit $visit, Package $package): PackageSubscription
    {
        $priceMinor = $package->pivot->package_price_minor;
        $paymentOption = $package->pivot->payment_option ?? 'full';

        // Calculate deposit and balance
        $depositMinor = $priceMinor; // Default to full payment
        $balanceMinor = 0;

        if ($paymentOption === 'deposit') {
            $depositPercent = $package->min_deposit_percent ?? 100;
            $depositMinor = (int) ceil($priceMinor * $depositPercent / 100);
            $balanceMinor = $priceMinor - $depositMinor;
        }

        return PackageSubscription::create([
            'tenant_id' => $visit->tenant_id,
            'patient_id' => $visit->patient_id,
            'package_id' => $package->id,
            'branch_id' => $visit->branch_id,
            'package_price_minor' => $priceMinor,
            'deposit_paid_minor' => $depositMinor,
            'balance_remaining_minor' => $balanceMinor,
            'recognized_revenue_minor' => 0,
            'unrecognized_revenue_minor' => $priceMinor,
            'status' => PackageSubscription::STATUS_ACTIVE,
            'activation_rule' => PackageSubscription::ACTIVATION_IMMEDIATE,
            'purchased_at' => now(),
            'expires_at' => now()->addDays($package->validity_days ?? 365),
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Calculate visit total
     */
    public function calculateTotal(Visit $visit): int
    {
        return $visit->calculateTotal();
    }

    /**
     * Get all open visits (for reception dashboard)
     */
    public function getOpenVisits(?Branch $branch = null): Collection
    {
        $query = Visit::query()
            ->with(['patient', 'appointments.service', 'appointments.practitioner'])
            ->where('status', Visit::STATUS_OPEN)
            ->whereDate('check_in_at', today())
            ->orderBy('check_in_at');

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        return $query->get();
    }

    /**
     * Get patient visit history
     */
    public function getPatientVisitHistory(Patient $patient, int $limit = 10): Collection
    {
        return Visit::query()
            ->with(['appointments.service', 'invoice'])
            ->where('patient_id', $patient->id)
            ->whereIn('status', [Visit::STATUS_COMPLETED, Visit::STATUS_INVOICED])
            ->orderByDesc('check_in_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Add a package to a visit for purchase at checkout
     */
    public function addPackageToVisit(Visit $visit, Package $package, string $paymentOption = 'full'): void
    {
        $visit->addPendingPackage($package, $paymentOption);
    }

    /**
     * Remove a package from a visit
     */
    public function removePackageFromVisit(Visit $visit, int $packageId): void
    {
        $visit->pendingPackages()->detach($packageId);
    }

    /**
     * Get available packages for a patient to purchase
     */
    public function getAvailablePackagesForPatient(Patient $patient): Collection
    {
        return Package::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
