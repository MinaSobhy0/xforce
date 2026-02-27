# Session Checkout & Billing Workflow Implementation Plan

## Objective
Enhance the treatment session completion flow to automatically generate invoices with all completed services and upsold products, allowing flexible payment options at reception.

---

## Current State Analysis

### What Already Exists:
| Feature | Status | Location |
|---------|--------|----------|
| Treatment Session Page | ✅ Complete | `modules/Booking/Filament/Pages/TreatmentSession.php` |
| Session Products (upsell) | ✅ Complete | `SessionProduct` model with `usage_type` (applied/sold) |
| Session Consumables | ✅ Complete | `SessionConsumable` model (internal cost tracking) |
| Treatment Plans | ✅ Complete | `TreatmentPlan`, `TreatmentPlanItem` models |
| Auto-invoice on complete | ⚠️ Partial | Only invoices appointment service, NOT upsold products |
| Book unbooked services | ✅ Complete | Via `TreatmentPlanItem::canBook()` and `unscheduled_sessions` |
| Add to existing treatment plan | ❌ Missing | Can create new plan, cannot add to existing |

### What Needs to Be Built:
1. **Add services to existing treatment plan during session**
2. **Enhanced invoice generation** - Include sold products
3. **Session charges tracking** - Unified view of all billable items
4. **Reception checkout enhancements** - Partial payment by category
5. **Doctor discount on services** - Allow doctor to apply discount during session

---

## Workflow Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        PATIENT JOURNEY                                   │
└─────────────────────────────────────────────────────────────────────────┘

1. BOOKING                    2. CHECK-IN                 3. TREATMENT SESSION
┌──────────────┐             ┌──────────────┐            ┌──────────────────────┐
│ Patient books│             │ Patient      │            │ Doctor performs:     │
│ appointment  │ ─────────►  │ checks in    │ ────────►  │ • Complete service   │
│ (creates     │             │ at reception │            │ • Add consumables    │
│ treatment    │             │              │            │ • Upsell products    │
│ plan if new) │             │              │            │ • Add to treatment   │
└──────────────┘             └──────────────┘            │   plan (NEW)         │
                                                          └──────────┬───────────┘
                                                                     │
                                                                     ▼
4. SESSION COMPLETE                          5. CHECKOUT (RECEPTION)
┌──────────────────────────┐                ┌─────────────────────────────────┐
│ Doctor clicks "Complete" │                │ Reception sees:                 │
│                          │                │ ┌─────────────────────────────┐ │
│ System auto-generates:   │ ────────────►  │ │ SERVICES COMPLETED          │ │
│ • Invoice with:          │                │ │ ✓ Laser Session    1,500 EGP│ │
│   - Service lines        │                │ ├─────────────────────────────┤ │
│   - Product lines (sold) │                │ │ PRODUCTS SOLD               │ │
│ • Marks session ready    │                │ │ □ Sunscreen         350 EGP │ │
│   for checkout           │                │ │ □ Cream             400 EGP │ │
│                          │                │ ├─────────────────────────────┤ │
└──────────────────────────┘                │ │ [Pay Services] [Pay All]    │ │
                                            │ └─────────────────────────────┘ │
                                            └─────────────────────────────────┘
