# Booking Slot Configuration - Implementation Checklist

## Overview
Configuration screen for the booking slot generation algorithm with dynamic rules, holiday calendar, and real-time preview.

---

## Phase 1: Database & Models

### Migrations
- [ ] Create `booking_rules` table migration
  - [ ] id, tenant_id, scope_level, branch_id, service_id
  - [ ] name, code, description, rule_type
  - [ ] conditions (JSONB), actions (JSONB)
  - [ ] priority, is_active, timestamps
  - [ ] Indexes for performance

- [ ] Create `booking_blackout_dates` table migration
  - [ ] id, tenant_id, branch_id
  - [ ] name, start_date, end_date
  - [ ] is_recurring, recurrence_type
  - [ ] affects_online_booking, affects_staff_booking
  - [ ] reason, is_active, timestamps

- [ ] Create `booking_settings` table migration (if not using existing settings)
  - [ ] Tenant-level slot configuration settings
  - [ ] Branch-level overrides

### Models
- [ ] Create `BookingRule` model
  - [ ] Fillable, casts, relationships
  - [ ] Scope constants (TENANT, BRANCH, SERVICE)
  - [ ] Rule type constants
  - [ ] Validation methods
  - [ ] `isActiveFor($date, $service, $branch)` method
  - [ ] `evaluate($context)` method

- [ ] Create `BookingBlackoutDate` model
  - [ ] Fillable, casts, relationships
  - [ ] `isBlackout($date)` method
  - [ ] `getBlackoutsForDateRange($start, $end)` method
  - [ ] Recurring date calculation

---

## Phase 2: Configuration Page

### BookingSlotConfigPage.php
- [ ] Create Filament page in `modules/Booking/Filament/Pages/`
- [ ] Register in navigation (Settings group)
- [ ] Implement tabs structure:
  - [ ] Tab 1: Algorithm Visualization
  - [ ] Tab 2: Core Settings
  - [ ] Tab 3: Booking Restrictions
  - [ ] Tab 4: Online Booking
  - [ ] Tab 5: Rules (link to resource)
  - [ ] Tab 6: Holidays (link to resource)

### Algorithm Visualization Panel
- [ ] Create visual flowchart component
- [ ] Show current configuration values
- [ ] Explain each step of slot generation
- [ ] Highlight active rules and their effects

### Core Settings Form
- [ ] Default slot duration (minutes)
- [ ] Slot interval (minutes, separate from duration)
- [ ] Buffer between appointments (minutes)
- [ ] Default working hours start/end
- [ ] Min advance booking (hours)
- [ ] Max advance booking (days)
- [ ] Cancellation policy (hours)
- [ ] Allow same-day booking toggle

### Online Booking Settings
- [ ] Enable online booking toggle
- [ ] Auto-confirm toggle
- [ ] Show practitioner selection toggle
- [ ] Require deposit toggle
- [ ] Deposit percentage (when required)

---

## Phase 3: Rules Engine

### BookingRuleResource.php
- [ ] Create Filament resource
- [ ] Form schema with:
  - [ ] Basic info (name, code, description)
  - [ ] Scope selection (tenant/branch/service)
  - [ ] Branch selector (when scope = branch/service)
  - [ ] Service selector (when scope = service)
  - [ ] Rule type dropdown
  - [ ] Conditions builder (dynamic based on type)
  - [ ] Actions builder (dynamic based on type)
  - [ ] Priority slider
  - [ ] Active toggle

### Conditions Builder UI
- [ ] Days of week checkboxes
- [ ] Time range picker (start/end)
- [ ] Date range picker (for seasonal rules)
- [ ] Services multi-select
- [ ] Service categories multi-select
- [ ] Practitioners multi-select
- [ ] Rooms multi-select
- [ ] Equipment multi-select
- [ ] Patient type (new/returning)

### Actions Builder UI
- [ ] For slot_block: reason text
- [ ] For time_restriction: allowed start/end time
- [ ] For capacity_limit: max number, scope (day/practitioner)
- [ ] For buffer_override: buffer minutes
- [ ] For advance_booking: min hours, max days
- [ ] For online_restriction: allow toggle, reason

### Table Columns
- [ ] Name with scope badge
- [ ] Rule type badge
- [ ] Priority
- [ ] Active status toggle
- [ ] Branch/Service indicators
- [ ] Edit/Delete actions

---

## Phase 4: Holiday Calendar

### BookingBlackoutDateResource.php
- [ ] Create Filament resource
- [ ] Form schema with:
  - [ ] Name
  - [ ] Date picker (single or range)
  - [ ] Branch selector (or all branches)
  - [ ] Recurring toggle
  - [ ] Recurrence type (yearly/monthly)
  - [ ] Affects online booking toggle
  - [ ] Affects staff booking toggle
  - [ ] Reason textarea
  - [ ] Active toggle

