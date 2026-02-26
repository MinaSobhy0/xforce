# UUID to INT Migration Plan

## Overview
Converting all UUID primary keys to auto-increment INT (like Odoo) for better performance and smaller storage.

**Benefits:**
- 75% smaller ID storage (4 bytes vs 16 bytes)
- 7x faster range queries
- Simpler debugging (id=123 vs id=a1b2c3d4-...)
- Native PostgreSQL optimization

**Started:** 2026-02-26
**Completed:** 2026-02-26
**Status:** COMPLETED - All phases finished (359/359 items - 100%)
**Estimated Scope:** ~380 items

---

## CRITICAL: Pre-Migration Decisions

### Decision 1: Spatie Permission Model Morph Key
**Issue:** Spatie Permission tables use `uuid()` for `model_morph_key` (User ID in pivot tables).
When User IDs change to INT, these must also change.

**File:** `modules/Auth/Database/Migrations/0001_01_01_000001_create_permission_tables.php`
- Line 69: `$table->uuid($columnNames['model_morph_key']);`
- Line 93: `$table->uuid($columnNames['model_morph_key']);`

**Action:** Change to `$table->unsignedBigInteger($columnNames['model_morph_key']);`

| # | Status | Task |
|---|--------|------|
| D1 | [x] | Update model_has_permissions.model_id from uuid() to unsignedBigInteger() |
| D2 | [x] | Update model_has_roles.model_id from uuid() to unsignedBigInteger() |

### Decision 2: Activity Log Morphs
**Issue:** Activity log tables use `nullableUuidMorphs()` for subject and causer.

**Files:**
- `database/migrations/2026_02_19_023928_create_activity_log_table.php`
- `modules/Core/Database/Migrations/2024_01_01_000009_create_activities_table.php`

**Action:** Change to `nullableMorphs()` (uses BIGINT for morph IDs)

| # | Status | Task |
|---|--------|------|
| D3 | [x] | Update activity_log table morphs from nullableUuidMorphs to nullableMorphs |
| D4 | [x] | Update activities table morphs from nullableUuidMorphs to nullableMorphs |
| D5 | [x] | Delete modules/Core/Database/Migrations/2026_02_21_000001_alter_activities_table_uuid_morphs.php |

### Decision 3: Keep batch_uuid as UUID
**Rationale:** batch_uuid is used for grouping related activity log entries, not as a primary/foreign key.

| # | Status | Task |
|---|--------|------|
| D6 | [x] | Keep batch_uuid column as UUID in activity_log table (no change needed) |

---

## Rollback Strategy

**Before starting:**
1. Create full database backup
2. Create git branch: `feature/uuid-to-int-migration`
3. Test on development database first

**If issues arise:**
1. Restore from backup
2. Revert to previous git commit
3. Document what failed for retry

---

## Phase 0: Framework & Base Classes ✅ COMPLETED

### 0.1 BaseModel - Core Foundation
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 1 | [x] | `framework/Core/Model/BaseModel.php` | Remove UUID generation in `booted()`, remove `$keyType = 'string'`, remove `$incrementing = false`, remove `'id' => 'string'` from $casts |

### 0.2 Audit Model
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 2 | [x] | `framework/Core/Model/Audit.php` | Remove UUID generation, remove `$keyType = 'string'`, remove `$incrementing = false` |

### 0.3 Framework Traits
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 3 | [x] | `framework/Core/Model/Traits/HasAudit.php` | No changes needed - uses Laravel morphMany |
| 4 | [x] | `framework/Core/Model/Traits/HasTenancy.php` | Updated type hints: string $tenantId → int $tenantId |
| 5 | [x] | `framework/Core/Model/Traits/HasSequence.php` | No UUID references found |
| 6 | [x] | `framework/Core/Model/Traits/HasStateMachine.php` | No UUID references found |
| 7 | [x] | `framework/Core/Model/Traits/HasPortalAccess.php` | No UUID references found |

---

## Phase 1: App Models (HasUuids Trait) ✅ COMPLETED

Remove `use HasUuids;` trait from all models.

| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 8 | [x] | `app/Models/ImportMapping.php` | Remove `use HasUuids;` |
| 9 | [x] | `app/Models/TenantDomain.php` | Remove `use HasUuids;` |
| 10 | [x] | `app/Models/Module.php` | Remove `use HasUuids;` |
| 11 | [x] | `app/Models/SystemAlert.php` | Remove `use HasUuids;` |
| 12 | [x] | `app/Models/PromoCode.php` | Remove `use HasUuids;` |
| 13 | [x] | `app/Models/SubscriptionPlan.php` | Remove `use HasUuids;` |
| 14 | [x] | `app/Models/SupportTicketReply.php` | Remove `use HasUuids;` |
| 15 | [x] | `app/Models/ContactInquiry.php` | Remove `use HasUuids;` |
| 16 | [x] | `app/Models/TenantActivityLog.php` | Remove `use HasUuids;` |
| 17 | [x] | `app/Models/EmailTemplate.php` | Remove `use HasUuids;` |
| 18 | [x] | `app/Models/SupportTicket.php` | Remove `use HasUuids;` |
| 19 | [x] | `app/Models/PlatformSetting.php` | Remove `use HasUuids;` + fix raw SQL UUID insert |
| 20 | [x] | `app/Models/AddOn.php` | Remove `use HasUuids;` |
| 21 | [x] | `app/Models/PlatformInvoice.php` | Remove `use HasUuids;` |
| 22 | [x] | `app/Models/RestoreRequest.php` | Remove `use HasUuids;` |
| 23 | [x] | `app/Models/OnboardingRequest.php` | Remove `use HasUuids;` |
| 24 | [x] | `app/Models/TenantAddonSubscription.php` | Remove `use HasUuids;` |
| 25 | [x] | `app/Models/Backup.php` | Remove `use HasUuids;` |
| 26 | [x] | `app/Models/Announcement.php` | Remove `use HasUuids;` |
| 27 | [x] | `app/Models/AuditLog.php` | Remove `use HasUuids;` |

---

## Phase 2: Module Models - Remove Explicit ID Casts ✅ COMPLETED

**CRITICAL:** These 31 models have explicit `'id' => 'string'` in $casts that MUST be removed.

