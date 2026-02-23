# Treatment Plan Finance System - Implementation Plan

## Overview

This plan implements a comprehensive financial system centered around Treatment Plans, enabling:
- Treatment Plans with 3 item types: Services, Packages, Products
- Deposit collection on appointments
- Invoice generation from treatment plan items
- Deposit application to invoices
- Payment tracking and collection

**IMPORTANT**: This implementation integrates with the **existing Billing module** (`modules/Billing/`), which already has:
- `Invoice` model with statuses, payments, and accounting integration
- `InvoiceLine` model with service support
- `Payment` model with journal entries
- `TaxRate`, `InstallmentPlan`, `InstallmentSchedule` models

We will **extend** the existing module rather than create a new Finance module.

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            TREATMENT PLAN                                    │
│                         (Master Financial Record)                            │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐ │
│  │                      TREATMENT PLAN ITEMS                               │ │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐                     │ │
│  │  │   SERVICE   │  │   PACKAGE   │  │   PRODUCT   │                     │ │
│  │  │  Laser x6   │  │ Bundle Pack │  │ Skincare Kit│                     │ │
│  │  │  $600 total │  │ $500 total  │  │ $200 total  │                     │ │
│  │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘                     │ │
│  └─────────┼────────────────┼────────────────┼────────────────────────────┘ │
│            │                │                │                               │
│            ▼                ▼                ▼                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                         APPOINTMENTS                                     ││
│  │   [Apt #1] ──► [Apt #2] ──► [Apt #3] ──► ...                           ││
│  │      │            │                                                      ││
│  │      ▼            ▼                                                      ││
│  │  [Deposit]    [Deposit]     (Optional deposits on booking)              ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                           INVOICES                                       ││
│  │   ┌─────────────┐    ┌─────────────┐                                    ││
│  │   │ Invoice #1  │    │ Invoice #2  │                                    ││
│  │   │ $400        │    │ $300        │                                    ││
│  │   │             │    │             │                                    ││
│  │   │ Applied:    │    │ Applied:    │                                    ││
│  │   │ -$200 dep   │    │ -$100 dep   │                                    ││
│  │   │             │    │             │                                    ││
│  │   │ Paid: $200  │    │ Due: $200   │                                    ││
│  │   └─────────────┘    └─────────────┘                                    ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## Phase 1: Database Schema & Migrations

### 1.1 Extend Existing Billing Module

We will add to the existing `modules/Billing/` structure:

```
modules/Billing/
├── Database/
│   └── Migrations/
│       ├── (existing) 2024_01_01_000001_create_tax_rates_table.php
│       ├── (existing) 2024_01_01_000002_create_invoices_table.php
│       ├── (existing) 2024_01_01_000003_create_invoice_lines_table.php
│       ├── (existing) 2024_01_01_000004_create_payments_table.php
│       ├── (NEW) 2024_02_01_000001_create_deposits_table.php
│       ├── (NEW) 2024_02_01_000002_create_deposit_applications_table.php
│       ├── (NEW) 2024_02_01_000003_add_treatment_plan_to_invoices.php
│       └── (NEW) 2024_02_01_000004_add_itemable_to_invoice_lines.php
├── Models/
│   ├── (existing) Invoice.php - Add treatment_plan relationship
│   ├── (existing) InvoiceLine.php - Add polymorphic itemable
│   ├── (existing) Payment.php
│   ├── (NEW) Deposit.php
│   └── (NEW) DepositApplication.php
├── Services/
│   ├── (existing) InvoiceCalculationService.php
│   ├── (existing) AccountingIntegrationService.php
│   └── (NEW) DepositService.php
└── Filament/
    └── Resources/
        ├── (existing) InvoiceResource.php - Add deposit application
        └── (NEW) DepositResource.php
```

### 1.2 SIMPLIFIED APPROACH: Deposits = Unassigned Payments

**Key Insight**: Deposits are simply payments that haven't been assigned to an invoice yet.

Instead of creating separate deposits tables, we:
1. Make `invoice_id` nullable on payments table
2. Add `patient_id`, `branch_id`, `treatment_plan_id`, `appointment_id` to payments
3. Payments without `invoice_id` = Unassigned Payments (Deposits)
4. When creating invoice, assign existing unassigned payments to it

**Benefits**:
- Simpler data model
- Single payment tracking system
- Consistent accounting entries
- Easy to query available deposits per patient/plan

### 1.3 Migration: invoices (EXISTING - updated)

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('treatment_plan_id')->nullable();
    $table->uuid('patient_id');
    $table->uuid('branch_id');

    $table->string('code')->unique(); // INV-00001

    // Totals
    $table->integer('subtotal_minor')->default(0);
    $table->integer('discount_minor')->default(0);
    $table->integer('tax_minor')->default(0);
    $table->integer('total_minor')->default(0);

    // Payment tracking
    $table->integer('deposits_applied_minor')->default(0);
    $table->integer('payments_received_minor')->default(0);
    $table->integer('balance_minor')->default(0);

    // Status & dates
    $table->string('status')->default('draft');
    // draft, pending, partial, paid, overdue, cancelled, refunded

    $table->date('issue_date')->nullable();
    $table->date('due_date')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();

    $table->uuid('created_by_user_id')->nullable();
    $table->uuid('cancelled_by_user_id')->nullable();

    $table->text('notes')->nullable();
    $table->text('terms')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('set null');
    $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
});
```

### 1.3 Migration: invoice_items

```php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('invoice_id');
    $table->uuid('treatment_plan_item_id')->nullable();
    $table->uuid('appointment_id')->nullable();

    // Polymorphic reference to actual item
    $table->string('itemable_type'); // Service, Package, Product
    $table->uuid('itemable_id');

    $table->string('item_type'); // service, package, product
    $table->string('description');

    $table->integer('quantity')->default(1);
    $table->integer('unit_price_minor')->default(0);
    $table->integer('discount_minor')->default(0);
    $table->integer('tax_minor')->default(0);
    $table->integer('total_minor')->default(0);

    $table->uuid('package_subscription_id')->nullable(); // If from package

    $table->integer('sort_order')->default(0);
    $table->text('notes')->nullable();

    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
    $table->foreign('treatment_plan_item_id')->references('id')->on('treatment_plan_items')->onDelete('set null');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
});
```

### 1.4 Migration: deposits

```php
Schema::create('deposits', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('patient_id');
    $table->uuid('branch_id');
    $table->uuid('treatment_plan_id')->nullable();
    $table->uuid('appointment_id')->nullable();

    $table->string('code')->unique(); // DEP-00001

    $table->integer('amount_minor');
    $table->string('method'); // cash, card, bank_transfer, online
    $table->string('reference')->nullable();

    $table->string('status')->default('collected');
    // pending, collected, applied, partially_applied, refunded, cancelled

    $table->integer('applied_amount_minor')->default(0);
    $table->integer('remaining_amount_minor')->default(0);

    $table->uuid('collected_by_user_id')->nullable();
    $table->timestamp('collected_at')->nullable();

    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
    $table->foreign('treatment_plan_id')->references('id')->on('treatment_plans')->onDelete('set null');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
});
```

### 1.5 Migration: deposit_applications (pivot for applying deposits to invoices)

```php
Schema::create('deposit_applications', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('deposit_id');
    $table->uuid('invoice_id');

    $table->integer('amount_minor');

    $table->uuid('applied_by_user_id')->nullable();
    $table->timestamp('applied_at');

    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('deposit_id')->references('id')->on('deposits')->onDelete('cascade');
    $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
});
```

### 1.6 Migration: payments

```php
Schema::create('payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('invoice_id');
    $table->uuid('patient_id');
    $table->uuid('branch_id');

    $table->string('code')->unique(); // PAY-00001

    $table->integer('amount_minor');
    $table->string('method'); // cash, card, bank_transfer, online, cheque
    $table->string('reference')->nullable();

    $table->string('status')->default('completed');
    // pending, completed, failed, refunded

    $table->uuid('received_by_user_id')->nullable();
    $table->timestamp('paid_at');

    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
    $table->foreign('patient_id')->references('id')->on('patients')->onDelete('cascade');
    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
});
```

### 1.7 Migration: Update treatment_plan_items

```php
Schema::table('treatment_plan_items', function (Blueprint $table) {
    // Add item type if not exists
    $table->string('item_type')->default('service')->after('treatment_plan_id');
    // service, package, product

    // Polymorphic columns
    $table->string('itemable_type')->nullable()->after('item_type');
    $table->uuid('itemable_id')->nullable()->after('itemable_type');

    // Quantity tracking
    $table->integer('quantity')->default(1)->after('itemable_id');
    $table->integer('completed_quantity')->default(0);
    $table->integer('invoiced_quantity')->default(0);

    // For packages
    $table->uuid('package_subscription_id')->nullable();

    // For products
    $table->boolean('is_delivered')->default(false);
    $table->timestamp('delivered_at')->nullable();
});
```

### 1.8 Migration: Update treatment_plans (financial tracking)

```php
Schema::table('treatment_plans', function (Blueprint $table) {
    // Financial totals (cached for performance)
    $table->integer('total_value_minor')->default(0);
    $table->integer('total_deposits_minor')->default(0);
    $table->integer('total_invoiced_minor')->default(0);
    $table->integer('total_paid_minor')->default(0);
    $table->integer('balance_minor')->default(0);
});
```

### 1.9 Migration: Update appointments (deposit tracking)

```php
Schema::table('appointments', function (Blueprint $table) {
    $table->uuid('deposit_id')->nullable();
    $table->integer('deposit_amount_minor')->default(0);

    $table->string('payment_status')->default('unpaid');
    // unpaid, deposit_paid, invoiced, paid, package

    $table->foreign('deposit_id')->references('id')->on('deposits')->onDelete('set null');
});
```

---

## Phase 2: Models & Relationships

### 2.1 Invoice Model

```php
// modules/Finance/Models/Invoice.php

