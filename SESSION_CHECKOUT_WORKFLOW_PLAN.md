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

**Tasks:**
- [ ] Add "Add to Treatment Plan" action in session page
- [ ] Show patient's existing treatment plans (if any)
- [ ] Allow selecting existing plan OR creating new
- [ ] Form to add services: service, sessions count, interval, price
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

  Services to Add:
  ┌────────────────┬──────────┬──────────┬─────────┐
  │ Service        │ Sessions │ Interval │ Price   │
  ├────────────────┼──────────┼──────────┼─────────┤
  │ Laser Full Leg │ 6        │ 30 days  │ 1,500   │
  │ [+ Add Row]    │          │          │         │
  └────────────────┴──────────┴──────────┴─────────┘

  [Cancel] [Add to Plan]
```

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
// Service line
[
    'description' => 'Laser Hair Removal - Full Leg (Session 3 of 6)',
    'service_id' => $appointment->service_id,
    'appointment_id' => $appointment->id,
    'quantity' => 1,
    'unit_price_minor' => $appointment->price_minor,
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

### New Migration: `add_line_type_to_invoice_lines_table.php`
```php
Schema::table('invoice_lines', function (Blueprint $table) {
    $table->string('line_type', 20)->default('service')->after('description');
    $table->foreignId('product_id')->nullable()->after('service_id');
    $table->foreignId('session_product_id')->nullable()->after('product_id');

    $table->index('line_type');
});
```

---

## Files to Create/Modify Summary

### Create:
| File | Purpose |
|------|---------|
| `modules/Billing/Filament/Pages/SessionCheckout.php` | Reception checkout page |
| `modules/Billing/Services/CheckoutService.php` | Split payment & product cancellation logic |
| `modules/Billing/Database/Migrations/xxxx_add_line_type_to_invoice_lines.php` | New columns |

### Modify:
| File | Changes |
|------|---------|
| `modules/Booking/Filament/Pages/TreatmentSession.php` | Add to existing treatment plan action |
| `modules/Billing/Listeners/CreateInvoiceOnAppointmentComplete.php` | Include sold products |
| `modules/Billing/Services/InvoiceCalculationService.php` | New method for session invoice |
| `modules/Billing/Models/InvoiceLine.php` | New fields, relationships, scopes |
| `modules/Billing/Models/Invoice.php` | Add getServicesTotalAttribute, getProductsTotalAttribute |
| `modules/Booking/Filament/Pages/ReceptionDashboard.php` | Ready for checkout section |
| `modules/Booking/Models/SessionProduct.php` | Add returnToInventory() method |

---

## Verification Steps

1. **Test Add to Treatment Plan:**
   - Start session for patient with existing treatment plan
   - Click "Add to Treatment Plan"
   - Select existing plan, add service
   - Verify item appears in plan with correct sessions/price

2. **Test Invoice Generation:**
   - Complete session with upsold products
   - Verify invoice created with service line AND product lines
   - Verify products marked as invoiced
   - Verify line_type is correct for each line

3. **Test Reception Checkout:**
   - Complete session
   - Go to reception dashboard
   - Verify patient appears in "Ready for Checkout"
   - Open checkout, verify services/products separated
   - Test "Pay All" - verify full payment, invoice marked PAID

4. **Test Product Cancellation:**
   - Complete session with 2 products upsold
   - At checkout, uncheck 1 product
   - Pay for services + 1 product only
   - Verify unchecked product line removed from invoice
   - Verify product returned to inventory (stock increased)
   - Verify SessionProduct.is_invoiced reset to false

5. **Test Split Payment:**
   - At checkout, add two payment methods
   - Pay 1,000 EGP cash + 700 EGP card
   - Verify two Payment records created
   - Verify invoice total paid = 1,700 EGP
   - Verify invoice status updated correctly

---

## Implementation Order

1. **Phase 3** - Database migration (foundation)
2. **Phase 2** - Invoice generation enhancement
3. **Phase 1** - Add to treatment plan
4. **Phase 4** - Reception checkout page
5. **Phase 5** - Split payment & product cancellation