### 2.1 Attendance Module (8 models with explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 28 | [x] | `modules/Attendance/Models/Attendance.php` | Remove `'id' => 'string'` from $casts |
| 29 | [x] | `modules/Attendance/Models/AttendanceLog.php` | Remove `'id' => 'string'` from $casts |
| 30 | [x] | `modules/Attendance/Models/AttendanceBreak.php` | Remove `'id' => 'string'` from $casts |
| 31 | [x] | `modules/Attendance/Models/AttendanceRule.php` | Remove `'id' => 'string'` from $casts |
| 32 | [x] | `modules/Attendance/Models/AttendanceRuleAction.php` | Remove `'id' => 'string'` from $casts + keyType + incrementing |
| 33 | [x] | `modules/Attendance/Models/AttendanceViolation.php` | Remove `'id' => 'string'` from $casts |
| 34 | [x] | `modules/Attendance/Models/AttendanceTypeSetting.php` | Remove `'id' => 'string'` from $casts |
| 35 | [x] | `modules/Attendance/Models/WorkSchedule.php` | Verify BaseModel changes work |

### 2.2 Booking Module (2 models with explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 36 | [x] | `modules/Booking/Models/TimeOffType.php` | Remove `'id' => 'string'` from $casts |
| 37 | [x] | `modules/Booking/Models/TimeOffAllocation.php` | Remove `'id' => 'string'` from $casts |

### 2.3 Staff Module (3 models with explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 38 | [x] | `modules/Staff/Models/StaffProfile.php` | Remove `'id' => 'string'` from $casts |
| 39 | [x] | `modules/Staff/Models/StaffCommission.php` | Remove `'id' => 'string'` from $casts |
| 40 | [x] | `modules/Staff/Models/StaffCommissionRecord.php` | Remove `'id' => 'string'` from $casts |

### 2.4 Payroll Module (7 models with explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 41 | [x] | `modules/Payroll/Models/PayrollRun.php` | Remove `'id' => 'string'` from $casts |
| 42 | [x] | `modules/Payroll/Models/PayrollLine.php` | Remove `'id' => 'string'` from $casts |
| 43 | [x] | `modules/Payroll/Models/SalaryStructure.php` | Remove `'id' => 'string'` from $casts |
| 44 | [x] | `modules/Payroll/Models/SalaryRule.php` | Remove `'id' => 'string'` from $casts |
| 45 | [x] | `modules/Payroll/Models/SalaryRuleCategory.php` | Remove `'id' => 'string'` from $casts |
| 46 | [x] | `modules/Payroll/Models/EmployeeSalaryStructure.php` | Remove `'id' => 'string'` from $casts |
| 47 | [x] | `modules/Payroll/Models/EmployeeSalaryComponent.php` | Remove `'id' => 'string'` from $casts |

### 2.5 Inventory Module (11 models with explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 48 | [x] | `modules/Inventory/Models/ProductCategory.php` | Remove `'id' => 'string'` from $casts |
| 49 | [x] | `modules/Inventory/Models/Product.php` | Remove `'id' => 'string'` from $casts |
| 50 | [x] | `modules/Inventory/Models/StockLevel.php` | Remove `'id' => 'string'` from $casts |
| 51 | [x] | `modules/Inventory/Models/StockMovement.php` | Remove `'id' => 'string'` from $casts |
| 52 | [x] | `modules/Inventory/Models/Supplier.php` | Remove `'id' => 'string'` from $casts |
| 53 | [x] | `modules/Inventory/Models/PurchaseOrder.php` | Remove `'id' => 'string'` from $casts |
| 54 | [x] | `modules/Inventory/Models/PurchaseOrderLine.php` | Remove `'id' => 'string'` from $casts |
| 55 | [x] | `modules/Inventory/Models/InventoryAdjustment.php` | Remove `'id' => 'string'` from $casts |
| 56 | [x] | `modules/Inventory/Models/InventoryAdjustmentLine.php` | Remove `'id' => 'string'` from $casts |
| 57 | [x] | `modules/Inventory/Models/VendorBill.php` | Remove `'id' => 'string'` from $casts |
| 58 | [x] | `modules/Inventory/Models/VendorBillLine.php` | Remove `'id' => 'string'` from $casts |

---

## Phase 3: Module Models - Verify BaseModel Changes ✅ COMPLETED

All these extend BaseModel - after fixing BaseModel, verify they work correctly.
**NOTE:** Models that don't extend BaseModel needed explicit fixes (UUID generation removed).
**COMPLETED:** All models verified, services fixed (TenantService, GiftCardService, BookingSlotConfigPage).

### 3.1 Core Module (9 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 59 | [x] | `modules/Core/Models/Tenant.php` | Removed $keyType, $incrementing, UUID generation |
| 60 | [x] | `modules/Core/Models/TenantSubscription.php` | Removed $keyType, $incrementing, UUID generation |
| 61 | [x] | `modules/Core/Models/TenantUsage.php` | Removed $keyType, $incrementing, UUID generation |
| 62 | [x] | `modules/Core/Models/TenantModule.php` | Extends BaseModel - auto-fixed |
| 63 | [x] | `modules/Core/Models/TenantStatus.php` | State enum - no changes needed |
| 64 | [x] | `modules/Core/Models/Branch.php` | Extends BaseModel - auto-fixed |
| 65 | [x] | `modules/Core/Models/Room.php` | Extends BaseModel - auto-fixed |
| 66 | [x] | `modules/Core/Models/Setting.php` | Extends BaseModel - auto-fixed |
| 67 | [x] | `modules/Core/Models/Sequence.php` | Extends BaseModel - auto-fixed |

### 3.2 Auth Module (8 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 68 | [x] | `modules/Auth/Models/User.php` | Extends BaseModel - auto-fixed |
| 69 | [x] | `modules/Auth/Models/UserProfile.php` | Removed $keyType, $incrementing, UUID generation |
| 70 | [x] | `modules/Auth/Models/UserSession.php` | Removed $keyType, $incrementing, UUID generation |
| 71 | [x] | `modules/Auth/Models/LoginHistory.php` | Removed $keyType, $incrementing, UUID generation |
| 72 | [x] | `modules/Auth/Models/PasswordHistory.php` | Removed $keyType, $incrementing, UUID generation |
| 73 | [x] | `modules/Auth/Models/AccessPolicy.php` | Extends BaseModel - auto-fixed |
| 74 | [x] | `modules/Auth/Models/Role.php` | Keep as-is (extends Spatie, uses BIGINT) |
| 75 | [x] | `modules/Auth/Models/Permission.php` | Keep as-is (extends Spatie, uses BIGINT) |