class Invoice extends BaseModel
{
    use HasTenancy, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'invoice';
    protected string $sequenceColumn = 'code';

    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_PAID = 'paid';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'tenant_id', 'treatment_plan_id', 'patient_id', 'branch_id',
        'code', 'subtotal_minor', 'discount_minor', 'tax_minor', 'total_minor',
        'deposits_applied_minor', 'payments_received_minor', 'balance_minor',
        'status', 'issue_date', 'due_date', 'paid_at', 'cancelled_at',
        'created_by_user_id', 'cancelled_by_user_id', 'notes', 'terms',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function treatmentPlan(): BelongsTo
    public function patient(): BelongsTo
    public function branch(): BelongsTo
    public function items(): HasMany
    public function payments(): HasMany
    public function depositApplications(): HasMany
    public function appliedDeposits(): BelongsToMany // through deposit_applications

    // Accessors
    public function getFormattedTotalAttribute(): string
    public function getFormattedBalanceAttribute(): string
    public function getIsPaidAttribute(): bool
    public function getIsOverdueAttribute(): bool

    // Methods
    public function calculateTotals(): void
    public function applyDeposit(Deposit $deposit, int $amount): void
    public function recordPayment(int $amount, string $method, ?string $reference = null): Payment
    public function markAsPaid(): void
    public function cancel(?string $reason = null): void
}
```

### 2.2 InvoiceItem Model

```php
// modules/Finance/Models/InvoiceItem.php