```

---

## Design Decisions (Confirmed)

1. **Consumables** - Internal costs only. NOT on invoice. Only sold products appear.
2. **Unpaid Products** - Cancel and return to inventory if not paid at checkout.
3. **Split Payments** - Yes, allow paying with multiple methods (cash + card + gift card).

---

## Implementation Checklist

### Phase 1: Add to Existing Treatment Plan (During Session)

**Files to Modify:**
- [ ] `modules/Booking/Filament/Pages/TreatmentSession.php`
- [ ] `modules/TreatmentPlans/Models/TreatmentPlanItem.php`
- [ ] `modules/TreatmentPlans/Database/Migrations/2024_02_01_000002_create_treatment_plan_items_table.php`

**Database Changes for TreatmentPlanItem:**
```php
// Ensure these fields exist (add migration if needed)
$table->string('item_type')->default('service');  // 'service', 'product', 'package'
$table->foreignId('service_id')->nullable();      // For services
$table->foreignId('product_id')->nullable();      // For products (NEW)
$table->foreignId('package_id')->nullable();      // For packages (NEW)
$table->integer('quantity')->default(1);          // For products
$table->integer('recommended_sessions')->default(1); // For services
$table->integer('completed_sessions')->default(0);
$table->integer('session_interval_days')->nullable(); // For services
$table->integer('unit_price_minor');
$table->integer('discount_minor')->default(0);
$table->integer('total_minor');
```

**Tasks:**
- [ ] Add "Add to Treatment Plan" action in session page
- [ ] Show patient's existing treatment plans (if any)
- [ ] Allow selecting existing plan OR creating new
- [ ] Support three item types: Service, Product, Package
- [ ] Dynamic form based on item type selected
- [ ] On save: create `TreatmentPlanItem` records
- [ ] Recalculate plan financials after adding

**UI Flow:**
```
[Add to Treatment Plan] button
    ↓
Modal:
  ○ Create New Plan
  ● Add to Existing Plan
      └─ Select: [Plan A (Active)] [Plan B (Active)]

  Items to Add:
  ┌──────────┬────────────────┬──────────┬──────────┬─────────┐
  │ Type     │ Item           │ Qty/Sess │ Interval │ Price   │
  ├──────────┼────────────────┼──────────┼──────────┼─────────┤
  │ Service  │ Laser Full Leg │ 6 sess   │ 30 days  │ 1,500   │
  │ Product  │ Sunscreen SPF50│ 2 qty    │ -        │ 350     │
  │ Package  │ Glow Package   │ 1 qty    │ -        │ 5,000   │
  │ [+ Add Row]               │          │          │         │
  └──────────┴────────────────┴──────────┴──────────┴─────────┘

  [Cancel] [Add to Plan]
```

**Item Type Behavior:**
| Type | Fields | Booking | Invoicing |
|------|--------|---------|-----------|
| Service | sessions, interval, price | Can book appointments | Invoice per session |
| Product | quantity, price | No booking | Invoice on delivery |
| Package | quantity, price | Includes multiple services | Invoice package price |

---

### Phase 1B: Doctor Discount on Services (During Session)

**Files to Modify:**
- [ ] `modules/Booking/Filament/Pages/TreatmentSession.php`
- [ ] `modules/Booking/Models/Appointment.php`
- [ ] `modules/Booking/Database/Migrations/xxxx_add_discount_fields_to_appointments.php` (if needed)

**New Fields on Appointment (if not exist):**
```php
$table->integer('discount_minor')->default(0);        // Discount amount
$table->string('discount_type')->default('fixed');    // 'fixed' or 'percent'
$table->string('discount_reason')->nullable();        // Doctor's reason for discount
$table->foreignId('discount_approved_by')->nullable(); // Manager approval (optional)
```

**Tasks:**
- [ ] Add "Apply Discount" action in treatment session page
- [ ] Form fields: discount type (fixed/percent), amount, reason
- [ ] Calculate and display discounted price in session summary
- [ ] Store discount on appointment record
- [ ] Pass discount to invoice generation

**UI in Treatment Session:**
```
┌─────────────────────────────────────────────────────────────────┐
│ SERVICE DETAILS                                                 │
├─────────────────────────────────────────────────────────────────┤
│ Service: Laser Hair Removal - Full Leg                          │
│ Original Price:                               1,500 EGP         │
│                                                                 │
│ [Apply Discount]                                                │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │ Discount Type: ○ Fixed Amount  ● Percentage                 │ │
│ │ Discount:      [10] %                                       │ │
│ │ Reason:        [First-time patient promotion______]         │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ Discount:                                      -150 EGP         │
│ Final Price:                                  1,350 EGP         │
└─────────────────────────────────────────────────────────────────┘
```

**Business Rules:**
- [ ] Doctor can apply discount up to configurable max (e.g., 50%)
- [ ] Discount reason is optional
- [ ] Optional: Discounts above threshold require manager approval
- [ ] Discount flows through to invoice automatically

---

### Phase 2: Enhanced Invoice Generation on Session Complete

**Files to Modify:**
- [ ] `modules/Billing/Listeners/CreateInvoiceOnAppointmentComplete.php`
- [ ] `modules/Billing/Services/InvoiceCalculationService.php`

**Tasks:**
- [ ] Modify listener to include sold products in invoice
- [ ] Add `createInvoiceForSession()` method to service
- [ ] Create service line from appointment
- [ ] Create product lines from `SessionProduct` where `usage_type = 'sold'`
- [ ] Mark products as invoiced (`is_invoiced = true`, `invoice_line_id`)
- [ ] Group lines by type (service vs product) using description prefix or category

**Invoice Line Structure:**
```php
// Service line (with doctor discount applied)
[
    'description' => 'Laser Hair Removal - Full Leg (Session 3 of 6)',
    'service_id' => $appointment->service_id,
    'appointment_id' => $appointment->id,
    'quantity' => 1,
    'unit_price_minor' => $appointment->price_minor,
    'discount_minor' => $appointment->discount_minor,      // From doctor discount
    'discount_type' => $appointment->discount_type,        // 'fixed' or 'percent'
    'line_type' => 'service', // NEW FIELD
]