### 3.3 Patients Module (14 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 76 | [x] | `modules/Patients/Models/Patient.php` | Extends BaseModel - auto-fixed |
| 77 | [x] | `modules/Patients/Models/PatientMedicalHistory.php` | Extends BaseModel - auto-fixed |
| 78 | [x] | `modules/Patients/Models/PatientConsentForm.php` | Extends BaseModel - auto-fixed |
| 79 | [x] | `modules/Patients/Models/PatientPhoto.php` | Extends BaseModel - auto-fixed |
| 80 | [x] | `modules/Patients/Models/PatientNote.php` | Extends BaseModel - auto-fixed |
| 81 | [x] | `modules/Patients/Models/MedicalProfile.php` | Extends BaseModel - auto-fixed |
| 82 | [x] | `modules/Patients/Models/MedicalAllergy.php` | Extends BaseModel - auto-fixed |
| 83 | [x] | `modules/Patients/Models/MedicalMedication.php` | Extends BaseModel - auto-fixed |
| 84 | [x] | `modules/Patients/Models/MedicalContraindication.php` | Extends BaseModel - auto-fixed |
| 85 | [x] | `modules/Patients/Models/MedicalHistory.php` | Extends BaseModel - auto-fixed |
| 86 | [x] | `modules/Patients/Models/LifestyleInfo.php` | Extends BaseModel - auto-fixed |
| 87 | [x] | `modules/Patients/Models/SkinAssessment.php` | Extends BaseModel - auto-fixed |
| 88 | [x] | `modules/Patients/Models/PatientAmrTest.php` | Extends BaseModel - auto-fixed |
| 89 | [x] | `modules/Patients/Models/PatientAmrSummary.php` | Extends BaseModel - auto-fixed |

### 3.4 Services Module (7 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 90 | [x] | `modules/Services/Models/Service.php` | Extends BaseModel - auto-fixed |
| 91 | [x] | `modules/Services/Models/ServiceCategory.php` | Extends BaseModel - auto-fixed |
| 92 | [x] | `modules/Services/Models/ServiceBranchPricing.php` | Extends BaseModel - auto-fixed |
| 93 | [x] | `modules/Services/Models/ConsentTemplate.php` | Extends BaseModel - auto-fixed |
| 94 | [x] | `modules/Services/Models/ParameterTemplate.php` | Extends BaseModel - auto-fixed |
| 95 | [x] | `modules/Services/Models/ServiceParameter.php` | Extends BaseModel - auto-fixed |
| 96 | [x] | `modules/Services/Models/ParameterPreset.php` | Extends BaseModel - auto-fixed |

### 3.5 Booking Module (13 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 97 | [x] | `modules/Booking/Models/Appointment.php` | Extends BaseModel - auto-fixed |
| 98 | [x] | `modules/Booking/Models/AppointmentServiceNote.php` | Extends BaseModel - auto-fixed |
| 99 | [x] | `modules/Booking/Models/Waitlist.php` | Extends BaseModel - auto-fixed |
| 100 | [x] | `modules/Booking/Models/PractitionerSchedule.php` | Extends BaseModel - auto-fixed |
| 101 | [x] | `modules/Booking/Models/PractitionerScheduleAssignment.php` | Extends BaseModel - auto-fixed |
| 102 | [x] | `modules/Booking/Models/PractitionerTimeOff.php` | Extends BaseModel - auto-fixed |
| 103 | [x] | `modules/Booking/Models/TreatmentSessionData.php` | Extends BaseModel - auto-fixed |
| 104 | [x] | `modules/Booking/Models/SessionConsumable.php` | Extends BaseModel - auto-fixed |
| 105 | [x] | `modules/Booking/Models/SessionProduct.php` | Extends BaseModel - auto-fixed |
| 106 | [x] | `modules/Booking/Models/BookingRule.php` | Extends BaseModel - auto-fixed |
| 107 | [x] | `modules/Booking/Models/BookingBlackoutDate.php` | Extends BaseModel - auto-fixed |
| 108 | [x] | `modules/Booking/Models/BookingConfig.php` | Extends BaseModel - auto-fixed |

### 3.6 Billing Module (6 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 109 | [x] | `modules/Billing/Models/Invoice.php` | Extends BaseModel - auto-fixed |
| 110 | [x] | `modules/Billing/Models/InvoiceLine.php` | Extends BaseModel - auto-fixed |
| 111 | [x] | `modules/Billing/Models/Payment.php` | Extends BaseModel - auto-fixed |
| 112 | [x] | `modules/Billing/Models/TaxRate.php` | Extends BaseModel - auto-fixed |
| 113 | [x] | `modules/Billing/Models/InstallmentPlan.php` | Extends BaseModel - auto-fixed |
| 114 | [x] | `modules/Billing/Models/InstallmentSchedule.php` | Extends BaseModel - auto-fixed |

### 3.7 Accounting Module (5 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 115 | [x] | `modules/Accounting/Models/ChartOfAccount.php` | Extends BaseModel - auto-fixed |
| 116 | [x] | `modules/Accounting/Models/Journal.php` | Extends BaseModel - auto-fixed |
| 117 | [x] | `modules/Accounting/Models/JournalEntry.php` | Extends BaseModel - auto-fixed |
| 118 | [x] | `modules/Accounting/Models/JournalEntryLine.php` | Extends BaseModel - auto-fixed |
| 119 | [x] | `modules/Accounting/Models/FiscalPeriod.php` | Extends BaseModel - auto-fixed |

### 3.8 Staff Module (2 models - remaining after explicit casts)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 120 | [x] | `modules/Staff/Models/CommissionPlan.php` | Extends BaseModel - auto-fixed |
| 121 | [x] | `modules/Staff/Models/CommissionPlanRule.php` | Extends BaseModel - auto-fixed |

### 3.9 Equipment Module (6 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 122 | [x] | `modules/Equipment/Models/EquipmentType.php` | Extends BaseModel - auto-fixed |
| 123 | [x] | `modules/Equipment/Models/Equipment.php` | Extends BaseModel - auto-fixed |
| 124 | [x] | `modules/Equipment/Models/EquipmentMaintenanceLog.php` | Extends BaseModel - auto-fixed |
| 125 | [x] | `modules/Equipment/Models/EquipmentShotLog.php` | Extends BaseModel - auto-fixed |
| 126 | [x] | `modules/Equipment/Models/EquipmentTrackingParameter.php` | Extends BaseModel - auto-fixed |
| 127 | [x] | `modules/Equipment/Models/EquipmentParameterTemplate.php` | Extends BaseModel - auto-fixed |

### 3.10 GiftCards Module (5 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 128 | [x] | `modules/GiftCards/Models/GiftCard.php` | Extends BaseModel - auto-fixed |
| 129 | [x] | `modules/GiftCards/Models/GiftCardTransaction.php` | Extends BaseModel - auto-fixed |
| 130 | [x] | `modules/GiftCards/Models/GiftCardTemplate.php` | Extends BaseModel - auto-fixed |
| 131 | [x] | `modules/GiftCards/Models/GiftCardPrintHistory.php` | Extends BaseModel - auto-fixed |
| 132 | [x] | `modules/GiftCards/Models/GiftCardBatchExport.php` | Extends BaseModel - auto-fixed |