class InvoiceItem extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id', 'invoice_id', 'treatment_plan_item_id', 'appointment_id',
        'itemable_type', 'itemable_id', 'item_type', 'description',
        'quantity', 'unit_price_minor', 'discount_minor', 'tax_minor', 'total_minor',
        'package_subscription_id', 'sort_order', 'notes',
    ];

    // Relationships
    public function invoice(): BelongsTo
    public function treatmentPlanItem(): BelongsTo
    public function appointment(): BelongsTo
    public function itemable(): MorphTo
    public function packageSubscription(): BelongsTo

    // Methods
    public function calculateTotal(): void
}
```

### 2.3 Deposit Model

```php
// modules/Finance/Models/Deposit.php

class Deposit extends BaseModel
{
    use HasTenancy, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'deposit';
    protected string $sequenceColumn = 'code';

    const STATUS_PENDING = 'pending';
    const STATUS_COLLECTED = 'collected';
    const STATUS_APPLIED = 'applied';
    const STATUS_PARTIALLY_APPLIED = 'partially_applied';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE = 'online';

    protected $fillable = [
        'tenant_id', 'patient_id', 'branch_id', 'treatment_plan_id', 'appointment_id',
        'code', 'amount_minor', 'method', 'reference',
        'status', 'applied_amount_minor', 'remaining_amount_minor',
        'collected_by_user_id', 'collected_at', 'notes',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
    ];

    // Relationships
    public function patient(): BelongsTo
    public function branch(): BelongsTo
    public function treatmentPlan(): BelongsTo
    public function appointment(): BelongsTo
    public function collectedBy(): BelongsTo
    public function applications(): HasMany // DepositApplication
    public function invoices(): BelongsToMany // through deposit_applications

    // Accessors
    public function getFormattedAmountAttribute(): string
    public function getIsFullyAppliedAttribute(): bool
    public function getAvailableAmountAttribute(): int

    // Methods
    public function applyToInvoice(Invoice $invoice, int $amount): DepositApplication
    public function refund(?string $reason = null): void

    // Boot
    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Deposit $deposit) {
            $deposit->remaining_amount_minor = $deposit->amount_minor;
            $deposit->collected_at = $deposit->collected_at ?? now();
        });
    }
}
```

### 2.4 Payment Model

```php
// modules/Finance/Models/Payment.php

class Payment extends BaseModel
{
    use HasTenancy, HasSequence, SoftDeletes;

    protected string $sequenceCode = 'payment';
    protected string $sequenceColumn = 'code';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE = 'online';
    const METHOD_CHEQUE = 'cheque';

