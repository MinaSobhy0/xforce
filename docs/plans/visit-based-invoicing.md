# Visit-Based Invoicing Implementation Plan

## Overview

Implement a Visit system that groups all appointments and products for a patient's clinic visit into a single invoice at checkout.

## Database Schema

### visits table
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| tenant_id | foreignId | Tenant reference |
| branch_id | foreignId | Branch reference |
| patient_id | foreignId | Patient reference |
| code | string | Auto-generated (VST-0001) |
| check_in_at | timestamp | When patient checked in |
| check_out_at | timestamp | When patient checked out (nullable) |
| status | string | open, completed, invoiced, cancelled |
| invoice_id | foreignId | Linked invoice after checkout (nullable) |
| total_minor | bigint | Calculated total in minor units |
| notes | text | Optional notes |

### visit_appointments pivot table
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| visit_id | foreignId | Visit reference |
| appointment_id | foreignId | Appointment reference |

### session_products table (existing - add column)
| Column | Type | Description |
|--------|------|-------------|
| visit_id | foreignId | Visit reference (nullable) |

---

## Implementation Checklist

### Phase 1: Database & Models

- [x] **1.1** Create migration: `create_visits_table`
- [x] **1.2** Create migration: `create_visit_appointments_table`
- [x] **1.3** Create migration: `add_visit_id_to_session_products`
- [x] **1.4** Create `Visit` model with relationships
- [x] **1.5** Add `visits()` relationship to `Appointment` model
- [x] **1.6** Add `visit()` relationship to `SessionProduct` model
- [x] **1.7** Register Visit sequence code in seeder

### Phase 2: Visit Service

- [x] **2.1** Create `VisitService` with methods:
  - [x] `createVisit(Patient $patient, Branch $branch): Visit`
  - [x] `findOpenVisit(Patient $patient): ?Visit`
  - [x] `addAppointment(Visit $visit, Appointment $appointment): void`
  - [x] `getVisitSummary(Visit $visit): array`
  - [x] `checkout(Visit $visit, array $sessionActions): Invoice`
  - [x] `calculateTotal(Visit $visit): int`
- [x] **2.2** Add `visit_id` to Invoice model and migration

### Phase 3: Check-in Flow Update

- [x] **3.1** Update check-in process to create/find Visit
- [x] **3.2** Link appointment to visit on check-in
- [x] **3.3** Show current visit info in appointment view
- [x] **3.4** Add visit methods to ReceptionService

### Phase 4: Session Flow Update

- [x] **4.1** Update `TreatmentSession` page to show visit context
- [x] **4.2** When adding new service, link to current visit
- [x] **4.3** When selling product, set `visit_id` on `SessionProduct`
- [x] **4.4** Update invoice summary to show full visit items

### Phase 5: Checkout Page

- [x] **5.1** Create `Checkout` Filament page
- [x] **5.2** Show all visit appointments with status
- [x] **5.3** Show all visit products
- [x] **5.4** Validation for open sessions:
  - [x] Option to complete session
  - [x] Option to cancel session
  - [x] Option to reschedule session
- [x] **5.5** Package deduction handling
- [x] **5.6** Discount application (per-item and overall)
- [x] **5.7** Generate invoice on confirm
- [x] **5.8** Payment integration (via Invoice view page)

### Phase 6: Disable Auto-Invoice

- [x] **6.1** Remove/disable `CreateInvoiceOnAppointmentComplete` listener
- [x] **6.2** Update any dependent code

### Phase 7: Patient Profile Integration

- [x] **7.1** Add "Visit History" tab to patient profile
- [x] **7.2** Show visit details:
  - [x] Date & time
  - [x] Services received
  - [x] Products purchased
  - [x] Doctors involved
  - [x] Invoice link
  - [x] Total paid

### Phase 8: Reception Dashboard

- [x] **8.1** Add "Open Visits" widget to reception dashboard
- [x] **8.2** Quick checkout action from dashboard
- [x] **8.3** Filter appointments by visit status

### Phase 9: Reports

- [x] **9.1** Daily Visits Report page with date navigation
- [x] **9.2** Session type breakdown (New vs Package vs Treatment Plan Continuation)
- [x] **9.3** Summary statistics (visits, revenue, avg duration)
- [x] **9.4** Visual breakdown with progress bars
- [x] **9.5** Filterable visits table with session type badges

---

## Flow Diagrams

### Check-in Flow
```
Patient Arrives
       ↓
Check-in for Appointment
       ↓
┌─────────────────────────┐
│ Open visit exists?      │
├─────────────────────────┤
│ YES → Add to existing   │
│ NO  → Create new visit  │
└─────────────────────────┘
       ↓
Link appointment to visit
       ↓
Start Session
```

### Checkout Flow
```
Click "Checkout" for Visit
       ↓
┌─────────────────────────────────┐
│ Any open sessions?              │
├─────────────────────────────────┤
│ YES → Show validation modal     │
│       For each open session:    │
│       ○ Complete                │
│       ○ Cancel                  │
│       ○ Reschedule              │
│                                 │
│ NO  → Proceed to invoice        │
└─────────────────────────────────┘
       ↓
Generate Invoice
  - Completed appointments
  - Sold products
  - Package deductions
  - Discounts
       ↓
Visit status → invoiced
       ↓
Payment Screen
```

### Invoice Structure
```
INVOICE #INV-0001
─────────────────────────────────────────
Visit: VST-0001 | Patient: Ahmed Mohamed
Date: 2026-03-05
─────────────────────────────────────────

SERVICES
─────────────────────────────────────────
Laser Hair Removal (Dr. Sara)    500.00
  Session 2 of 6
Skin Treatment (Dr. Mohamed)     300.00
  Session 1 of 3
─────────────────────────────────────────
                    Subtotal:    800.00

PRODUCTS
─────────────────────────────────────────
Sunscreen SPF 50 x1              150.00
Moisturizer x2                   200.00
─────────────────────────────────────────
                    Subtotal:    350.00

─────────────────────────────────────────
              Services Total:    800.00
              Products Total:    350.00
         Package Deduction:    -500.00
                 Discount:      -50.00
─────────────────────────────────────────
              GRAND TOTAL:      600.00
─────────────────────────────────────────
```

---

## Status Definitions

| Visit Status | Description |
|--------------|-------------|
| `open` | Patient is in clinic, sessions ongoing |
| `completed` | All sessions done, ready for checkout |
| `invoiced` | Invoice created, patient checked out |
| `cancelled` | Visit cancelled |

---

## Notes

- Visit is automatically created on first check-in of the day
- Multiple appointments on same day = same visit
- Products sold link to current active visit
- Invoice only created at checkout, not on session completion
- Package sessions deducted at checkout, not during session