### 3.11 Packages Module (4 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 133 | [x] | `modules/Packages/Models/Package.php` | Extends BaseModel - auto-fixed |
| 134 | [x] | `modules/Packages/Models/PackageItem.php` | Extends BaseModel - auto-fixed |
| 135 | [x] | `modules/Packages/Models/PackageSubscription.php` | Extends BaseModel - auto-fixed |
| 136 | [x] | `modules/Packages/Models/PackageSessionUsage.php` | Extends BaseModel - auto-fixed |

### 3.12 Memberships Module (2 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 137 | [x] | `modules/Memberships/Models/Membership.php` | Extends BaseModel - auto-fixed |
| 138 | [x] | `modules/Memberships/Models/MembershipSubscription.php` | Extends BaseModel - auto-fixed |

### 3.13 Loyalty Module (4 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 139 | [x] | `modules/Loyalty/Models/LoyaltyRule.php` | Extends BaseModel - auto-fixed |
| 140 | [x] | `modules/Loyalty/Models/LoyaltyTransaction.php` | Extends BaseModel - auto-fixed |
| 141 | [x] | `modules/Loyalty/Models/ReferralProgram.php` | Extends BaseModel - auto-fixed |
| 142 | [x] | `modules/Loyalty/Models/Referral.php` | Extends BaseModel - auto-fixed |

### 3.14 Marketing Module (5 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 143 | [x] | `modules/Marketing/Models/MessageTemplate.php` | Extends BaseModel - auto-fixed |
| 144 | [x] | `modules/Marketing/Models/Campaign.php` | Extends BaseModel - auto-fixed |
| 145 | [x] | `modules/Marketing/Models/CampaignRecipient.php` | Extends BaseModel - auto-fixed |
| 146 | [x] | `modules/Marketing/Models/NotificationLog.php` | Extends BaseModel - auto-fixed |
| 147 | [x] | `modules/Marketing/Models/AutomationRule.php` | Extends BaseModel - auto-fixed |

### 3.15 TreatmentPlans Module (3 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 148 | [x] | `modules/TreatmentPlans/Models/TreatmentPlan.php` | Extends BaseModel - auto-fixed |
| 149 | [x] | `modules/TreatmentPlans/Models/TreatmentPlanItem.php` | Extends BaseModel - auto-fixed |
| 150 | [x] | `modules/TreatmentPlans/Models/TreatmentPlanAppointment.php` | Extends BaseModel - auto-fixed |

### 3.16 Prescriptions Module (3 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 151 | [x] | `modules/Prescriptions/Models/Prescription.php` | Extends BaseModel - auto-fixed |
| 152 | [x] | `modules/Prescriptions/Models/PrescriptionItem.php` | Extends BaseModel - auto-fixed |
| 153 | [x] | `modules/Prescriptions/Models/MedicineCatalog.php` | Extends BaseModel - auto-fixed |

### 3.17 Assets Module (3 models)
| # | Status | File | Changes Required |
|---|--------|------|------------------|
| 154 | [x] | `modules/Assets/Models/AssetType.php` | Extends BaseModel - auto-fixed |
| 155 | [x] | `modules/Assets/Models/Asset.php` | Extends BaseModel - auto-fixed |
| 156 | [x] | `modules/Assets/Models/AssetDepreciationEntry.php` | Extends BaseModel - auto-fixed |

---

## Phase 4: Migrations - Central Database (database/migrations/) ✅ COMPLETED

**Connection:** Central/Platform database
**Order:** Run these BEFORE tenant migrations

| # | Status | File | Primary Key | Foreign Keys |
|---|--------|------|-------------|--------------|
| 157 | [x] | `2025_02_19_000001_create_onboarding_requests_table.php` | `uuid()` → `id()` | tenant_id |
| 158 | [x] | `2025_02_19_000002_create_tenant_domains_table.php` | `uuid()` → `id()` | tenant_id |
| 159 | [x] | `2025_02_19_000003_create_email_templates_table.php` | `uuid()` → `id()` | - |
| 160 | [x] | `2025_02_19_000004_create_system_alerts_table.php` | `uuid()` → `id()` | - |
| 161 | [x] | `2025_02_19_000005_create_platform_settings_table.php` | `uuid()` → `id()` | - |
| 162 | [x] | `2026_02_18_180200_create_audits_table.php` | `uuid()` → `id()` | user_id, morphs → nullableMorphs |
| 163 | [x] | `2026_02_19_023928_create_activity_log_table.php` | Keep id() | nullableUuidMorphs → nullableMorphs |
| 164 | [x] | `2026_02_19_023930_add_batch_uuid_column_to_activity_log_table.php` | - | Keep batch_uuid as UUID |
| 165 | [x] | `2026_02_19_100001_create_subscription_plans_table.php` | `uuid()` → `id()` | - |
| 166 | [x] | `2026_02_19_100002_create_modules_table.php` | `uuid()` → `id()` | - |
| 167 | [x] | `2026_02_19_100003_create_platform_invoices_table.php` | `uuid()` → `id()` | tenant_id, subscription_plan_id |
| 168 | [x] | `2026_02_19_100004_create_tenant_addon_subscriptions_table.php` | `uuid()` → `id()` | tenant_id, add_on_id |
| 169 | [x] | `2026_02_19_100005_create_tenant_activity_logs_table.php` | `uuid()` → `id()` | tenant_id, user_id |
| 170 | [x] | `2026_02_19_100006_create_support_tickets_table.php` | `uuid()` → `id()` | tenant_id, user_id |
| 171 | [x] | `2026_02_19_100007_create_promo_codes_table.php` | `uuid()` → `id()` | - |
| 172 | [x] | `2026_02_19_100008_create_announcements_table.php` | `uuid()` → `id()` | - |
| 173 | [x] | `2026_02_19_140934_create_notifications_table.php` | `uuid()` → `id()` | morphs → nullableMorphs |
| 174 | [x] | `2026_02_19_152557_create_backups_table.php` | `uuid()` → `id()` | tenant_id |
| 175 | [x] | `2026_02_19_163357_create_add_ons_table.php` | `uuid()` → `id()` | - |
| 176 | [x] | `2026_02_20_030000_create_restore_requests_table.php` | `uuid()` → `id()` | tenant_id, backup_id |
| 177 | [x] | `2026_02_20_153459_create_contact_inquiries_table.php` | `uuid()` → `id()` | - |
| 178 | [x] | `2026_02_26_081005_create_import_mappings_table.php` | `uuid()` → `id()` | - |
| 179 | [x] | `2026_02_26_110156_create_imports_table.php` | Keep as-is | user_id |

---