// Product line (upsell)
[
    'description' => 'Sunscreen SPF50',
    'product_id' => $sessionProduct->product_id, // NEW FIELD
    'session_product_id' => $sessionProduct->id, // NEW FIELD
    'quantity' => $sessionProduct->quantity,
    'unit_price_minor' => $sessionProduct->unit_price_minor,
    'line_type' => 'product', // NEW FIELD
]
```

---

### Phase 3: Invoice Line Enhancement

**Files to Modify:**
- [ ] `modules/Billing/Database/Migrations/2024_01_01_000003_create_invoice_lines_table.php`
- [ ] `modules/Billing/Models/InvoiceLine.php`

**New Fields:**
```php
$table->string('line_type')->default('service'); // service, product, package, other
$table->foreignId('product_id')->nullable();      // Link to product if product line
$table->foreignId('session_product_id')->nullable(); // Link to SessionProduct
```

**Tasks:**
- [ ] Add migration for new columns
- [ ] Update InvoiceLine model with relationships
- [ ] Add scopes: `services()`, `products()`
- [ ] Update fillable array

---

### Phase 4: Reception Checkout Enhancement

**Files to Create:**
- [ ] `modules/Billing/Filament/Pages/SessionCheckout.php`

**Files to Modify:**
- [ ] `modules/Booking/Filament/Pages/ReceptionDashboard.php`

**Tasks:**
- [ ] Add "Ready for Checkout" section in reception dashboard
- [ ] Show patients with completed sessions (completed but unpaid invoice)
- [ ] Create SessionCheckout page with:
  - Patient info header
  - Services section (from invoice lines where line_type = 'service')
  - Products section (from invoice lines where line_type = 'product')
  - Checkboxes for partial payment selection
  - Payment method selection
  - Quick actions: "Pay Services Only", "Pay All", "Custom Amount"

**Checkout Page Layout:**
```
┌─────────────────────────────────────────────────────────────────┐
│ CHECKOUT - John Doe                        Session #APT-2024-001│
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ COMPLETED SERVICES (required)                           Amount  │
│ ─────────────────────────────────────────────────────────────── │
│ [✓] Laser Hair Removal - Full Leg (Session 3/6)       1,500 EGP│
│ [✓] Skin Consultation                                   200 EGP│
│                                              ─────────────────  │
│                                    Services Total:    1,700 EGP│
│                                                                 │
│ PRODUCTS SOLD (optional - uncheck to cancel & return to stock) │
│ ─────────────────────────────────────────────────────────────── │
│ [✓] Sunscreen SPF50 × 1                                 350 EGP│
│ [ ] Moisturizing Cream × 2          ← Will return to inventory │
│                                              ─────────────────  │
│                                    Products Total:      350 EGP│
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│ PAYMENT METHODS (split payment supported)                       │
│ ┌───────────────┬─────────────┬──────────────────┐              │
│ │ Method        │ Amount      │ Reference        │              │
│ ├───────────────┼─────────────┼──────────────────┤              │
│ │ [Cash ▼]      │ [1,500 EGP] │ [__________]     │              │
│ │ [Card ▼]      │ [550 EGP]   │ [TXN-98765]      │              │
│ │ [+ Add Method]│             │                  │              │
│ └───────────────┴─────────────┴──────────────────┘              │
│                                                                 │
│   Selected Total:     2,050 EGP                                 │
│   Payments Total:     2,050 EGP  ✓ Balanced                     │
│                                                                 │
│              ┌─────────────────────────────────────┐            │
│              │        Complete Checkout            │            │
│              └─────────────────────────────────────┘            │
└─────────────────────────────────────────────────────────────────┘
```

**Behavior Notes:**
- Services are always checked (required payment)
- Products can be unchecked → removed from invoice, returned to inventory
- Multiple payment methods can be added
- Payments must balance with selected total to enable checkout

---

### Phase 5: Split Payment & Product Cancellation

**Files to Modify:**
- [ ] `modules/Billing/Filament/Pages/SessionCheckout.php`
- [ ] `modules/Billing/Services/CheckoutService.php` (NEW)

**Split Payment Tasks:**
- [ ] Create `CheckoutService` to handle multi-payment checkout
- [ ] Allow adding multiple payment entries in checkout form
- [ ] Each payment: method (journal), amount, reference
- [ ] Total payments must equal or exceed selected amount
- [ ] Create multiple `Payment` records in single transaction

**Product Cancellation Tasks:**
- [ ] If product lines unchecked at checkout → remove from invoice
- [ ] Return products to inventory (increment stock)
- [ ] Mark `SessionProduct.is_invoiced = false`, clear `invoice_line_id`
- [ ] Delete the invoice line
- [ ] Recalculate invoice totals

**Split Payment UI:**
```
PAYMENT METHODS
┌─────────────────────────────────────────────────────────┐
│ Method          Amount           Reference              │
│ [Cash ▼]        [1,000 EGP]      [________]            │
│ [Card ▼]        [700 EGP]        [TXN-12345]           │
│ [+ Add Payment Method]                                  │
├─────────────────────────────────────────────────────────┤
│ Selected Total:     1,700 EGP                           │
│ Payments Total:     1,700 EGP  ✓                        │
└─────────────────────────────────────────────────────────┘
```

---

## Database Changes Summary

### Migration 1: `add_line_type_to_invoice_lines_table.php`
```php
Schema::table('invoice_lines', function (Blueprint $table) {
    $table->string('line_type', 20)->default('service')->after('description');
    $table->foreignId('product_id')->nullable()->after('service_id');
    $table->foreignId('session_product_id')->nullable()->after('product_id');

    $table->index('line_type');
});
```

### Migration 2: `add_product_package_to_treatment_plan_items_table.php`
```php
Schema::table('treatment_plan_items', function (Blueprint $table) {
    // Add item_type if not exists
    if (!Schema::hasColumn('treatment_plan_items', 'item_type')) {
        $table->string('item_type', 20)->default('service')->after('treatment_plan_id');
    }

    // Add product_id for product items
    if (!Schema::hasColumn('treatment_plan_items', 'product_id')) {
        $table->foreignId('product_id')->nullable()->after('service_id');
        $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
    }

    // Add package_id for package items
    if (!Schema::hasColumn('treatment_plan_items', 'package_id')) {
        $table->foreignId('package_id')->nullable()->after('product_id');
        $table->foreign('package_id')->references('id')->on('packages')->nullOnDelete();
    }

    // Add quantity for products (services use recommended_sessions)
    if (!Schema::hasColumn('treatment_plan_items', 'quantity')) {
        $table->integer('quantity')->default(1)->after('recommended_sessions');
    }

    // Add completed_quantity for products
    if (!Schema::hasColumn('treatment_plan_items', 'completed_quantity')) {
        $table->integer('completed_quantity')->default(0)->after('completed_sessions');
    }

    // Add is_delivered for products
    if (!Schema::hasColumn('treatment_plan_items', 'is_delivered')) {
        $table->boolean('is_delivered')->default(false);
        $table->timestamp('delivered_at')->nullable();
    }

    $table->index('item_type');
});
```

---

## Files to Create/Modify Summary

### Create:
| File | Purpose |
|------|---------|
| `modules/Billing/Filament/Pages/SessionCheckout.php` | Reception checkout page |
| `modules/Billing/Services/CheckoutService.php` | Split payment & product cancellation logic |
| `modules/Billing/Database/Migrations/xxxx_add_line_type_to_invoice_lines.php` | Invoice line type columns |
| `modules/TreatmentPlans/Database/Migrations/xxxx_add_product_package_to_treatment_plan_items.php` | Product/package support |

### Modify:
| File | Changes |
|------|---------|
| `modules/Booking/Filament/Pages/TreatmentSession.php` | Add to existing treatment plan action, Apply discount action |
| `modules/Booking/Models/Appointment.php` | Add discount fields if not exist, discount calculation methods |
| `modules/Billing/Listeners/CreateInvoiceOnAppointmentComplete.php` | Include sold products |
| `modules/Billing/Services/InvoiceCalculationService.php` | New method for session invoice |
| `modules/Billing/Models/InvoiceLine.php` | New fields, relationships, scopes |
| `modules/Billing/Models/Invoice.php` | Add getServicesTotalAttribute, getProductsTotalAttribute |
| `modules/Booking/Filament/Pages/ReceptionDashboard.php` | Ready for checkout section |
| `modules/Booking/Models/SessionProduct.php` | Add returnToInventory() method |
| `modules/TreatmentPlans/Models/TreatmentPlanItem.php` | Add product/package relationships, item_type handling |
| `modules/TreatmentPlans/Models/TreatmentPlan.php` | Update financial calculations for products/packages |

---

## Verification Steps

1. **Test Doctor Discount:**
   - Start treatment session
   - Click "Apply Discount" on service
   - Apply 10% discount (reason optional)
   - Verify discounted price shows in session summary
   - Complete session
   - Verify invoice has discount applied to service line

2. **Test Add Service to Treatment Plan:**
   - Start session for patient with existing treatment plan
   - Click "Add to Treatment Plan"
   - Select existing plan, add service (6 sessions, 30 day interval)
   - Verify item appears in plan with correct sessions/price

3. **Test Add Product to Treatment Plan:**
   - In treatment session, click "Add to Treatment Plan"
   - Select item type "Product"
   - Add product with quantity 2
   - Verify product item appears in plan
   - Verify product has no booking option (products don't create appointments)

4. **Test Add Package to Treatment Plan:**
   - In treatment session, click "Add to Treatment Plan"
   - Select item type "Package"
   - Add package
   - Verify package item appears in plan with included services

5. **Test Invoice Generation:**
   - Complete session with upsold products
   - Verify invoice created with service line AND product lines
   - Verify products marked as invoiced
   - Verify line_type is correct for each line

6. **Test Reception Checkout:**
   - Complete session
   - Go to reception dashboard
   - Verify patient appears in "Ready for Checkout"
   - Open checkout, verify services/products separated
   - Test "Pay All" - verify full payment, invoice marked PAID

7. **Test Product Cancellation:**
   - Complete session with 2 products upsold
   - At checkout, uncheck 1 product
   - Pay for services + 1 product only
   - Verify unchecked product line removed from invoice
   - Verify product returned to inventory (stock increased)
   - Verify SessionProduct.is_invoiced reset to false

8. **Test Split Payment:**
   - At checkout, add two payment methods
   - Pay 1,000 EGP cash + 700 EGP card
   - Verify two Payment records created
   - Verify invoice total paid = 1,700 EGP
   - Verify invoice status updated correctly

---

## Implementation Order

1. **Phase 3** - Database migrations (foundation)
   - Invoice lines: add line_type, product_id, session_product_id
   - Treatment plan items: add product_id, package_id, item_type, quantity
2. **Phase 1B** - Doctor discount on services
3. **Phase 2** - Invoice generation enhancement (includes discount)
4. **Phase 1** - Add to treatment plan (services, products, packages)
5. **Phase 4** - Reception checkout page
6. **Phase 5** - Split payment & product cancellation