    protected $fillable = [
        'tenant_id', 'invoice_id', 'patient_id', 'branch_id',
        'code', 'amount_minor', 'method', 'reference',
        'status', 'received_by_user_id', 'paid_at', 'notes',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // Relationships
    public function invoice(): BelongsTo
    public function patient(): BelongsTo
    public function branch(): BelongsTo
    public function receivedBy(): BelongsTo

    // Boot
    protected static function booted(): void
    {
        parent::booted();

        static::created(function (Payment $payment) {
            // Update invoice totals
            $payment->invoice->payments_received_minor += $payment->amount_minor;
            $payment->invoice->balance_minor = $payment->invoice->total_minor
                - $payment->invoice->deposits_applied_minor
                - $payment->invoice->payments_received_minor;

            if ($payment->invoice->balance_minor <= 0) {
                $payment->invoice->status = Invoice::STATUS_PAID;
                $payment->invoice->paid_at = now();
            } else {
                $payment->invoice->status = Invoice::STATUS_PARTIAL;
            }

            $payment->invoice->save();

            // Update treatment plan totals
            if ($payment->invoice->treatmentPlan) {
                $payment->invoice->treatmentPlan->recalculateFinancials();
            }
        });
    }
}
```

### 2.5 DepositApplication Model (Pivot)

```php
// modules/Finance/Models/DepositApplication.php

class DepositApplication extends BaseModel
{
    use HasTenancy;

    protected $fillable = [
        'tenant_id', 'deposit_id', 'invoice_id',
        'amount_minor', 'applied_by_user_id', 'applied_at',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
    ];

    // Relationships
    public function deposit(): BelongsTo
    public function invoice(): BelongsTo
    public function appliedBy(): BelongsTo

    // Boot
    protected static function booted(): void
    {
        parent::booted();

        static::created(function (DepositApplication $application) {
            // Update deposit
            $deposit = $application->deposit;
            $deposit->applied_amount_minor += $application->amount_minor;
            $deposit->remaining_amount_minor = $deposit->amount_minor - $deposit->applied_amount_minor;

            if ($deposit->remaining_amount_minor <= 0) {
                $deposit->status = Deposit::STATUS_APPLIED;
            } else {
                $deposit->status = Deposit::STATUS_PARTIALLY_APPLIED;
            }
            $deposit->save();

            // Update invoice
            $invoice = $application->invoice;
            $invoice->deposits_applied_minor += $application->amount_minor;
            $invoice->balance_minor = $invoice->total_minor
                - $invoice->deposits_applied_minor
                - $invoice->payments_received_minor;
            $invoice->save();
        });
    }
}
```

### 2.6 Update TreatmentPlan Model

```php
// Add to modules/TreatmentPlans/Models/TreatmentPlan.php

// New relationships
public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class);
}

public function deposits(): HasMany
{
    return $this->hasMany(Deposit::class);
}

// Financial methods
public function recalculateFinancials(): void
{
    $this->total_value_minor = $this->items->sum('total_minor');
    $this->total_deposits_minor = $this->deposits()->sum('amount_minor');
    $this->total_invoiced_minor = $this->invoices()
        ->whereNotIn('status', ['cancelled', 'draft'])
        ->sum('total_minor');
    $this->total_paid_minor = $this->invoices()
        ->whereNotIn('status', ['cancelled', 'draft'])
        ->sum(\DB::raw('deposits_applied_minor + payments_received_minor'));
    $this->balance_minor = $this->total_value_minor - $this->total_paid_minor;
    $this->save();
}

public function getAvailableDepositsAttribute(): Collection
{
    return $this->deposits()
        ->where('status', '!=', Deposit::STATUS_APPLIED)
        ->where('remaining_amount_minor', '>', 0)
        ->get();
}

public function getFinancialSummaryAttribute(): array
{
    return [
        'total_value' => $this->total_value_minor / 100,
        'total_deposits' => $this->total_deposits_minor / 100,
        'total_invoiced' => $this->total_invoiced_minor / 100,
        'total_paid' => $this->total_paid_minor / 100,
        'balance' => $this->balance_minor / 100,
        'available_deposits' => $this->available_deposits->sum('remaining_amount_minor') / 100,
    ];
}
```

### 2.7 Update TreatmentPlanItem Model

```php
// Add to modules/TreatmentPlans/Models/TreatmentPlanItem.php

const TYPE_SERVICE = 'service';
const TYPE_PACKAGE = 'package';
const TYPE_PRODUCT = 'product';

protected $fillable = [
    // ... existing fields
    'item_type', 'itemable_type', 'itemable_id',
    'quantity', 'completed_quantity', 'invoiced_quantity',
    'package_subscription_id', 'is_delivered', 'delivered_at',
];

// Polymorphic relationship
public function itemable(): MorphTo
{
    return $this->morphTo();
}

public function packageSubscription(): BelongsTo
{
    return $this->belongsTo(PackageSubscription::class);
}

public function invoiceItems(): HasMany
{
    return $this->hasMany(InvoiceItem::class);
}

// Accessors
public function getRemainingQuantityAttribute(): int
{
    return $this->quantity - $this->completed_quantity;
}

public function getUninvoicedQuantityAttribute(): int
{
    return $this->completed_quantity - $this->invoiced_quantity;
}