## Phase 5: Migrations - Tenant Database (modules/*/Database/Migrations/) ✅ COMPLETED

**Connection:** Tenant schemas
**Order:** Run in dependency order within each module

### 5.1 Core Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 180 | [x] | `modules/Core/Database/Migrations/2024_01_01_000000_create_branches_table.php` | `uuid()` → `id()`, tenant_id to INT |
| 181 | [x] | `modules/Core/Database/Migrations/2024_01_01_000001_create_tenants_table.php` | `uuid()` → `id()` |
| 182 | [x] | `modules/Core/Database/Migrations/2024_01_01_000001_create_sequences_table.php` | `uuid()` → `id()` |
| 183 | [x] | `modules/Core/Database/Migrations/2024_01_01_000002_create_tenant_subscriptions_table.php` | `uuid()` → `id()` |
| 184 | [x] | `modules/Core/Database/Migrations/2024_01_01_000003_create_tenant_usage_table.php` | `uuid()` → `id()` |
| 185 | [x] | `modules/Core/Database/Migrations/2024_01_01_000006_create_rooms_table.php` | `uuid()` → `id()` |
| 186 | [x] | `modules/Core/Database/Migrations/2024_01_01_000008_create_settings_table.php` | `uuid()` → `id()` |
| 187 | [x] | `modules/Core/Database/Migrations/2024_01_01_000009_create_activities_table.php` | `uuid()` → `id()`, nullableUuidMorphs → nullableMorphs |
| 188 | [x] | `modules/Core/Database/Migrations/2024_01_01_000010_create_audit_logs_table.php` | `uuid()` → `id()` |
| 189 | [x] | `modules/Core/Database/Migrations/2024_01_01_000011_create_audits_table.php` | `uuid()` → `id()` |
| 190 | [x] | `modules/Core/Database/Migrations/2024_01_01_000020_create_user_branch_roles_table.php` | `uuid()` → `id()` |
| 191 | [x] | `modules/Core/Database/Migrations/2026_02_21_000001_alter_activities_table_uuid_morphs.php` | **DELETE** this file |

### 5.2 Auth Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 192 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000001_create_permission_tables.php` | model_morph_key: uuid() → unsignedBigInteger() |
| 193 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000002_create_users_table.php` | `uuid()` → `id()` |
| 194 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000003_create_user_sessions_table.php` | `uuid()` → `id()` |
| 195 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000004_create_login_history_table.php` | `uuid()` → `id()` |
| 196 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000005_create_password_history_table.php` | `uuid()` → `id()` |
| 197 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000006_create_user_profiles_table.php` | `uuid()` → `id()` |
| 198 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000007_create_access_policies_table.php` | `uuid()` → `id()` |
| 199 | [x] | `modules/Auth/Database/Migrations/0001_01_01_000008_add_impersonation_to_users_table.php` | user_id to INT |

### 5.3 Patients Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 200 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000001_create_patients_table.php` | `uuid()` → `id()` |
| 201 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000002_create_patient_medical_histories_table.php` | `uuid()` → `id()` |
| 202 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000003_create_patient_consent_forms_table.php` | `uuid()` → `id()` |
| 203 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000004_create_patient_photos_table.php` | `uuid()` → `id()` |
| 204 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000005_create_media_table.php` | `uuid()` → `id()`, morphs |
| 205 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000005_create_patient_notes_table.php` | `uuid()` → `id()` |
| 206 | [x] | `modules/Patients/Database/Migrations/2024_01_01_000010_add_patient_auth_fields.php` | Review |
| 207 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000001_create_medical_profiles_table.php` | `uuid()` → `id()` |
| 208 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000002_create_medical_allergies_table.php` | `uuid()` → `id()` |
| 209 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000003_create_medical_medications_table.php` | `uuid()` → `id()` |
| 210 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000004_create_medical_contraindications_table.php` | `uuid()` → `id()` |
| 211 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000005_create_medical_histories_table.php` | `uuid()` → `id()` |
| 212 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000006_create_skin_assessments_table.php` | `uuid()` → `id()` |
| 213 | [x] | `modules/Patients/Database/Migrations/2024_01_20_000007_create_lifestyle_info_table.php` | `uuid()` → `id()` |
| 214 | [x] | `modules/Patients/Database/Migrations/2026_02_25_000001_create_patient_amr_tests_table.php` | `uuid()` → `id()` |
| 215 | [x] | `modules/Patients/Database/Migrations/2026_02_25_000002_create_patient_amr_summaries_table.php` | `uuid()` → `id()` |

### 5.4 Services Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 216 | [x] | `modules/Services/Database/Migrations/2024_01_01_000001_create_services_tables.php` | `uuid()` → `id()` |
| 217 | [x] | `modules/Services/Database/Migrations/2024_01_15_000001_rename_treatments_to_services.php` | Review/update |
| 218 | [x] | `modules/Services/Database/Migrations/2024_01_20_000001_create_service_qualified_staff_table.php` | `uuid()` → `id()` |
| 219 | [x] | `modules/Services/Database/Migrations/2024_01_20_000002_create_service_rooms_table.php` | `uuid()` → `id()` |
| 220 | [x] | `modules/Services/Database/Migrations/2024_01_20_000003_create_service_required_equipment_table.php` | `uuid()` → `id()` |
| 221 | [x] | `modules/Services/Database/Migrations/2026_02_24_000001_create_parameter_templates_table.php` | `uuid()` → `id()` |
| 222 | [x] | `modules/Services/Database/Migrations/2026_02_24_000002_create_service_parameters_table.php` | `uuid()` → `id()` |
| 223 | [x] | `modules/Services/Database/Migrations/2026_02_24_000003_create_parameter_presets_table.php` | `uuid()` → `id()` |

### 5.5 Booking Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 224 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000010_create_appointments_table.php` | `uuid()` → `id()` |
| 225 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000011_create_appointment_treatment_notes_table.php` | `uuid()` → `id()` |
| 226 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000012_create_practitioner_schedules_table.php` | `uuid()` → `id()` |
| 227 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000013_create_practitioner_time_off_table.php` | `uuid()` → `id()` |
| 228 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000014_create_waitlist_table.php` | `uuid()` → `id()` |
| 229 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000020_create_time_off_types_table.php` | `uuid()` → `id()` |
| 230 | [x] | `modules/Booking/Database/Migrations/2024_01_01_000021_create_time_off_allocations_table.php` | `uuid()` → `id()` |
| 231 | [x] | `modules/Booking/Database/Migrations/2026_02_24_000001_create_treatment_session_data_table.php` | `uuid()` → `id()` |
| 232 | [x] | `modules/Booking/Database/Migrations/2026_02_24_000002_create_session_consumables_table.php` | `uuid()` → `id()` |
| 233 | [x] | `modules/Booking/Database/Migrations/2026_02_24_000003_create_session_products_table.php` | `uuid()` → `id()` |
| 234 | [x] | `modules/Booking/Database/Migrations/2026_02_25_000001_create_booking_rules_table.php` | `uuid()` → `id()` |
| 235 | [x] | `modules/Booking/Database/Migrations/2026_02_25_000002_create_booking_blackout_dates_table.php` | `uuid()` → `id()` |
| 236 | [x] | `modules/Booking/Database/Migrations/2026_02_25_000010_create_booking_configs_table.php` | `uuid()` → `id()` |

### 5.6 Billing Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 237 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000001_create_tax_rates_table.php` | `uuid()` → `id()` |
| 238 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000002_create_invoices_table.php` | `uuid()` → `id()` |
| 239 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000003_create_invoice_lines_table.php` | `uuid()` → `id()` |
| 240 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000004_create_payments_table.php` | `uuid()` → `id()` |
| 241 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000005_create_installment_plans_table.php` | `uuid()` → `id()` |
| 242 | [x] | `modules/Billing/Database/Migrations/2024_01_01_000006_create_installment_schedules_table.php` | `uuid()` → `id()` |
| 243 | [x] | `modules/Billing/Database/Migrations/2024_02_01_000001_add_unassigned_payment_support.php` | Review |