### Calendar Visualization
- [ ] Month calendar view
- [ ] Highlight blackout dates
- [ ] Show upcoming holidays list
- [ ] Quick add from calendar click

---

## Phase 5: Integration with SlotGenerationService

### BookingRuleEvaluator Service
- [ ] Create service class
- [ ] `getRulesForContext($tenant, $branch, $service, $date)` method
- [ ] `evaluateRules($slot, $rules)` method
- [ ] `isSlotBlocked($slot, $rules)` method
- [ ] `getEffectiveBuffer($service, $rules)` method
- [ ] `getTimeRestrictions($date, $rules)` method
- [ ] `checkCapacityLimits($practitioner, $date, $rules)` method

### SlotGenerationService Updates
- [ ] Inject BookingRuleEvaluator
- [ ] Load applicable rules at start of generation
- [ ] Check blackout dates before generating
- [ ] Apply time restrictions to slot generation
- [ ] Evaluate each slot against active rules
- [ ] Apply buffer overrides from rules
- [ ] Check capacity limits per practitioner
- [ ] Add rule-based blocking reasons to response

### AvailabilityService Updates
- [ ] Use BookingRuleEvaluator for consistency
- [ ] Apply same rules as SlotGenerationService

---

## Phase 6: Preview Feature

### Slot Preview Component
- [ ] Service selector
- [ ] Date picker
- [ ] Branch selector
- [ ] Generate button
- [ ] Results table showing:
  - [ ] Time slot
  - [ ] Available/Blocked status
  - [ ] Practitioner(s) available
  - [ ] Room assignment
  - [ ] Equipment assignment
  - [ ] Block reason (if blocked)
- [ ] Summary statistics

---

## Phase 7: Branch Overrides

### Branch Settings
- [ ] Branch selector in config page
- [ ] Show inherited tenant values
- [ ] Allow override for each setting
- [ ] Visual indicator for overridden values
- [ ] Reset to tenant default button

---

## Phase 8: Translations

### English (en)
- [ ] `modules/Booking/Lang/en/slot_config.php`
  - [ ] Page titles and labels
  - [ ] Rule types
  - [ ] Condition labels
  - [ ] Action labels
  - [ ] Help text
  - [ ] Validation messages

### Arabic (ar)
- [ ] `modules/Booking/Lang/ar/slot_config.php`
  - [ ] All corresponding Arabic translations

---

## Phase 9: Testing & Verification

### Manual Testing
- [ ] Change default duration → verify new bookings use it
- [ ] Create lunch break rule → verify slots blocked 12:00-13:00
- [ ] Add holiday → verify no slots on that date
- [ ] Create service-specific buffer → verify only that service affected
- [ ] Set branch override → verify isolation from other branches
- [ ] Test capacity limit → verify practitioner max enforced
- [ ] Generate preview → verify matches actual booking UI
- [ ] Switch to Arabic → verify all labels translated

### Edge Cases
- [ ] Overlapping rules with different priorities
- [ ] Rule with no matching conditions
- [ ] Blackout date on recurring appointment
- [ ] Branch override + service override on same booking
- [ ] Rule active date range crossing months
- [ ] Maximum capacity on holiday edge

---

## Files Summary

### To Create
| File | Purpose |
|------|---------|
| `modules/Booking/Database/Migrations/create_booking_rules_table.php` | Rules table |
| `modules/Booking/Database/Migrations/create_booking_blackout_dates_table.php` | Holidays |
| `modules/Booking/Models/BookingRule.php` | Rule model |
| `modules/Booking/Models/BookingBlackoutDate.php` | Blackout model |
| `modules/Booking/Filament/Pages/BookingSlotConfigPage.php` | Main config page |
| `modules/Booking/Filament/Resources/BookingRuleResource.php` | Rule CRUD |
| `modules/Booking/Filament/Resources/BookingBlackoutDateResource.php` | Holiday CRUD |
| `modules/Booking/Services/BookingRuleEvaluator.php` | Rule evaluation |
| `modules/Booking/Lang/en/slot_config.php` | English translations |
| `modules/Booking/Lang/ar/slot_config.php` | Arabic translations |

### To Modify
| File | Changes |
|------|---------|
| `modules/Booking/Services/SlotGenerationService.php` | Add rule evaluation |
| `modules/Booking/Services/AvailabilityService.php` | Add rule evaluation |
| `modules/Core/Filament/Pages/GeneralSettingsPage.php` | Remove booking tab |
| `modules/Booking/Config/config.php` | Use database settings |
| `modules/Booking/Providers/BookingServiceProvider.php` | Register new pages |

---

## Notes
- Rules are evaluated in priority order (highest first, 100 = max)
- Service-level rules override branch-level, which override tenant-level
- Blackout dates completely block slot generation for affected dates
- Preview feature helps administrators test configuration before saving