public function getCanBeInvoicedAttribute(): bool
{
    if ($this->item_type === self::TYPE_PRODUCT) {
        return $this->is_delivered && $this->invoiced_quantity < $this->quantity;
    }
    return $this->uninvoiced_quantity > 0;
}
```

---

## Phase 3: Services (Business Logic)

### 3.1 InvoiceService

```php
// modules/Finance/Services/InvoiceService.php

class InvoiceService
{
    /**
     * Create invoice from treatment plan items
     */
    public function createFromTreatmentPlan(
        TreatmentPlan $plan,
        array $itemsToInvoice, // [{plan_item_id, quantity, appointments: []}]
        ?array $depositsToApply = [],
        ?array $options = []
    ): Invoice {
        return \DB::transaction(function () use ($plan, $itemsToInvoice, $depositsToApply, $options) {
            // Create invoice
            $invoice = Invoice::create([
                'treatment_plan_id' => $plan->id,
                'patient_id' => $plan->patient_id,
                'branch_id' => $plan->branch_id,
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => $options['issue_date'] ?? today(),
                'due_date' => $options['due_date'] ?? today()->addDays(30),
                'created_by_user_id' => auth()->id(),
            ]);

            // Add items
            foreach ($itemsToInvoice as $itemData) {
                $planItem = TreatmentPlanItem::find($itemData['plan_item_id']);
                $quantity = $itemData['quantity'] ?? 1;

                $invoiceItem = $invoice->items()->create([
                    'treatment_plan_item_id' => $planItem->id,
                    'itemable_type' => $planItem->itemable_type,
                    'itemable_id' => $planItem->itemable_id,
                    'item_type' => $planItem->item_type,
                    'description' => $this->getItemDescription($planItem),
                    'quantity' => $quantity,
                    'unit_price_minor' => $planItem->unit_price_minor,
                    'discount_minor' => $planItem->discount_minor / $planItem->quantity * $quantity,
                    'tax_minor' => $this->calculateTax($planItem, $quantity),
                ]);

                $invoiceItem->calculateTotal();

                // Link appointments if provided
                if (!empty($itemData['appointments'])) {
                    foreach ($itemData['appointments'] as $appointmentId) {
                        // Could create separate invoice items per appointment
                        // or just link them for reference
                    }
                }

                // Update plan item invoiced quantity
                $planItem->increment('invoiced_quantity', $quantity);
            }

            // Calculate invoice totals
            $invoice->calculateTotals();

            // Apply deposits
            foreach ($depositsToApply as $depositData) {
                $deposit = Deposit::find($depositData['deposit_id']);
                $amount = min($depositData['amount'], $deposit->remaining_amount_minor);

                $invoice->applyDeposit($deposit, $amount);
            }

            // Update plan financials
            $plan->recalculateFinancials();

            return $invoice->fresh(['items', 'depositApplications']);
        });
    }

    /**
     * Create standalone invoice (not from treatment plan)
     */
    public function createStandalone(
        Patient $patient,
        Branch $branch,
        array $items,
        ?array $options = []
    ): Invoice {
        // Similar logic without treatment plan
    }

    /**
     * Finalize draft invoice
     */
    public function finalize(Invoice $invoice): Invoice
    {
        if ($invoice->status !== Invoice::STATUS_DRAFT) {
            throw new \Exception('Only draft invoices can be finalized');
        }

        $invoice->status = Invoice::STATUS_PENDING;
        $invoice->issue_date = $invoice->issue_date ?? today();
        $invoice->save();

        return $invoice;
    }

    /**
     * Cancel invoice
     */
    public function cancel(Invoice $invoice, ?string $reason = null): Invoice
    {
        return \DB::transaction(function () use ($invoice, $reason) {
            // Reverse deposit applications
            foreach ($invoice->depositApplications as $application) {
                $deposit = $application->deposit;
                $deposit->applied_amount_minor -= $application->amount_minor;
                $deposit->remaining_amount_minor += $application->amount_minor;
                $deposit->status = $deposit->remaining_amount_minor >= $deposit->amount_minor
                    ? Deposit::STATUS_COLLECTED
                    : Deposit::STATUS_PARTIALLY_APPLIED;
                $deposit->save();

                $application->delete();
            }

            // Reverse invoiced quantities on plan items
            foreach ($invoice->items as $item) {
                if ($item->treatment_plan_item_id) {
                    $item->treatmentPlanItem->decrement('invoiced_quantity', $item->quantity);
                }
            }

            $invoice->status = Invoice::STATUS_CANCELLED;
            $invoice->cancelled_at = now();
            $invoice->cancelled_by_user_id = auth()->id();
            $invoice->notes = $invoice->notes . "\nCancellation reason: " . $reason;
            $invoice->save();

            // Update plan financials
            if ($invoice->treatmentPlan) {
                $invoice->treatmentPlan->recalculateFinancials();
            }

            return $invoice;
        });
    }

    protected function getItemDescription(TreatmentPlanItem $item): string
    {
        return $item->itemable?->translated_name ?? $item->itemable?->name ?? 'Item';
    }