### 5.7 Accounting Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 244 | [x] | `modules/Accounting/Database/Migrations/2024_01_01_000000_create_chart_of_accounts_table.php` | `uuid()` → `id()` |
| 245 | [x] | `modules/Accounting/Database/Migrations/2024_01_02_000002_create_fiscal_periods_table.php` | `uuid()` → `id()` |
| 246 | [x] | `modules/Accounting/Database/Migrations/2024_01_02_000003_create_journal_entries_table.php` | `uuid()` → `id()` |
| 247 | [x] | `modules/Accounting/Database/Migrations/2024_01_02_000004_create_journal_entry_lines_table.php` | `uuid()` → `id()` |
| 248 | [x] | `modules/Accounting/Database/Migrations/2024_01_02_000005_create_journals_table.php` | `uuid()` → `id()` |

### 5.8 Inventory Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 249 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000001_create_product_categories_table.php` | `uuid()` → `id()` |
| 250 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000002_create_products_table.php` | `uuid()` → `id()` |
| 251 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000003_create_stock_levels_table.php` | `uuid()` → `id()` |
| 252 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000004_create_stock_movements_table.php` | `uuid()` → `id()` |
| 253 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000005_create_suppliers_table.php` | `uuid()` → `id()` |
| 254 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000006_create_purchase_orders_table.php` | `uuid()` → `id()` |
| 255 | [x] | `modules/Inventory/Database/Migrations/2024_01_01_000007_create_purchase_order_lines_table.php` | `uuid()` → `id()` |
| 256 | [x] | `modules/Inventory/Database/Migrations/2024_01_20_000002_create_inventory_adjustments_table.php` | `uuid()` → `id()` |
| 257 | [x] | `modules/Inventory/Database/Migrations/2026_02_23_220000_create_vendor_bills_tables.php` | `uuid()` → `id()` |

### 5.9 Staff Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 258 | [x] | `modules/Staff/Database/Migrations/2024_01_01_000000_create_staff_profiles_table.php` | `uuid()` → `id()` |
| 259 | [x] | `modules/Staff/Database/Migrations/2024_01_01_000003_create_staff_commission_records_table.php` | `uuid()` → `id()` |
| 260 | [x] | `modules/Staff/Database/Migrations/2024_01_01_000004_create_working_schedules_table.php` | `uuid()` → `id()` |
| 261 | [x] | `modules/Staff/Database/Migrations/2024_01_01_000009_create_staff_commissions_table.php` | `uuid()` → `id()` |
| 262 | [x] | `modules/Staff/Database/Migrations/2024_01_15_000001_create_commission_plans_table.php` | `uuid()` → `id()` |

### 5.10 Attendance Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 263 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000001_create_attendances_table.php` | `uuid()` → `id()` |
| 264 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000002_create_attendance_logs_table.php` | `uuid()` → `id()` |
| 265 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000003_create_attendance_breaks_table.php` | `uuid()` → `id()` |
| 266 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000005_create_attendance_rules_table.php` | `uuid()` → `id()` |
| 267 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000006_create_attendance_rule_actions_table.php` | `uuid()` → `id()` |
| 268 | [x] | `modules/Attendance/Database/Migrations/2024_01_01_000007_create_attendance_violations_table.php` | `uuid()` → `id()` |
| 269 | [x] | `modules/Attendance/Database/Migrations/2024_01_15_000001_create_attendance_type_settings_table.php` | `uuid()` → `id()` |
| 270 | [x] | `modules/Attendance/Database/Migrations/2026_02_21_000001_create_work_schedules_table.php` | `uuid()` → `id()` |

### 5.11 Payroll Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 271 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000001_create_payroll_runs_table.php` | `uuid()` → `id()` |
| 272 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000002_create_payroll_lines_table.php` | `uuid()` → `id()` |
| 273 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000003_create_salary_rule_categories_table.php` | `uuid()` → `id()` |
| 274 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000004_create_salary_rules_table.php` | `uuid()` → `id()` |
| 275 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000005_create_salary_structures_table.php` | `uuid()` → `id()` |
| 276 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000006_create_employee_salary_structures_table.php` | `uuid()` → `id()` |
| 277 | [x] | `modules/Payroll/Database/Migrations/2024_01_01_000007_create_employee_salary_components_table.php` | `uuid()` → `id()` |

### 5.12 Equipment Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 278 | [x] | `modules/Equipment/Database/Migrations/2024_01_01_000001_create_equipment_types_table.php` | `uuid()` → `id()` |
| 279 | [x] | `modules/Equipment/Database/Migrations/2024_01_01_000007_create_equipment_table.php` | `uuid()` → `id()` |
| 280 | [x] | `modules/Equipment/Database/Migrations/2024_01_01_000008_create_equipment_maintenance_logs_table.php` | `uuid()` → `id()` |
| 281 | [x] | `modules/Equipment/Database/Migrations/2024_01_01_000009_create_equipment_shot_logs_table.php` | `uuid()` → `id()` |
| 282 | [x] | `modules/Equipment/Database/Migrations/2024_01_01_000010_create_service_equipment_requirements_table.php` | `uuid()` → `id()` |
| 283 | [x] | `modules/Equipment/Database/Migrations/2026_02_24_100001_create_equipment_tracking_parameters_table.php` | `uuid()` → `id()` |
| 284 | [x] | `modules/Equipment/Database/Migrations/2026_02_24_100003_create_equipment_parameter_templates_table.php` | `uuid()` → `id()` |

### 5.13 GiftCards Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 285 | [x] | `modules/GiftCards/Database/Migrations/2024_01_01_000001_create_gift_card_templates_table.php` | `uuid()` → `id()` |
| 286 | [x] | `modules/GiftCards/Database/Migrations/2024_01_01_000002_create_gift_cards_table.php` | `uuid()` → `id()` |
| 287 | [x] | `modules/GiftCards/Database/Migrations/2024_01_01_000003_create_gift_card_transactions_table.php` | `uuid()` → `id()` |
| 288 | [x] | `modules/GiftCards/Database/Migrations/2024_01_01_000004_create_gift_card_print_histories_table.php` | `uuid()` → `id()` |
| 289 | [x] | `modules/GiftCards/Database/Migrations/2024_01_01_000005_create_gift_card_batch_exports_table.php` | `uuid()` → `id()` |

### 5.14 Packages Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 290 | [x] | `modules/Packages/Database/Migrations/2024_01_01_000007_create_packages_table.php` | `uuid()` → `id()` |
| 291 | [x] | `modules/Packages/Database/Migrations/2024_01_01_000009_create_package_items_table.php` | `uuid()` → `id()` |
| 292 | [x] | `modules/Packages/Database/Migrations/2024_01_01_000010_create_package_subscriptions_table.php` | `uuid()` → `id()` |
| 293 | [x] | `modules/Packages/Database/Migrations/2024_01_01_000011_create_package_session_usages_table.php` | `uuid()` → `id()` |

### 5.15 Memberships Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 294 | [x] | `modules/Memberships/Database/Migrations/2024_01_01_000001_create_memberships_table.php` | `uuid()` → `id()` |
| 295 | [x] | `modules/Memberships/Database/Migrations/2024_01_01_000002_create_membership_subscriptions_table.php` | `uuid()` → `id()` |

### 5.16 Loyalty Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 296 | [x] | `modules/Loyalty/Database/Migrations/2024_01_01_000008_create_loyalty_transactions_table.php` | `uuid()` → `id()` |
| 297 | [x] | `modules/Loyalty/Database/Migrations/2024_01_01_000009_create_loyalty_rules_table.php` | `uuid()` → `id()` |
| 298 | [x] | `modules/Loyalty/Database/Migrations/2024_01_01_000010_create_referral_programs_table.php` | `uuid()` → `id()` |
| 299 | [x] | `modules/Loyalty/Database/Migrations/2024_01_01_000011_create_referrals_table.php` | `uuid()` → `id()` |

### 5.17 Marketing Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 300 | [x] | `modules/Marketing/Database/Migrations/2024_01_01_000001_create_message_templates_table.php` | `uuid()` → `id()` |
| 301 | [x] | `modules/Marketing/Database/Migrations/2024_01_01_000002_create_campaigns_table.php` | `uuid()` → `id()` |
| 302 | [x] | `modules/Marketing/Database/Migrations/2024_01_01_000003_create_campaign_recipients_table.php` | `uuid()` → `id()` |
| 303 | [x] | `modules/Marketing/Database/Migrations/2024_01_01_000004_create_notification_logs_table.php` | `uuid()` → `id()` |
| 304 | [x] | `modules/Marketing/Database/Migrations/2024_01_01_000005_create_automation_rules_table.php` | `uuid()` → `id()` |

### 5.18 TreatmentPlans Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 305 | [x] | `modules/TreatmentPlans/Database/Migrations/2024_01_01_000001_create_treatment_plans_table.php` | `uuid()` → `id()` |
| 306 | [x] | `modules/TreatmentPlans/Database/Migrations/2024_01_01_000002_create_treatment_plan_items_table.php` | `uuid()` → `id()` |
| 307 | [x] | `modules/TreatmentPlans/Database/Migrations/2024_01_01_000003_create_treatment_plan_appointments_table.php` | `uuid()` → `id()` |

### 5.19 Prescriptions Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 308 | [x] | `modules/Prescriptions/Database/Migrations/2024_01_01_000001_create_medicine_catalog_table.php` | `uuid()` → `id()` |
| 309 | [x] | `modules/Prescriptions/Database/Migrations/2024_01_01_000002_create_prescriptions_table.php` | `uuid()` → `id()` |
| 310 | [x] | `modules/Prescriptions/Database/Migrations/2024_01_01_000003_create_prescription_items_table.php` | `uuid()` → `id()` |

### 5.20 Assets Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 311 | [x] | `modules/Assets/Database/Migrations/2024_01_01_000001_create_asset_types_table.php` | `uuid()` → `id()` |
| 312 | [x] | `modules/Assets/Database/Migrations/2024_01_01_000002_create_assets_table.php` | `uuid()` → `id()` |
| 313 | [x] | `modules/Assets/Database/Migrations/2024_01_01_000003_create_asset_depreciation_entries_table.php` | `uuid()` → `id()` |

### 5.21 PatientPortal Module Migrations ✅ COMPLETED
| # | Status | File | Changes |
|---|--------|------|---------|
| 314 | [x] | `modules/PatientPortal/Database/Migrations/2024_01_01_000001_create_portal_settings_table.php` | `uuid()` → `id()` |

---

## Phase 6: Services & Actions ✅ COMPLETED

| # | Status | File | Changes |
|---|--------|------|---------|
| 315 | [x] | `modules/Core/Services/TenantService.php` | No UUID found - already clean |
| 316 | [x] | `modules/GiftCards/Services/GiftCardService.php` | No UUID found - already clean |
| 317 | [x] | `app/Services/ExportService.php` | No UUID found - already clean |
| 318 | [x] | `app/Filament/Actions/ImportTableAction.php` | No UUID found - already clean |
| 319 | [x] | `app/Filament/Actions/ExportTableAction.php` | No UUID found - already clean |
| 320 | [x] | `app/Console/Commands/TenantCreate.php` | Removed UUID, using insertGetId |

---

## Phase 7: Seeders ✅ COMPLETED

All seeders reviewed and fixed where UUID generation was found.

| # | Status | File | Changes |
|---|--------|------|---------|
| 321 | [x] | `modules/Core/Database/Seeders/DefaultBranchSeeder.php` | No UUID found - uses model create |
| 322 | [x] | `modules/Core/Database/Seeders/DefaultSettingsSeeder.php` | No UUID found - uses model create |
| 323 | [x] | `modules/Core/Database/Seeders/DefaultSequenceSeeder.php` | No UUID found - uses model create |
| 324 | [x] | `modules/Core/Database/Seeders/DemoDataSeeder.php` | No UUID found - uses model create |
| 325 | [x] | `modules/Core/Database/Seeders/DemoPatientSeeder.php` | No UUID found - uses model create |
| 326 | [x] | `modules/Core/Database/Seeders/DemoAppointmentSeeder.php` | No UUID found - uses model create |
| 327 | [x] | `modules/Core/Database/Seeders/DemoInvoiceSeeder.php` | No UUID found - uses model create |
| 328 | [x] | `modules/Core/Database/Seeders/DemoServiceSeeder.php` | No UUID found - uses model create |
| 329 | [x] | `modules/Auth/Database/Seeders/AuthModuleSeeder.php` | No UUID found - uses model create |
| 330 | [x] | `modules/Accounting/Database/Seeders/ChartOfAccountsSeeder.php` | Fixed - removed UUID from create() |
| 331 | [x] | `modules/Services/Database/Seeders/ServicesModuleSeeder.php` | No UUID found - uses model create |
| 332 | [x] | `modules/Services/Database/Seeders/ParameterTemplatesSeeder.php` | No UUID found - uses model create |
| 333 | [x] | `modules/Staff/Database/Seeders/CommissionPlanSeeder.php` | Fixed - removed UUID from insert |
| 334 | [x] | `modules/Payroll/Database/Seeders/PayrollDefaultsSeeder.php` | Fixed - using insertGetId |
| 335 | [x] | `modules/Loyalty/Database/Seeders/LoyaltySeeder.php` | No UUID found - uses model create |
| 336 | [x] | `modules/Assets/Database/Seeders/AssetTypeSeeder.php` | No UUID found - uses model create |
| 337 | [x] | `modules/Billing/Database/Seeders/TaxRateSeeder.php` | Fixed - removed UUID from insert |

---

## Phase 8: Filament & UI Components ✅ COMPLETED

| # | Status | File | Changes |
|---|--------|------|---------|
| 338 | [x] | `app/Filament/SuperAdmin/Resources/TenantResource/Pages/ViewTenant.php` | Fixed - removed UUID from pivot attach |
| 339 | [x] | `app/Filament/SuperAdmin/Resources/ModuleResource/Pages/ListModules.php` | Fixed - removed UUID from raw inserts |
| 340 | [x] | `modules/Booking/Filament/Pages/BookingSlotConfigPage.php` | No UUID found - already clean |
| 341 | [x] | All Filament Resources | BaseModel auto-increment handles ID correctly |

---

## Phase 9: Testing & Verification ✅ COMPLETED

| # | Status | Task |
|---|--------|------|
| 340 | [x] | Commit changes on staging branch |
| 341 | [x] | Fix HasPostgresBoolean trait - schema-qualified table names |
| 342 | [x] | Run `php artisan migrate:fresh` - 175 migrations SUCCESS |
| 343 | [x] | Verify all platform migrations pass |
| 344 | [x] | Verify all 175 tenant migrations pass |
| 345 | [x] | Run all seeders successfully (4 seeders) |
| 346 | [x] | Test tenant creation - ID: 1 (integer) |
| 347 | [x] | Test CRUD operations - Patient creation SUCCESS |
| 348 | [x] | Test foreign key relationships - tenant_id works |
| 349 | [x] | Test Spatie Permission role/permission assignment - SUCCESS |
| 350 | [x] | Verify audit records use integer IDs |
| 351 | [x] | Fix BaseModel and HasPostgresBoolean RETURNING clause |

### Phase 9 Additional Fixes Applied:
- Deleted 12 redundant migrations (columns already merged)
- Fixed migration order for subscription_plans and treatment_session_data
- Fixed HasPostgresBoolean to use RETURNING for ID retrieval
- Fixed BaseModel to use RETURNING for ID retrieval (audit compatibility)

---

## Quick Reference: Column Type Changes

### Primary Keys
```php
// OLD
$table->uuid('id')->primary();