    protected function calculateTax(TreatmentPlanItem $item, int $quantity): int
    {
        $taxRate = config('finance.tax_rate', 0.14); // 14% default
        $subtotal = $item->unit_price_minor * $quantity;
        return (int) round($subtotal * $taxRate);
    }
}
```

### 3.2 DepositService

```php
// modules/Finance/Services/DepositService.php

class DepositService
{
    /**
     * Collect deposit on appointment booking
     */
    public function collectOnAppointment(
        Appointment $appointment,
        int $amountMinor,
        string $method,
        ?string $reference = null
    ): Deposit {
        $deposit = Deposit::create([
            'patient_id' => $appointment->patient_id,
            'branch_id' => $appointment->branch_id,
            'treatment_plan_id' => $appointment->treatmentPlanItem?->treatment_plan_id,
            'appointment_id' => $appointment->id,
            'amount_minor' => $amountMinor,
            'method' => $method,
            'reference' => $reference,
            'status' => Deposit::STATUS_COLLECTED,
            'collected_by_user_id' => auth()->id(),
            'collected_at' => now(),
        ]);

        // Update appointment
        $appointment->deposit_id = $deposit->id;
        $appointment->deposit_amount_minor = $amountMinor;
        $appointment->payment_status = 'deposit_paid';
        $appointment->save();

        // Update treatment plan financials
        if ($deposit->treatmentPlan) {
            $deposit->treatmentPlan->recalculateFinancials();
        }

        return $deposit;
    }

    /**
     * Collect standalone deposit for treatment plan
     */
    public function collectForPlan(
        TreatmentPlan $plan,
        int $amountMinor,
        string $method,
        ?string $reference = null
    ): Deposit {
        $deposit = Deposit::create([
            'patient_id' => $plan->patient_id,
            'branch_id' => $plan->branch_id,
            'treatment_plan_id' => $plan->id,
            'amount_minor' => $amountMinor,
            'method' => $method,
            'reference' => $reference,
            'status' => Deposit::STATUS_COLLECTED,
            'collected_by_user_id' => auth()->id(),
            'collected_at' => now(),
        ]);

        $plan->recalculateFinancials();

        return $deposit;
    }

    /**
     * Refund a deposit
     */
    public function refund(Deposit $deposit, ?string $reason = null): Deposit
    {
        if ($deposit->applied_amount_minor > 0) {
            throw new \Exception('Cannot refund a deposit that has been applied to invoices');
        }

        $deposit->status = Deposit::STATUS_REFUNDED;
        $deposit->notes = $deposit->notes . "\nRefund reason: " . $reason;
        $deposit->save();

        // Update appointment if linked
        if ($deposit->appointment) {
            $deposit->appointment->deposit_id = null;
            $deposit->appointment->deposit_amount_minor = 0;
            $deposit->appointment->payment_status = 'unpaid';
            $deposit->appointment->save();
        }

        // Update plan financials
        if ($deposit->treatmentPlan) {
            $deposit->treatmentPlan->recalculateFinancials();
        }

        return $deposit;
    }

    /**
     * Get available deposits for a treatment plan
     */
    public function getAvailableForPlan(TreatmentPlan $plan): Collection
    {
        return Deposit::where('treatment_plan_id', $plan->id)
            ->where('remaining_amount_minor', '>', 0)
            ->whereIn('status', [Deposit::STATUS_COLLECTED, Deposit::STATUS_PARTIALLY_APPLIED])
            ->orderBy('collected_at')
            ->get();
    }
}
```

### 3.3 PaymentService

```php
// modules/Finance/Services/PaymentService.php

class PaymentService
{
    /**
     * Record payment for invoice
     */
    public function recordPayment(
        Invoice $invoice,
        int $amountMinor,
        string $method,
        ?string $reference = null
    ): Payment {
        if ($amountMinor > $invoice->balance_minor) {
            throw new \Exception('Payment amount exceeds invoice balance');
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'patient_id' => $invoice->patient_id,
            'branch_id' => $invoice->branch_id,
            'amount_minor' => $amountMinor,
            'method' => $method,
            'reference' => $reference,
            'status' => Payment::STATUS_COMPLETED,
            'received_by_user_id' => auth()->id(),
            'paid_at' => now(),
        ]);

        // Invoice and plan totals are updated in Payment::booted()