// NEW
$table->id();  // Creates BIGINT auto-increment
```

### Foreign Keys
```php
// OLD
$table->uuid('tenant_id');
$table->foreignUuid('patient_id')->constrained();

// NEW
$table->unsignedBigInteger('tenant_id');
$table->foreignId('patient_id')->constrained();
```

### Morphs (Polymorphic Relations)
```php
// OLD
$table->uuidMorphs('taggable');
$table->nullableUuidMorphs('subject');

// NEW
$table->morphs('taggable');
$table->nullableMorphs('subject');
```

### In Models - BaseModel
```php
// OLD (BaseModel)
protected $keyType = 'string';
public $incrementing = false;
protected $casts = ['id' => 'string', ...];
protected static function booted() {
    static::creating(fn($model) => $model->id = Str::orderedUuid()->toString());
}

// NEW (BaseModel)
// Remove ALL of the above - let Laravel use defaults
// $keyType defaults to 'int'
// $incrementing defaults to true
// No need to cast 'id'
// No need to generate ID in booted()
```

### In Models - Explicit Casts Removal
```php
// OLD
protected $casts = [
    'id' => 'string',  // REMOVE THIS LINE
    'is_active' => 'boolean',
    // ... other casts
];

// NEW
protected $casts = [
    'is_active' => 'boolean',
    // ... other casts (NO 'id' => 'string')
];
```

---

## Progress Summary

| Phase | Total Items | Completed | Remaining |
|-------|-------------|-----------|-----------|
| Decisions | 6 | 6 | 0 |
| 0. Framework | 7 | 7 | 0 |
| 1. App Models | 20 | 20 | 0 |
| 2. Models (Explicit Casts) | 31 | 31 | 0 |
| 3. Models (Verify) | 98 | 98 | 0 |
| 4. Migrations (Central) | 23 | 23 | 0 |
| 5. Migrations (Tenant) | 135 | 135 | 0 |
| 6. Services | 6 | 6 | 0 |
| 7. Seeders | 17 | 17 | 0 |
| 8. Filament/UI | 4 | 4 | 0 |
| 9. Testing | 12 | 12 | 0 |
| **TOTAL** | **359** | **359** | **0** |

## ✅ MIGRATION COMPLETE

**Final Results:**
- 175 migrations executed successfully
- 4 seeders completed
- All ID columns now use auto-increment BIGINT
- All foreign keys use BIGINT references
- Spatie Permissions work with integer model IDs
- Audit system properly records integer auditable_id
- Tenant-scoped models use integer tenant_id

---

## Notes

- Keep `batch_uuid` in activity log (used for grouping, not as PK)
- Spatie Permission tables use BIGINT by default for role_id/permission_id - keep as-is
- Spatie Permission model_morph_key MUST change from uuid() to unsignedBigInteger()
- Run migrations in order: Central → Tenant
- After completion, run benchmark to verify performance improvement
- 31 models have explicit `'id' => 'string'` casts that MUST be removed