        return $payment;
    }

    /**
     * Process checkout with multiple payment methods
     */
    public function processCheckout(
        Invoice $invoice,
        array $payments // [{amount, method, reference}]
    ): array {
        $createdPayments = [];

        \DB::transaction(function () use ($invoice, $payments, &$createdPayments) {
            foreach ($payments as $paymentData) {
                $createdPayments[] = $this->recordPayment(
                    $invoice,
                    $paymentData['amount'],
                    $paymentData['method'],
                    $paymentData['reference'] ?? null
                );
            }
        });

        return $createdPayments;
    }

    /**
     * Refund a payment
     */
    public function refund(Payment $payment, ?string $reason = null): Payment
    {
        return \DB::transaction(function () use ($payment, $reason) {
            $invoice = $payment->invoice;

            // Reverse payment amounts
            $invoice->payments_received_minor -= $payment->amount_minor;
            $invoice->balance_minor += $payment->amount_minor;

            if ($invoice->balance_minor > 0) {
                $invoice->status = $invoice->payments_received_minor > 0
                    ? Invoice::STATUS_PARTIAL
                    : Invoice::STATUS_PENDING;
                $invoice->paid_at = null;
            }
            $invoice->save();

            // Mark payment as refunded
            $payment->status = Payment::STATUS_REFUNDED;
            $payment->notes = $payment->notes . "\nRefund reason: " . $reason;
            $payment->save();

            // Update plan financials
            if ($invoice->treatmentPlan) {
                $invoice->treatmentPlan->recalculateFinancials();
            }

            return $payment;
        });
    }
}
```

---

## Phase 4: Filament Resources

### 4.1 InvoiceResource

```php
// modules/Finance/Filament/Resources/InvoiceResource.php

Key features:
- List view with filters (status, date range, patient, plan)
- Create from treatment plan (modal to select items)
- View page with:
  - Invoice details
  - Line items table
  - Deposits applied
  - Payments received
  - Actions: Finalize, Apply Deposit, Record Payment, Print, Cancel
- Edit (only for drafts)
```

### 4.2 DepositResource

```php
// modules/Finance/Filament/Resources/DepositResource.php

Key features:
- List view with filters (status, patient, plan)
- Create standalone deposit
- View deposit applications
- Actions: Apply to Invoice, Refund
```

### 4.3 PaymentResource

```php
// modules/Finance/Filament/Resources/PaymentResource.php

Key features:
- List view with filters (date, method, patient)
- Linked to invoices
- Actions: Refund
```

### 4.4 Update TreatmentPlanResource

```php
Add to ViewTreatmentPlan page:
- Financial Summary Card
  - Total Value, Deposits, Invoiced, Paid, Balance
- Deposits Tab
  - List of deposits
  - Collect Deposit action
- Invoices Tab
  - List of invoices
  - Generate Invoice action
- Actions:
  - Generate Invoice (modal to select items)
  - Collect Deposit (modal)
```

---

## Phase 5: Integration with Booking Flow

### 5.1 Update CreateBooking Page

```php
// Add deposit collection option when booking from treatment plan

After appointment is created:
1. Show deposit collection modal (optional)
2. If deposit collected:
   - Create Deposit record
   - Link to appointment and treatment plan
   - Update appointment.payment_status
```

### 5.2 Checkout Flow (New Page)

```php
// modules/Finance/Filament/Pages/Checkout.php

Features:
- Select patient
- Show today's completed appointments
- Show pending invoices
- Quick actions:
  - Generate invoice for appointments
  - Apply deposits
  - Collect payment
  - Print receipt
```

---

## Phase 6: UI Components

### 6.1 Treatment Plan Financial Summary Widget

```blade
{{-- Widget showing plan financial status --}}
<div class="grid grid-cols-5 gap-4">
    <div class="stat">
        <div class="stat-title">Total Value</div>
        <div class="stat-value">{{ $plan->formatted_total_value }}</div>
    </div>
    <div class="stat">
        <div class="stat-title">Deposits</div>
        <div class="stat-value text-blue-600">{{ $plan->formatted_deposits }}</div>
    </div>
    <div class="stat">
        <div class="stat-title">Invoiced</div>
        <div class="stat-value">{{ $plan->formatted_invoiced }}</div>
    </div>
    <div class="stat">
        <div class="stat-title">Paid</div>
        <div class="stat-value text-green-600">{{ $plan->formatted_paid }}</div>
    </div>
    <div class="stat">
        <div class="stat-title">Balance</div>
        <div class="stat-value text-red-600">{{ $plan->formatted_balance }}</div>
    </div>
</div>
```

### 6.2 Invoice Generation Modal

```php
// Filament Action for generating invoice from plan

Action::make('generate_invoice')
    ->form([
        // Select items to invoice
        CheckboxList::make('items')
            ->options(fn ($record) => $record->items
                ->where('can_be_invoiced', true)
                ->mapWithKeys(fn ($item) => [
                    $item->id => "{$item->itemable->name} ({$item->uninvoiced_quantity} available)"
                ])),

        // Select deposits to apply
        CheckboxList::make('deposits')
            ->options(fn ($record) => $record->available_deposits
                ->mapWithKeys(fn ($dep) => [
                    $dep->id => "Deposit {$dep->code} - {$dep->formatted_remaining}"
                ])),

        // Invoice options
        DatePicker::make('due_date')
            ->default(today()->addDays(30)),
    ])
    ->action(function ($record, array $data) {
        app(InvoiceService::class)->createFromTreatmentPlan(
            $record,
            $data['items'],
            $data['deposits'],
            ['due_date' => $data['due_date']]
        );
    });
```

---

## Phase 7: Translations

### 7.1 English (modules/Finance/Lang/en/finance.php)

```php
return [
    'navigation' => [
        'finance' => 'Finance',
        'invoices' => 'Invoices',
        'deposits' => 'Deposits',
        'payments' => 'Payments',
    ],

    'invoice' => [
        'model_label' => 'Invoice',
        'plural_label' => 'Invoices',
        'statuses' => [
            'draft' => 'Draft',
            'pending' => 'Pending',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
            'overdue' => 'Overdue',
            'cancelled' => 'Cancelled',
        ],
        'fields' => [
            'code' => 'Invoice #',
            'patient' => 'Patient',
            'treatment_plan' => 'Treatment Plan',
            'issue_date' => 'Issue Date',
            'due_date' => 'Due Date',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'tax' => 'Tax',
            'total' => 'Total',
            'deposits_applied' => 'Deposits Applied',
            'payments_received' => 'Payments Received',
            'balance' => 'Balance Due',
        ],
        'actions' => [
            'generate' => 'Generate Invoice',
            'finalize' => 'Finalize',
            'apply_deposit' => 'Apply Deposit',
            'record_payment' => 'Record Payment',
            'print' => 'Print',
            'cancel' => 'Cancel Invoice',
        ],
    ],

    'deposit' => [
        'model_label' => 'Deposit',
        'plural_label' => 'Deposits',
        'statuses' => [
            'pending' => 'Pending',
            'collected' => 'Collected',
            'applied' => 'Applied',
            'partially_applied' => 'Partially Applied',
            'refunded' => 'Refunded',
        ],
        'methods' => [
            'cash' => 'Cash',
            'card' => 'Card',
            'bank_transfer' => 'Bank Transfer',
            'online' => 'Online',
        ],
        'fields' => [
            'code' => 'Deposit #',
            'amount' => 'Amount',
            'method' => 'Method',
            'reference' => 'Reference',
            'collected_at' => 'Collected At',
            'remaining' => 'Remaining',
        ],
        'actions' => [
            'collect' => 'Collect Deposit',
            'apply' => 'Apply to Invoice',
            'refund' => 'Refund',
        ],
    ],

    'payment' => [
        'model_label' => 'Payment',
        'plural_label' => 'Payments',
        // ... similar structure
    ],
];
```

---

## Phase 8: Implementation Steps

### Step 1: Create Finance Module (Day 1)
- [ ] Create module structure
- [ ] Create migrations
- [ ] Run migrations
- [ ] Create models with relationships

### Step 2: Update Treatment Plan Models (Day 1)
- [ ] Add financial fields to TreatmentPlan
- [ ] Add item types to TreatmentPlanItem
- [ ] Add polymorphic relationship
- [ ] Run migrations

### Step 3: Create Services (Day 2)
- [ ] InvoiceService
- [ ] DepositService
- [ ] PaymentService
- [ ] Unit tests for services

### Step 4: Create Filament Resources (Day 2-3)
- [ ] InvoiceResource (List, Create, View, Edit)
- [ ] DepositResource
- [ ] PaymentResource
- [ ] Add financial tab to TreatmentPlanResource

### Step 5: Integration (Day 3)
- [ ] Update CreateBooking for deposit collection
- [ ] Create Checkout page
- [ ] Add invoice generation action to plans

### Step 6: Translations (Day 4)
- [ ] English translations
- [ ] Arabic translations

### Step 7: Testing & Polish (Day 4)
- [ ] Test complete flow
- [ ] Fix edge cases
- [ ] Add print templates
- [ ] Documentation

---

## Verification Checklist

### Treatment Plan
- [ ] Can create plan with service items
- [ ] Can create plan with package items
- [ ] Can create plan with product items
- [ ] Financial totals calculate correctly

### Deposits
- [ ] Can collect deposit on appointment booking
- [ ] Can collect standalone deposit for plan
- [ ] Deposit linked to plan correctly
- [ ] Can refund unused deposit

### Invoices
- [ ] Can generate invoice from plan items
- [ ] Can select which items to invoice
- [ ] Can apply deposits to invoice
- [ ] Balance calculates correctly
- [ ] Can finalize draft invoice
- [ ] Can cancel invoice (reverses everything)

### Payments
- [ ] Can record payment against invoice
- [ ] Invoice status updates correctly
- [ ] Plan financials update correctly
- [ ] Can refund payment

### Complete Flow
- [ ] Create treatment plan with mixed items
- [ ] Book appointment from plan
- [ ] Collect deposit on booking
- [ ] Complete appointment
- [ ] Generate invoice for completed services
- [ ] Apply deposit to invoice
- [ ] Collect remaining payment
- [ ] Verify all totals are correct
