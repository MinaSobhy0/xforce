# LaserBase — Tenant Modules Implementation Plan
## Stack: Schema-per-tenant with PgBouncer (keeping as-is)
## You have: Super Admin panel + Framework kernel done
## Building: All clinic-side modules

---

# PANEL ARCHITECTURE

The system has **THREE separate Filament panels**:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. SUPERADMIN PANEL                                                          │
│    URL: sys.x-linic.com/platform                                            │
│    Provider: SuperAdminPanelProvider                                         │
│    Purpose: Platform owner manages ALL tenants                               │
│    Features:                                                                 │
│    ├── Tenant management (create, edit, suspend clinics)                    │
│    ├── Subscription plans management                                         │
│    ├── Platform-wide settings                                               │
│    ├── System monitoring                                                     │
│    └── Support tickets (all tenants)                                        │
│    Auth: Platform administrators only                                        │
│    Database: public schema                                                   │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│ 2. CLINIC OWNER PORTAL                                                       │
│    URL: sys.x-linic.com/admin                                               │
│    Provider: AdminPanelProvider                                              │
│    Purpose: Clinic owners manage their SUBSCRIPTION (not clinic data)        │
│    Features:                                                                 │
│    ├── Dashboard (subscription status)                                       │
│    ├── My Subscription (view plan, upgrade)                                  │
│    ├── My Invoices (platform invoices for subscription)                      │
│    ├── My Support Tickets                                                    │
│    ├── My Backups                                                           │
│    └── Account Settings                                                      │
│    Auth: Clinic owners (tenant owners)                                       │
│    Database: public schema (reads tenant info only)                          │
│    NOTE: NO access to Patients, Treatments, Booking, etc.                    │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│ 3. TENANT SYSTEM (Clinic Management)                                         │
│    URL: {tenant-slug}.x-linic.com/admin                                     │
│    Provider: TenantPanelProvider (NEW)                                       │
│    Purpose: Clinic staff manages CLINIC OPERATIONS                           │
│    Features (modules):                                                       │
│    ├── Core: Branches, Rooms, Settings, Usage Dashboard                     │
│    ├── Auth: Users, Roles, Permissions, Access Policies                     │
│    ├── Patients: Patient CRM, Medical History, Consent Forms, Photos        │
│    ├── Treatments: Treatment Catalog, Categories, Consent Templates         │
│    ├── Booking: Appointments, Calendar, Schedules, Waitlist                 │
│    ├── Billing: Invoices, Payments, Installments                            │
│    ├── Accounting: Chart of Accounts, Journal Entries, Reports              │
│    ├── Packages, Gift Cards, Memberships                                    │
│    ├── Inventory, Staff, Payroll                                            │
│    ├── Marketing: WhatsApp, SMS, Email campaigns                            │
│    ├── Loyalty, Reporting                                                   │
│    └── Patient Portal, API                                                  │
│    Auth: Tenant users (staff with roles/permissions)                         │
│    Database: tenant_* schema (isolated per tenant)                           │
│    Middleware: IdentifyTenant (switches to tenant schema)                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Key Points:
1. **Clinic Owner Portal** (sys.x-linic.com/admin) = Subscription management ONLY
2. **Tenant System** (tenant.x-linic.com/admin) = Clinic operations (patients, treatments, etc.)
3. They are SEPARATE panels with SEPARATE providers
4. Tenant modules (Patients, Treatments, etc.) ONLY appear in Tenant System

---

# BUILD ORDER

```
BATCH 1 → Core + Auth           ✅ COMPLETED (branches, users, roles, permissions)
BATCH 2 → Patients + Treatments ✅ COMPLETED (CRM + treatment catalog)
BATCH 3 → Booking + Equipment   ✅ COMPLETED (appointments, calendar, machines)
BATCH 4 → Billing + Accounting  ✅ COMPLETED (invoices, payments, double-entry)
BATCH 5 → Packages + Gift Cards + Memberships ✅ COMPLETED (sales add-ons)
BATCH 6 → Inventory + Staff + Payroll ✅ COMPLETED (supply chain + HR)
BATCH 7 → Marketing ✅ COMPLETED (WhatsApp, SMS, Email unified)
BATCH 8 → Loyalty + Reporting   (points, analytics, exports) ← NEXT
BATCH 8 → Loyalty + Reporting   (points, analytics, exports)
BATCH 9 → Patient Portal + API  (self-service + REST)
```

Each batch: build → test → verify checkpoint → next batch.

---

# BATCH 1: CORE + AUTH MODULE
## What it does: Branches, rooms, users, roles, permissions, settings
## Dependencies: None (foundation for everything)

### Module: Core

**Models:**
```
modules/Core/
├── CoreManifest.php
├── CoreServiceProvider.php
├── Models/
│   ├── Branch.php
│   │   → BaseModel, HasTranslation, HasActivity
│   │   → Fields: name (jsonb), slug, address, phone, email, working_hours (jsonb),
│   │     google_maps_url, is_headquarters (bool), is_active, timezone, currency_code
│   │   → Relationships: rooms(), users(), appointments(), equipment()
│   │
│   ├── Room.php
│   │   → BaseModel, HasTranslation
│   │   → Fields: name (jsonb), branch_id (FK), capacity, is_active, sort_order
│   │   → Relationships: branch(), equipment(), appointments()
│   │
│   ├── Setting.php
│   │   → Fields: key (unique), value (text), group, type
│   │   → No BaseModel — simple key-value store
│   │
│   └── Sequence.php
│       → Fields: code (unique), prefix, next_value (int), padding (int),
│         reset_on_year (bool), current_year (int)
│       → Method: next() — with SELECT FOR UPDATE locking
│
├── Filament/
│   └── Resources/
│       ├── BranchResource.php
│       │   → List: name, address, rooms count, staff count, is_active toggle
│       │   → Form: translatable name, address, phone, email, working hours
│       │     (JSON editor — day × open/close times), Google Maps link, timezone
│       │   → Relation managers: RoomsRelationManager
│       │
│       └── RoomResource.php
│           → Inline under BranchResource as relation manager
│           → Table: name, capacity, equipment count, active toggle
│           → Form: translatable name, branch select, capacity, sort order
│
├── Filament/Pages/
│   ├── GeneralSettingsPage.php
│   │   → Unified settings built from SettingsRegistry
│   │   → Sections: General, Booking, Billing, Marketing (grouped)
│   │   → Each setting: label, input type, default, validation
│   │
│   ├── ModuleManagementPage.php
│   │   → Grid of available modules (from plan)
│   │   → Toggle on/off with dependency check
│   │   → Shows: module name, icon, description, status, dependencies
│   │   → Disabled modules show "Upgrade Plan" badge
│   │
│   └── UsageDashboardPage.php
│       → Progress bars: users, branches, patients, storage, etc.
│       → vs plan limits
│       → Monthly counters: appointments, WhatsApp, SMS, emails
│
├── Database/Migrations/ (tenant-level)
│   ├── create_branches_table.php
│   ├── create_rooms_table.php
│   ├── create_settings_table.php
│   ├── create_sequences_table.php
│   ├── create_activities_table.php      (for HasActivity across all modules)
│   └── create_audit_logs_table.php      (for HasAudit across all modules)
│
├── Database/Seeders/
│   ├── DefaultBranchSeeder.php          (create "Main Branch" on provision)
│   ├── DefaultSequenceSeeder.php        (PAT-, INV-, APT-, GC-, JE-, PO-)
│   └── DefaultSettingsSeeder.php        (all default setting values)
│
└── Lang/
    ├── en/core.php
    └── ar/core.php
```

### Module: Auth

**Models:**
```
modules/Auth/
├── AuthManifest.php
├── AuthServiceProvider.php
├── Models/
│   ├── User.php
│   │   → Extends Authenticatable + uses BaseModel traits (HasActivity, HasSequence)
│   │   → Fields: name, email, phone, password, avatar_url, branch_id (FK),
│   │     locale (en/ar), is_active, last_login_at, email_verified_at,
│   │     two_factor_enabled, two_factor_secret
│   │   → Uses spatie/laravel-permission for roles
│   │   → Relationships: branch(), roles(), activities()
│   │   → Searchable via Meilisearch
│   │
│   └── AccessPolicy.php
│       → Odoo ir.rule equivalent
│       → Fields: name, model_class, domain_filter (jsonb), role_id (FK),
│         perm_read, perm_write, perm_create, perm_delete (booleans)
│       → Example: Receptionist can only see patients in their branch
│         domain_filter: {"branch_id": "{user.branch_id}"}
│
├── Filament/Resources/
│   ├── UserResource.php
│   │   → List: name, email, role badges, branch, last login, active toggle
│   │   → Filters: role, branch, active status
│   │   → Form: name, email, phone, password, avatar upload,
│   │     branch select, role select (multi), locale, active toggle
│   │   → Actions: Reset Password, Disable, Force Logout
│   │   → Bulk: Activate, Deactivate
│   │
│   ├── RoleResource.php
│   │   → List: name, users count, permissions count
│   │   → Form: translatable name, permission checkboxes grouped by module
│   │   → System roles (owner, manager, practitioner, receptionist, accountant)
│   │     cannot be deleted — only permissions edited
│   │   → Uses filament-shield for permission UI
│   │
│   └── AccessPolicyResource.php
│       → List: name, model, role, permissions
│       → Form: name, model select (dropdown of all module models),
│         domain filter builder (jsonb), role select, permission toggles
│       → Advanced — only visible to owner role
│
├── Filament/Pages/
│   └── MyProfilePage.php
│       → Current user: edit name, email, phone, avatar, locale
│       → Change password
│       → 2FA setup (enable/disable)
│       → Active sessions list
│
├── Database/Migrations/
│   ├── create_users_table.php
│   ├── create_access_policies_table.php
│   └── (spatie permission tables auto-created)
│
├── Database/Seeders/
│   ├── DefaultRoleSeeder.php            (owner, manager, practitioner, receptionist, accountant)
│   ├── DefaultPermissionSeeder.php      (all permissions from all module manifests)
│   └── DefaultAccessPolicySeeder.php    (branch-scoped policies for non-owner roles)
│
└── Lang/
    ├── en/auth.php
    └── ar/auth.php
```

**CHECKPOINT BATCH 1:**
```
[ ] Tenant admin panel loads at {slug}.laserbase.com/admin
[ ] Login works with tenant user credentials
[ ] Can create/edit branches and rooms
[ ] Can create/edit users with role assignment
[ ] Roles have permission checkboxes grouped by module
[ ] Settings page shows all registered settings
[ ] Module management page shows available modules
[ ] Usage dashboard shows plan limits vs current usage
[ ] Access policies filter data by branch for scoped roles
[ ] Profile page allows password change and locale switch
```

---

# BATCH 2: PATIENTS + TREATMENTS
## What it does: Patient CRM and treatment catalog
## Dependencies: Core, Auth

### Module: Patients

**Models:**
```
modules/Patients/
├── PatientsManifest.php
│   → dependencies: ['core', 'auth']
│   → permissions: patients.view, patients.create, patients.edit, patients.delete,
│     patients.view_medical, patients.edit_medical, patients.view_photos, patients.manage_consents
│   → navigation: group 'CRM', items: [Patients]
│   → sequences: [{code: 'patient', prefix: 'PAT-', padding: 6}]
│   → settings: [require_national_id, default_referral_source, auto_merge_duplicates]
│
├── Models/
│   ├── Patient.php
│   │   → BaseModel, HasActivity, HasSequence, HasTags, HasAttachments, HasPortalAccess
│   │   → Fields: code (auto: PAT-2024-000001), first_name, last_name, full_name (generated),
│   │     phone (unique per tenant), email, date_of_birth, gender (enum: male/female),
│   │     national_id, address, city, referral_source (enum: walk_in/social/friend/doctor/ad/other),
│   │     referral_details, preferred_language (en/ar), preferred_branch_id, notes,
│   │     is_vip (bool), is_blacklisted (bool), tags (jsonb)
│   │   → Relationships: medicalHistory(), consentForms(), photos(), notes(),
│   │     appointments(), invoices(), giftCards(), packageSubscriptions(),
│   │     membershipSubscription(), loyaltyTransactions()
│   │   → Searchable: name, phone, email, national_id, code
│   │   → Computed: age, total_spent, last_visit_date, upcoming_appointment
│   │
│   ├── PatientMedicalHistory.php
│   │   → One-to-one with Patient
│   │   → Fields: fitzpatrick_type (enum: I-VI), skin_conditions (jsonb),
│   │     allergies (jsonb), medications (jsonb), chronic_conditions (jsonb),
│   │     previous_treatments (text), contraindications (jsonb),
│   │     is_pregnant (bool), is_breastfeeding (bool), blood_type,
│   │     emergency_contact_name, emergency_contact_phone, notes
│   │
│   ├── PatientConsentForm.php
│   │   → Fields: patient_id (FK), consent_template_id (FK), signed_at,
│   │     signature_data (text/base64), signature_type (enum: drawn/typed/uploaded),
│   │     pdf_path, witness_name, ip_address, version
│   │   → Relationships: patient(), template()
│   │
│   ├── PatientPhoto.php
│   │   → Uses spatie/media-library
│   │   → Fields: patient_id (FK), type (enum: before/after/during/consultation),
│   │     body_area (enum: face/neck/chest/back/arms/legs/full_body/other),
│   │     treatment_id (FK nullable), appointment_id (FK nullable),
│   │     notes, taken_by_user_id, taken_at
│   │   → Relationships: patient(), treatment(), appointment(), takenBy()
│   │
│   └── PatientNote.php
│       → Fields: patient_id (FK), type (enum: clinical/administrative/follow_up/complaint),
│         content (text), created_by_user_id, is_pinned (bool), is_private (bool)
│       → Relationships: patient(), createdBy()
│
├── Filament/Resources/
│   └── PatientResource.php
│       → List page:
│         - Columns: code, full_name, phone, gender, last visit, upcoming appt, VIP badge, tags
│         - Filters: gender, referral source, branch, tags, date range, has upcoming appt,
│           VIP only, blacklisted only, Fitzpatrick type
│         - Search: name, phone, email, national_id, code (via Meilisearch)
│         - Actions: Quick Book, Quick Invoice, Send WhatsApp, View
│         - Bulk: Tag, Export, Send Campaign
│         - Stats header: total patients, new this month, returning this month
│       → Create/Edit page (tabbed):
│         - Tab "Personal": first_name, last_name, phone, email, DOB, gender,
│           national_id, address, referral source, preferred branch, VIP toggle, tags
│         - Tab "Medical": Fitzpatrick type (VISUAL selector with skin tone images),
│           allergies (tag input), medications (tag input), conditions (checkboxes),
│           contraindications (checkboxes: pregnant, breastfeeding, pacemaker, etc.), notes
│         - Tab "Consent Forms": list of signed consents, [Sign New] button → modal with
│           template select + signature pad
│         - Tab "Photos": gallery grid filtered by body area + type, upload with metadata,
│           before/after comparison view
│         - Tab "Notes": timeline of notes, add note form, pin/unpin, private toggle
│         - MORE TABS ADDED BY OTHER MODULES VIA FORM EXTENSIONS:
│           (Gift Cards tab, Packages tab, Membership tab, Loyalty tab — added by those modules)
│         - Bottom: Activity log (chatter) showing all interactions across all modules
│
├── Database/Migrations/
│   ├── create_patients_table.php
│   ├── create_patient_medical_histories_table.php
│   ├── create_patient_consent_forms_table.php
│   ├── create_patient_photos_table.php
│   └── create_patient_notes_table.php
│
└── Database/Seeders/
    └── PatientSampleSeeder.php          (20 sample patients with Arabic names for demo)
```

### Module: Treatments

**Models:**
```
modules/Treatments/
├── TreatmentsManifest.php
│   → dependencies: ['core']
│   → permissions: treatments.view, treatments.create, treatments.edit, treatments.delete,
│     treatment_categories.manage, consent_templates.manage
│   → navigation: group 'Operations', items: [Treatments, Categories, Consent Templates]
│
├── Models/
│   ├── TreatmentCategory.php
│   │   → BaseModel, HasTranslation
│   │   → Self-referencing tree: parent_id (FK nullable)
│   │   → Fields: name (jsonb), slug, icon, sort_order, is_active
│   │   → Relationships: parent(), children(), treatments()
│   │
│   ├── Treatment.php
│   │   → BaseModel, HasTranslation, HasActivity
│   │   → Fields: name (jsonb), slug, description (jsonb), category_id (FK),
│   │     duration_minutes (int), buffer_minutes (int), recommended_sessions (int),
│   │     session_interval_days (int), base_price_minor (int), cost_price_minor (int),
│   │     contraindications (jsonb), fitzpatrick_min (int 1-6), fitzpatrick_max (int 1-6),
│   │     pre_care_instructions (jsonb translatable), post_care_instructions (jsonb translatable),
│   │     consent_template_id (FK nullable), requires_equipment (bool),
│   │     avg_consumable_cost_minor (int), is_active (bool), sort_order
│   │   → Relationships: category(), branchPricing(), consentTemplate(),
│   │     equipmentRequirements(), appointments()
│   │   → Computed: effective_price(branch_id) — checks branch override, falls back to base
│   │
│   ├── TreatmentBranchPricing.php
│   │   → Fields: treatment_id (FK), branch_id (FK), price_minor (int), is_active
│   │   → Composite unique: [treatment_id, branch_id]
│   │
│   └── ConsentTemplate.php
│       → BaseModel, HasTranslation
│       → Fields: name (jsonb), content (jsonb — rich HTML translatable),
│         version (int), is_active, requires_signature (bool)
│       → Relationships: treatments(), signedForms()
│
├── Filament/Resources/
│   ├── TreatmentCategoryResource.php
│   │   → Tree view with drag-and-drop reorder (nested set or simple sort)
│   │   → Form: translatable name, parent select, icon, active toggle
│   │
│   ├── TreatmentResource.php
│   │   → List: name, category badge, duration, base price, sessions, active toggle
│   │   → Filters: category, price range, active, has consent
│   │   → Form (tabbed):
│   │     - Tab "Details": translatable name/description, category, duration, buffer,
│   │       recommended sessions, interval days, base price, cost price, active toggle
│   │     - Tab "Medical": contraindications (tag input), Fitzpatrick range (slider),
│   │       pre-care instructions (rich editor translatable), post-care instructions,
│   │       consent template select
│   │     - Tab "Pricing": table repeater — branch | price | active
│   │       (TreatmentBranchPricing relation manager)
│   │     - Tab "Equipment": which equipment types are required (for availability check)
│   │
│   └── ConsentTemplateResource.php
│       → List: name, version, treatments count, signed count
│       → Form: translatable name, rich text editor (translatable content),
│         version auto-increment on edit, signature required toggle
│
├── Database/Migrations/
│   ├── create_treatment_categories_table.php
│   ├── create_treatments_table.php
│   ├── create_treatment_branch_pricing_table.php
│   └── create_consent_templates_table.php
│
└── Database/Seeders/
    ├── TreatmentCategorySeeder.php      (Laser Hair Removal, Skin Rejuvenation, Body Contouring, etc.)
    └── TreatmentSampleSeeder.php        (10 sample treatments with Arabic translations)
```

**CHECKPOINT BATCH 2:**
```
[ ] Can create patients with medical history
[ ] Patient search works (name, phone, code)
[ ] Fitzpatrick type selector is visual (skin tone images)
[ ] Consent forms can be signed with signature pad
[ ] Before/after photo upload works with body area tags
[ ] Patient activity log shows all actions
[ ] Treatment categories show as tree with drag reorder
[ ] Treatments have branch-specific pricing
[ ] Consent templates have rich text with translatable content
[ ] Patient sequence auto-generates PAT-2024-000001
```

---

# BATCH 3: BOOKING + EQUIPMENT
## Dependencies: Patients, Treatments, Core

### Module: Equipment

**Models:**
```
modules/Equipment/
├── EquipmentManifest.php
│   → dependencies: ['core', 'treatments']
│
├── Models/
│   ├── EquipmentType.php
│   │   → Fields: name (jsonb), manufacturer, model, category
│   │     (enum: laser/ipl/rf/hifu/cryolipolysis/microneedling/hydrafacial/led/other),
│   │     specifications (jsonb), max_shots (int nullable), image_url
│   │
│   ├── Equipment.php
│   │   → BaseModel, HasStateMachine, HasActivity, HasSequence
│   │   → Fields: code, name, equipment_type_id (FK), branch_id (FK), room_id (FK nullable),
│   │     serial_number, purchase_date, purchase_price_minor, warranty_expiry,
│   │     total_shots_fired (int), current_status (enum: active/maintenance/retired/out_of_service),
│   │     last_maintenance_at, next_maintenance_at, depreciation_years, notes
│   │   → State machine: active ↔ maintenance → retired, active → out_of_service
│   │   → Relationships: type(), branch(), room(), maintenanceLogs(), shotLogs()
│   │   → Computed: shots_remaining, depreciated_value, is_maintenance_due
│   │
│   ├── EquipmentMaintenanceLog.php
│   │   → Fields: equipment_id (FK), type (enum: preventive/corrective/calibration),
│   │     description, performed_by, cost_minor, parts_replaced (jsonb),
│   │     next_due_date, performed_at
│   │
│   └── EquipmentShotLog.php
│       → Fields: equipment_id (FK), appointment_id (FK), shots_count,
│         energy_setting, spot_size, pulse_duration, notes, logged_at
│
├── Filament/Resources/
│   ├── EquipmentTypeResource.php
│   │   → Catalog of machine types (admin reference)
│   │
│   └── EquipmentResource.php
│       → List: code, name, type, branch, room, shots fired, status badge, maintenance due
│       → Form (tabbed):
│         - Tab "Details": name, type, branch, room, serial number, purchase info
│         - Tab "Maintenance": timeline of maintenance logs, [Log Maintenance] action
│         - Tab "Shot Counter": progress bar (current/max), recent shot logs
│         - Tab "Depreciation": purchase price, years, current value calculation
│       → Status bar at top (Odoo-style)
│       → Actions: Log Maintenance, Record Shots, Move to Room, Retire
│
├── Database/Migrations/
│   ├── create_equipment_types_table.php
│   ├── create_equipment_table.php
│   ├── create_equipment_maintenance_logs_table.php
│   ├── create_equipment_shot_logs_table.php
│   └── create_treatment_equipment_requirements_table.php (pivot)
│
└── Database/Seeders/
    └── EquipmentTypeSeeder.php          (Candela GentleMax, Alma Soprano, Lumenis, etc.)
```

### Module: Booking

**Models:**
```
modules/Booking/
├── BookingManifest.php
│   → dependencies: ['core', 'auth', 'patients', 'treatments']
│   → optionalDependencies: ['equipment']
│   → permissions: appointments.view, appointments.create, appointments.edit,
│     appointments.cancel, appointments.check_in, appointments.complete,
│     schedule.manage, time_off.manage, waitlist.manage, calendar.view
│   → navigation: group 'Operations', items: [Calendar, Appointments, Schedules, Time Off, Waitlist]
│   → settings: [default_slot_duration, buffer_minutes, auto_confirm, reminder_hours_before,
│     allow_online_booking, max_advance_booking_days, cancellation_policy_hours]
│   → events: [AppointmentCreated, AppointmentConfirmed, AppointmentCheckedIn,
│     AppointmentStarted, AppointmentCompleted, AppointmentCancelled, AppointmentNoShow]
│   → sequences: [{code: 'appointment', prefix: 'APT-', padding: 6}]
│
├── Models/
│   ├── Appointment.php
│   │   → BaseModel, HasStateMachine, HasActivity, HasSequence
│   │   → Fields: code, patient_id (FK), treatment_id (FK), branch_id (FK),
│   │     practitioner_id (FK → User), room_id (FK nullable), equipment_id (FK nullable),
│   │     date (date), start_time (time), end_time (time), duration_minutes,
│   │     status (enum), price_minor (int — snapshot at booking time),
│   │     discount_minor (int), notes, cancellation_reason, rescheduled_from_id (FK nullable),
│   │     source (enum: admin/portal/whatsapp/phone/walk_in),
│   │     confirmed_at, checked_in_at, started_at, completed_at, cancelled_at
│   │   → State machine:
│   │     scheduled → confirmed → checked_in → in_progress → completed
│   │     scheduled → cancelled
│   │     scheduled → no_show
│   │     confirmed → cancelled
│   │     confirmed → rescheduled (creates new appointment)
│   │   → Relationships: patient(), treatment(), branch(), practitioner(),
│   │     room(), equipment(), treatmentNote(), shotLog(), invoice()
│   │   → Scopes: today(), upcoming(), forBranch(), forPractitioner(), forRoom()
│   │
│   ├── AppointmentTreatmentNote.php
│   │   → One-to-one with Appointment (filled after completion)
│   │   → Fields: appointment_id (FK), areas_treated (jsonb),
│   │     machine_settings (jsonb: energy, spot_size, pulse, frequency),
│   │     skin_reaction (enum: none/mild_redness/moderate/severe),
│   │     patient_comfort (enum: comfortable/mild_discomfort/moderate/painful),
│   │     shots_fired (int), notes, post_care_given (jsonb),
│   │     follow_up_recommended (bool), follow_up_days (int), created_by_user_id
│   │
│   ├── PractitionerSchedule.php
│   │   → Fields: user_id (FK), branch_id (FK), day_of_week (int 0-6),
│   │     start_time, end_time, is_available (bool)
│   │   → Composite unique: [user_id, branch_id, day_of_week]
│   │   → Represents weekly recurring schedule
│   │
│   ├── PractitionerTimeOff.php
│   │   → BaseModel, HasStateMachine
│   │   → Fields: user_id (FK), type (enum: vacation/sick/personal/training),
│   │     start_date, end_date, reason, status (enum: pending/approved/rejected),
│   │     approved_by_user_id, approved_at
│   │   → State machine: pending → approved/rejected
│   │
│   └── Waitlist.php
│       → Fields: patient_id (FK), treatment_id (FK), branch_id (FK nullable),
│         practitioner_id (FK nullable), preferred_days (jsonb), preferred_times (jsonb),
│         priority (int), notes, status (enum: waiting/notified/booked/expired),
│         notified_at, expires_at
│
├── Filament/Resources/
│   ├── AppointmentResource.php
│   │   → List: code, patient name, treatment, practitioner, date+time, status badge, branch
│   │   → Filters: status, branch, practitioner, treatment, date range, source
│   │   → Create (step wizard):
│   │     Step 1: Select patient (search or create inline)
│   │     Step 2: Select treatment
│   │     Step 3: Select branch → shows available practitioners
│   │     Step 4: Select practitioner → shows available rooms
│   │     Step 5: Pick date + time (visual slot picker showing free slots)
│   │     Step 6: Confirm → optional notes, source
│   │   → View: status bar at top, all details, treatment notes section,
│   │     shot log, actions (Check-in, Start, Complete, Cancel, Reschedule, No-Show)
│   │   → Complete action: opens modal for treatment notes (areas, settings, reaction)
│   │
│   ├── PractitionerScheduleResource.php
│   │   → Visual weekly grid editor (days × hours)
│   │   → Select practitioner → see/edit their weekly schedule per branch
│   │
│   ├── PractitionerTimeOffResource.php
│   │   → List: practitioner, type, dates, status badge
│   │   → Actions: Approve, Reject (for managers)
│   │
│   └── WaitlistResource.php
│       → List: patient, treatment, preferred days/times, priority, status
│       → Action: Book Now (creates appointment from waitlist entry)
│
├── Filament/Pages/
│   ├── CalendarPage.php
│   │   → Full calendar view (use a Livewire calendar package or custom)
│   │   → Day / Week / Month views
│   │   → Color-coded by status
│   │   → Click to view/edit appointment
│   │   → Filters: branch, practitioner, room
│   │   → Drag to reschedule (optional — complex)
│   │
│   └── DailyAgendaPage.php
│       → Today's appointments as a timeline
│       → One-click: Check-in, Start, Complete, No-Show
│       → Designed for reception desk (simple, fast)
│       → Shows: time, patient name, treatment, practitioner, room, status
│
├── Services/
│   └── AvailabilityService.php
│       → getAvailableSlots(branch, treatment, practitioner?, date): array of time slots
│       → Checks: practitioner schedule, existing appointments, time off,
│         room availability, equipment availability, buffer times
│       → Returns: [{start: "09:00", end: "09:30", room_id: X, equipment_id: Y}, ...]
│
├── Listeners/
│   └── (Marketing modules will listen to AppointmentCreated, AppointmentConfirmed, etc.
│         to send reminders — but those listeners live in the Marketing modules)
│
├── Database/Migrations/
│   ├── create_appointments_table.php
│   ├── create_appointment_treatment_notes_table.php
│   ├── create_practitioner_schedules_table.php
│   ├── create_practitioner_time_off_table.php
│   └── create_waitlist_table.php
│
└── Database/Seeders/
    ├── PractitionerScheduleSeeder.php   (sample weekly schedules)
    └── AppointmentSampleSeeder.php      (30 sample appointments across statuses)
```

**CHECKPOINT BATCH 3:**
```
[ ] Calendar page shows appointments in day/week/month view
[ ] Can create appointment via step wizard with availability checking
[ ] Double-booking prevented (practitioner, room, equipment)
[ ] Status transitions work: scheduled → confirmed → checked_in → in_progress → completed
[ ] Treatment notes captured on completion (areas, settings, reaction)
[ ] Equipment shot log recorded per appointment
[ ] Practitioner schedules editable as weekly grid
[ ] Time off requests with approval workflow
[ ] Waitlist with priority ordering
[ ] Daily agenda page works for reception
[ ] Activity log on appointment shows full timeline
```

---

# BATCH 4: BILLING + ACCOUNTING
## Dependencies: Patients, Treatments, Booking, Core

### Module: Billing

**Models:**
```
modules/Billing/
├── BillingManifest.php
│   → dependencies: ['core', 'auth', 'patients']
│   → optionalDependencies: ['booking', 'packages', 'gift_cards', 'memberships']
│   → events: [InvoiceCreated, InvoiceIssued, InvoicePaid, InvoiceOverdue,
│     InvoiceCancelled, PaymentReceived, RefundProcessed]
│   → sequences: [{code: 'invoice', prefix: 'INV-'}, {code: 'payment', prefix: 'PAY-'}]
│   → settings: [tax_rate, tax_inclusive, auto_invoice_on_complete, payment_methods,
│     default_payment_terms_days, enable_installments]
│
├── Models/
│   ├── Invoice.php
│   │   → BaseModel, HasStateMachine, HasSequence, HasActivity
│   │   → Fields: code, patient_id (FK), branch_id (FK), appointment_id (FK nullable),
│   │     type (enum: standard/credit_note/proforma), status, subtotal_minor,
│   │     discount_minor, tax_minor, total_minor, paid_minor, remaining_minor,
│   │     notes, due_date, issued_at, paid_at, cancelled_at, created_by_user_id
│   │   → State machine: draft → issued → partially_paid → paid (also: overdue, cancelled, refunded)
│   │   → Relationships: patient(), branch(), lines(), payments(), appointment()
│   │   → Computed: is_overdue, payment_progress_percentage
│   │
│   ├── InvoiceLine.php
│   │   → Fields: invoice_id (FK), treatment_id (FK nullable), description,
│   │     quantity (decimal), unit_price_minor, discount_minor, discount_type (enum: fixed/percent),
│   │     tax_rate (decimal), tax_minor, total_minor, package_subscription_id (FK nullable),
│   │     gift_card_id (FK nullable), sort_order
│   │
│   ├── Payment.php
│   │   → BaseModel, HasActivity
│   │   → Fields: code, invoice_id (FK), amount_minor, method
│   │     (enum: cash/card/bank_transfer/wallet/gift_card/insurance/installment/online),
│   │     reference_number, gateway_transaction_id, gift_card_id (FK nullable),
│   │     received_by_user_id, notes, paid_at
│   │   → Relationships: invoice(), receivedBy(), giftCard()
│   │
│   ├── TaxRate.php
│   │   → Fields: name (jsonb), rate (decimal 5,2), is_default, is_active
│   │
│   ├── InstallmentPlan.php
│   │   → Fields: invoice_id (FK), total_installments (int), installment_amount_minor,
│   │     frequency (enum: weekly/biweekly/monthly), start_date, status
│   │   → Relationships: invoice(), schedule()
│   │
│   └── InstallmentSchedule.php
│       → Fields: installment_plan_id (FK), installment_number (int),
│         amount_minor, due_date, paid_at, payment_id (FK nullable), status (enum: pending/paid/overdue)
│
├── Filament/Resources/
│   ├── InvoiceResource.php
│   │   → List: code, patient, total, paid, remaining, status badge, date
│   │   → Stats header: today's revenue, month revenue, outstanding total
│   │   → Filters: status, branch, date range, payment method, overdue only
│   │   → Create/Edit:
│   │     - Header: patient select (search), branch, type, date, due date
│   │     - Lines: table repeater (treatment select → auto-fill price, qty, discount, tax auto-calc)
│   │     - Totals: auto-calculated subtotal, discount, tax, total
│   │     - Notes field
│   │   → View: status bar, line items, payment history,
│   │     [Issue], [Record Payment], [Add Credit Note], [Cancel]
│   │   → Record Payment action: modal with amount, method, reference, gift card select
│   │     Supports partial payments
│   │
│   └── PaymentResource.php
│       → Read-only list of all payments across invoices
│       → Filters: method, branch, date range
│       → Used for reconciliation
│
├── Filament/Widgets/
│   ├── RevenueWidget.php              (today + this month)
│   ├── OutstandingWidget.php          (unpaid invoices total)
│   └── PaymentMethodBreakdownWidget.php (pie chart)
│
├── Services/
│   ├── InvoiceCalculationService.php
│   │   → calculateLine(treatment, qty, discount, tax_rate): line totals
│   │   → calculateInvoice(lines): subtotal, discount, tax, total
│   │   → All integer arithmetic — no floats ever
│   │
│   └── AutoInvoiceService.php
│       → Listens to AppointmentCompleted event
│       → Creates draft invoice with treatment as line item
│       → Applies member discount if membership active
│       → Auto-issues if setting enabled
│
├── Database/Migrations/
│   ├── create_invoices_table.php
│   ├── create_invoice_lines_table.php
│   ├── create_payments_table.php
│   ├── create_tax_rates_table.php
│   ├── create_installment_plans_table.php
│   └── create_installment_schedule_table.php
│
└── Database/Seeders/
    └── TaxRateSeeder.php               (VAT 14% for Egypt)
```

### Module: Accounting

**Models:**
```
modules/Accounting/
├── AccountingManifest.php
│   → dependencies: ['core', 'billing']
│
├── Models/
│   ├── ChartOfAccount.php
│   │   → Self-referencing tree: parent_id
│   │   → Fields: code (e.g. "1000"), name (jsonb), type (enum: asset/liability/equity/revenue/expense),
│   │     sub_type, is_system (bool — cannot delete), balance_minor (cached), is_active
│   │
│   ├── JournalEntry.php
│   │   → BaseModel, HasStateMachine, HasSequence, HasActivity
│   │   → Fields: code, date, reference, description, source_type (polymorphic),
│   │     source_id, status (enum: draft/posted/cancelled), total_debit_minor,
│   │     total_credit_minor, fiscal_period_id (FK), created_by_user_id, posted_at
│   │   → MUST balance: total_debit = total_credit (validated before posting)
│   │   → State machine: draft → posted (also: cancelled with reversal)
│   │
│   ├── JournalEntryLine.php
│   │   → Fields: journal_entry_id (FK), account_id (FK), debit_minor (int),
│   │     credit_minor (int), description, branch_id (FK nullable — cost center),
│   │     partner_type (polymorphic nullable), partner_id
│   │
│   └── FiscalPeriod.php
│       → Fields: name, start_date, end_date, status (enum: open/closed/locked), closed_by, closed_at
│
├── Filament/Resources/
│   ├── ChartOfAccountResource.php
│   │   → Tree view with account hierarchy
│   │   → Columns: code, name, type badge, balance, active
│   │
│   └── JournalEntryResource.php
│       → List: code, date, reference, total, status
│       → Create: date, reference, description, lines (table repeater: account, debit, credit, description)
│       → Balance check: warning banner if unbalanced
│       → View: status bar, lines table, source link
│       → Actions: Post, Cancel (with auto-reversal)
│
├── Filament/Pages/
│   ├── TrialBalancePage.php            (all accounts debit/credit for period)
│   ├── ProfitLossPage.php             (revenue - expenses for period/branch)
│   ├── BalanceSheetPage.php           (assets = liabilities + equity)
│   ├── GeneralLedgerPage.php          (transactions for specific account)
│   └── CashFlowPage.php              (inflows/outflows by category)
│
├── Listeners/
│   ├── CreateJournalOnInvoiceIssued.php
│   │   → DR Accounts Receivable, CR Revenue + CR VAT Payable
│   │
│   ├── CreateJournalOnPaymentReceived.php
│   │   → DR Cash/Bank, CR Accounts Receivable
│   │
│   └── CreateJournalOnRefund.php
│       → Reverse of original entry
│
├── Database/Migrations/
│   ├── create_chart_of_accounts_table.php
│   ├── create_journal_entries_table.php
│   ├── create_journal_entry_lines_table.php
│   └── create_fiscal_periods_table.php
│
└── Database/Seeders/
    ├── ChartOfAccountSeeder.php        (full COA: 1000-5990)
    └── FiscalPeriodSeeder.php          (12 monthly periods for current year)
```

**CHECKPOINT BATCH 4:**
```
[ ] Can create invoices with line items and auto tax calculation
[ ] All amounts are integers (minor units) — no float math anywhere
[ ] Record payments: cash, card, bank transfer — partial payments work
[ ] Installment plans create schedule with due dates
[ ] Auto-invoice fires when appointment completes (if setting on)
[ ] Journal entries auto-created: on invoice issue, on payment
[ ] Journal entries always balance (debit = credit validation)
[ ] Trial balance page shows correct numbers
[ ] P&L report filters by period and branch
[ ] Chart of accounts pre-seeded (1000-5990)
[ ] Fiscal periods: can close period to prevent back-dating
```

---

# BATCH 5: PACKAGES + GIFT CARDS + MEMBERSHIPS
## Dependencies: Patients, Billing

### Module: Packages

```
modules/Packages/
├── PackagesManifest.php
│   → dependencies: ['core', 'patients', 'billing']
│   → optionalDependencies: ['booking']
│   → extensions:
│       model: [PatientModelExtension → adds packageSubscriptions(), active_packages]
│       form:  [PatientFormExtension → adds "Packages" tab on patient view]
│       table: [PatientTableExtension → adds "Active Pkgs" column]
│
├── Models/
│   ├── Package.php
│   │   → Fields: name (jsonb), type (enum: session_bundle/value_bundle), base_price_minor,
│   │     validity_days, is_transferable, is_active, sort_order
│   │   → Relationships: items(), subscriptions()
│   │
│   ├── PackageItem.php
│   │   → Fields: package_id (FK), treatment_id (FK), quantity (int)
│   │
│   ├── PackageSubscription.php
│   │   → BaseModel, HasStateMachine, HasActivity
│   │   → Fields: patient_id (FK), package_id (FK), invoice_id (FK nullable),
│   │     status (enum: active/completed/expired/cancelled/frozen),
│   │     purchased_at, expires_at, frozen_at, frozen_until
│   │   → State machine: active → completed/expired/cancelled, active ↔ frozen
│   │   → Computed: sessions_used, sessions_remaining (per treatment)
│   │
│   └── PackageSessionUsage.php
│       → Fields: subscription_id (FK), treatment_id (FK), appointment_id (FK),
│         used_at, notes
│
├── Extensions/
│   ├── PatientModelExtension.php       → adds packageSubscriptions() relation
│   ├── PatientFormExtension.php        → adds "Packages" tab showing active packages, remaining sessions
│   ├── AppointmentFormExtension.php    → adds package session selector when booking
│   └── InvoiceFormExtension.php        → adds package purchase as line type
│
├── Filament/Resources/
│   └── PackageResource.php
│       → List: name, type, price, items count, active subscriptions count
│       → Form: name, type, price, validity, items (table repeater: treatment + quantity)
│
└── Database/Migrations/...
```

### Module: GiftCards

```
modules/GiftCards/
├── GiftCardsManifest.php
│   → dependencies: ['core', 'patients', 'billing']
│   → extensions:
│       model: [PatientModelExtension → adds giftCards(), total_gift_card_balance]
│       form:  [PatientFormExtension → adds "Gift Cards" tab]
│       form:  [InvoiceFormExtension → adds gift card as payment method]
│       dashboard: [GiftCardDashboardWidget → outstanding balance]
│   → events: [GiftCardIssued, GiftCardActivated, GiftCardRedeemed, GiftCardExpired]
│
├── Models/
│   ├── GiftCard.php
│   │   → BaseModel, HasStateMachine, HasSequence, HasActivity
│   │   → Fields: code (unique, auto: GC-2024-000001), purchaser_patient_id (FK nullable),
│   │     recipient_patient_id (FK nullable), initial_value_minor, remaining_value_minor,
│   │     status (enum: draft/active/partially_used/fully_used/expired/cancelled),
│   │     purchased_via_invoice_id (FK nullable), expires_at, activated_at, notes
│   │   → State machine: draft → active → partially_used → fully_used, active → expired
│   │
│   └── GiftCardTransaction.php
│       → Fields: gift_card_id (FK), type (enum: activate/redeem/refund/adjust/expire),
│         amount_minor, running_balance_minor, invoice_id (FK nullable),
│         payment_id (FK nullable), notes, created_by_user_id
│
├── Extensions/
│   ├── PatientModelExtension.php
│   ├── PatientFormExtension.php
│   ├── InvoiceFormExtension.php        → gift card as payment option
│   └── GiftCardDashboardWidget.php
│
├── Filament/Resources/
│   └── GiftCardResource.php
│       → List: code, purchaser, recipient, initial value, remaining, status, expires
│       → View: status bar, transaction history timeline, actions (Activate, Redeem, Adjust)
│       → Create: value, purchaser (optional), recipient (optional), expiry
│
├── Listeners/
│   └── ActivateGiftCardOnInvoicePaid.php (when purchase invoice is paid → activate card)
│
└── Database/Migrations/...
```

### Module: Memberships

```
modules/Memberships/
├── MembershipsManifest.php
│   → dependencies: ['core', 'patients', 'billing']
│   → extensions:
│       model: [PatientModelExtension → adds membershipSubscription(), is_member, member_discount]
│       form:  [PatientFormExtension → adds "Membership" tab]
│       model: [InvoiceModelExtension → auto-apply member discount]
│
├── Models/
│   ├── Membership.php
│   │   → Fields: name (jsonb), tier (enum: silver/gold/platinum/diamond),
│   │     price_monthly_minor, price_yearly_minor, discount_percentage (decimal),
│   │     included_sessions_monthly (jsonb: {treatment_id: count}),
│   │     loyalty_multiplier (decimal, e.g. 1.5), priority_booking (bool),
│   │     is_active, sort_order
│   │
│   └── MembershipSubscription.php
│       → BaseModel, HasStateMachine, HasActivity
│       → Fields: patient_id (FK), membership_id (FK), status
│         (enum: active/expired/cancelled/frozen),
│         started_at, expires_at, auto_renew (bool), renewal_invoice_id (FK nullable)
│       → State machine: active → expired/cancelled, active ↔ frozen
│
├── Extensions/
│   ├── PatientModelExtension.php
│   ├── PatientFormExtension.php
│   └── InvoiceModelExtension.php       → auto-applies discount to line items
│
└── Database/Migrations/...
```

**CHECKPOINT BATCH 5:**
```
[ ] Can create packages with multiple treatments × quantities
[ ] Patient can purchase package → sessions tracked
[ ] When booking, can select "use package session" to consume from package
[ ] Package status: active → completed when all sessions used
[ ] Gift cards: create, activate, redeem (partial), track balance
[ ] Gift card as payment method on invoice works
[ ] Gift card auto-activates when purchase invoice paid
[ ] Memberships: create tiers with discount percentage
[ ] Member discount auto-applied on new invoices
[ ] All three modules add tabs to Patient detail via FormExtension
[ ] Patient model has new relationships from ModelExtensions
[ ] Accounting: deferred revenue journals for packages/gift cards
```

---

# BATCH 6: INVENTORY + STAFF + PAYROLL
## Dependencies: Core, Billing, Booking

### Module: Inventory

```
modules/Inventory/
├── Models/
│   ├── ProductCategory.php
│   ├── Product.php                     (consumables: creams, needles, gels, etc.)
│   ├── StockLevel.php                  (per product per branch)
│   ├── StockMovement.php               (in/out/transfer/adjustment)
│   ├── Supplier.php
│   ├── PurchaseOrder.php               (HasStateMachine: draft → sent → received)
│   └── PurchaseOrderLine.php
│
├── Key features:
│   → Auto-deduct consumables when appointment completes
│     (based on treatment's avg_consumable_cost or explicit product mapping)
│   → Reorder alerts when stock < reorder_point
│   → Inter-branch stock transfers
│   → Purchase order workflow with receiving
│
├── Listeners/
│   └── DeductStockOnAppointmentCompleted.php
│
└── Extensions/
    └── TreatmentFormExtension.php      → adds "Consumables" tab listing required products
```

### Module: Staff

```
modules/Staff/
├── Models/
│   ├── StaffProfile.php                (extends User with: specializations, bio, commission_type)
│   ├── StaffCommission.php             (rules: flat per treatment, % of revenue, tiered)
│   └── StaffCommissionRecord.php       (actual earnings per appointment)
│
├── Key features:
│   → Commission auto-calculated when appointment completes
│   → Commission approval workflow (pending → approved → paid)
│   → Per-treatment or per-category commission rules
│
├── Listeners/
│   └── CalculateCommissionOnAppointmentCompleted.php
│
└── Extensions/
    └── UserFormExtension.php           → adds "Commission" tab on user profile
```

### Module: Payroll

```
modules/Payroll/
├── Models/
│   ├── PayrollRun.php                  (monthly run: HasStateMachine draft → approved → paid)
│   └── PayrollLine.php                 (per user: base + commissions + bonuses - deductions)
│
├── Key features:
│   → Monthly payroll generation pulling approved commissions
│   → PDF salary slips
│   → Journal entries for salary expenses (if Accounting active)
│
└── Listeners/
    └── CreateSalaryJournalOnPayrollPaid.php
```

**CHECKPOINT BATCH 6:** ✅ COMPLETED
```
[x] Products with stock levels per branch
[~] Stock auto-deducts on appointment completion (listener ready, needs event wiring)
[ ] Low stock alerts on dashboard (widget needed)
[x] Purchase orders with receive workflow
[~] Staff commissions calculated per appointment (listener ready, needs event wiring)
[x] Commission approval workflow
[~] Payroll run generates salary slips (PDF generation pending)
[x] All financial transactions create journal entries
```

---

# BATCH 7: MARKETING
## Dependencies: Patients, Booking

### Module: MarketingWhatsApp

```
modules/MarketingWhatsApp/
├── Models/
│   ├── WhatsAppTemplate.php            (pre-approved Meta templates)
│   └── (shared) NotificationLog.php, Campaign.php
│
├── Key features:
│   → Automated appointment reminders (24h + 2h before — configurable)
│   → Follow-up messages (X days after appointment)
│   → Campaign builder: audience filters (last visit, tags, treatment, branch)
│   → Delivery tracking: sent, delivered, read, failed
│   → Uses WhatsApp Business Cloud API
│
├── Listeners/
│   ├── SendAppointmentReminder.php     (listens to AppointmentConfirmed)
│   ├── SendFollowUpMessage.php         (scheduled after AppointmentCompleted)
│   └── SendInvoiceReceipt.php          (listens to InvoicePaid)
│
└── Filament/Resources/
    ├── CampaignResource.php            (audience + template + schedule + analytics)
    ├── WhatsAppTemplateResource.php    (manage approved templates)
    └── NotificationLogResource.php     (delivery tracking table)
```

### Module: MarketingSms — Same pattern as WhatsApp but with SMS provider
### Module: MarketingEmail — Same pattern with Mailgun/Resend + HTML builder

**CHECKPOINT BATCH 7:** ✅ COMPLETED (Unified Marketing Module)
```
[~] WhatsApp appointment reminders sent automatically (service ready, needs event wiring)
[x] Campaign builder: filter audience → select template → schedule → send
[x] Delivery tracking: sent/delivered/read/failed status
[x] SMS and Email channels working (services implemented)
[x] Notification log shows all messages across channels
[ ] Quota enforcement: messages count against plan limits (needs integration)
```

---

# BATCH 8: LOYALTY + REPORTING
## Dependencies: Patients, Billing, Booking

### Module: Loyalty

```
modules/Loyalty/
├── Models/
│   ├── LoyaltyRule.php                 (earn: per_spend/per_visit/referral/birthday)
│   ├── LoyaltyTransaction.php          (earn/redeem/expire/adjust with running balance)
│   └── ReferralProgram.php             (referrer + referred rewards)
│
├── Extensions/
│   ├── PatientModelExtension.php       → adds loyalty_points, loyaltyTransactions()
│   ├── PatientFormExtension.php        → adds "Loyalty" tab
│   └── InvoiceModelExtension.php       → points redemption as payment
│
└── Listeners/
    ├── AwardPointsOnPayment.php
    ├── AwardPointsOnVisit.php
    └── AwardReferralBonus.php
```

### Module: Reporting

```
modules/Reporting/
├── Filament/Pages/
│   ├── RevenueReportPage.php           (by treatment, branch, practitioner, period)
│   ├── PatientReportPage.php           (new vs returning, demographics, retention)
│   ├── AppointmentReportPage.php       (utilization, no-show rate, peak hours)
│   ├── EquipmentReportPage.php         (shots, maintenance cost, utilization)
│   ├── StaffPerformanceReportPage.php  (revenue per practitioner, appointment count)
│   ├── InventoryReportPage.php         (stock valuation, consumption rate)
│   ├── GiftCardReportPage.php          (outstanding balance, redemption rate)
│   ├── CampaignReportPage.php          (ROI, conversion, cost per acquisition)
│   └── FinancialSummaryPage.php        (executive: P&L, cash flow, receivables aging)
│
│   Each report page has:
│   → Date range filter, branch filter
│   → Summary cards at top
│   → Chart (bar/line/pie using Chart.js)
│   → Data table below
│   → Export buttons: PDF (dompdf), Excel (maatwebsite)
```

**CHECKPOINT BATCH 8:**
```
[ ] Loyalty points earned on payment + visit
[ ] Points redeemable as payment on invoice
[ ] Referral tracking works
[ ] All 9 report pages load with correct data
[ ] Reports filter by date range and branch
[ ] PDF and Excel export functional
[ ] Dashboard shows key metrics from all modules
```

---

# BATCH 9: PATIENT PORTAL + API
## Dependencies: All modules

### Module: PatientPortal

```
modules/PatientPortal/
├── Separate Filament panel: PortalPanel at /portal
│   → Auth: phone + OTP or email + password
│   → Pages:
│     - Dashboard: upcoming appointments, recent invoices, points balance
│     - Book Appointment: treatment → branch → date → time → confirm
│     - My Appointments: list with cancel/reschedule
│     - My Invoices: list with pay online button (Paymob)
│     - My Profile: edit personal info
│     - Gift Cards: check balance
│     - Loyalty: view points and history
│     - Consent Forms: view and download signed PDFs
│     - Photos: view before/after (if enabled by clinic)
```

### Module: Api

```
modules/Api/
├── REST API using Laravel Sanctum
│   → Patient auth: phone + OTP → token
│   → Staff auth: email + password → token
│   → Endpoints mirror portal functionality
│   → Rate limiting via QuotaService
│   → Module-aware: endpoints only available if module active
│   → Auto-docs via Scribe or manual OpenAPI spec
```

**CHECKPOINT BATCH 9:**
```
[ ] Patient can register and login to portal via OTP
[ ] Patient can book appointments through portal
[ ] Patient can view and pay invoices online
[ ] API returns JSON with proper pagination
[ ] API respects module activation (404 if module off)
[ ] Rate limiting enforced per plan
```

---

# TENANT ADMIN PANEL — NAVIGATION STRUCTURE

When all batches are done, the tenant sidebar looks like this:

```
┌──────────────────────────┐
│  ◆ CLINIC NAME           │
│                          │
│  🏠 Dashboard            │
│                          │
│  ── CRM ──               │
│  👤 Patients             │
│                          │
│  ── OPERATIONS ──        │
│  📅 Calendar             │
│  📋 Appointments         │
│  💆 Treatments           │
│  📂 Categories           │
│  🔧 Equipment            │
│  📝 Consent Templates    │
│  🕐 Schedules            │
│  ⏸️ Time Off              │
│  📋 Waitlist             │
│                          │
│  ── SALES ──             │
│  📦 Packages             │
│  🎁 Gift Cards           │
│  ⭐ Memberships          │
│                          │
│  ── FINANCIAL ──         │
│  💰 Invoices             │
│  💳 Payments             │
│  📊 Chart of Accounts    │
│  📒 Journal Entries      │
│  📈 Trial Balance        │
│  💵 P&L Report           │
│  🏦 Balance Sheet        │
│                          │
│  ── INVENTORY ──         │
│  📦 Products             │
│  📊 Stock Levels         │
│  📑 Purchase Orders      │
│  🏢 Suppliers            │
│                          │
│  ── MARKETING ──         │
│  💬 WhatsApp             │
│  📱 SMS                  │
│  📧 Email                │
│  📣 Campaigns            │
│                          │
│  ── HR ──                │
│  👥 Staff                │
│  💵 Commissions          │
│  💰 Payroll              │
│                          │
│  ── REPORTS ──           │
│  📊 Revenue              │
│  👤 Patients             │
│  📅 Appointments         │
│  🔧 Equipment            │
│  👥 Staff Performance    │
│  📦 Inventory            │
│  📈 Financial Summary    │
│                          │
│  ── SETTINGS ──          │
│  🧩 Modules              │
│  ⚙️ General Settings     │
│  🏥 Branches & Rooms     │
│  👥 Users & Roles        │
│  🔒 Access Policies      │
│  📊 Usage & Limits       │
│  🎯 Loyalty Rules        │
│                          │
└──────────────────────────┘

Note: Items only visible if:
1. Module is active for this tenant
2. User has permission for that resource
3. User's branch has access (if branch-scoped)
```

---

# CLAUDE CODE PROMPT FOR EACH BATCH

### Batch 1:
```
Read docs/1-execution-plan.md for conventions and docs/4-framework-architecture.md
for module patterns. Build Batch 1: Core module + Auth module.
Create all models, migrations, seeders, Filament resources, and pages as specified.
Every model extends BaseModel. Every resource extends BaseResource.
Register both modules via their Manifest files. Verify the tenant admin panel loads.
```

### Batch 2-9: Same pattern — reference the docs, name the batch, list what to build.

---

# KEY REMINDER: CROSS-MODULE EXTENSIONS

The magic of this architecture is that modules extend each other WITHOUT touching
each other's code. When building Batch 5+ modules, always create:

1. **ModelExtension** — adds relationships to existing models (Patient, Invoice, etc.)
2. **FormExtension** — adds tabs/sections to existing Filament forms
3. **TableExtension** — adds columns/filters to existing Filament tables
4. **Listeners** — reacts to events from other modules

Example: GiftCards module NEVER touches Patient code. Instead:
- GiftCardsManifest declares extensions
- Framework kernel applies them at boot time
- Patient form magically has a "Gift Cards" tab
- Patient model magically has giftCards() relationship
- Invoice payment form magically has "Gift Card" payment option

This is what makes the system truly modular.

---

# IMPLEMENTATION CHECKLIST

## Legend
- [x] = Done / Implemented
- [ ] = Not Started
- [~] = Partial Implementation

---

## BATCH 1: CORE + AUTH MODULE

### Core Module

#### Models
- [x] Branch.php - BaseModel, HasTenancy, working_hours (jsonb), is_active, timezone
  - [x] Added rooms() and users() relationships
  - [x] Added working hours helper methods
- [x] Room.php - BaseModel, branch_id FK, capacity, is_active, sort_order
- [x] Setting.php - key-value store for tenant settings
- [x] Sequence.php - Fields: code, prefix, next_value, padding, reset_on_year, current_year
  - [x] Method: next() with SELECT FOR UPDATE locking (DB transaction)

#### Filament Resources (Tenant Panel)
- [x] BranchResource.php
  - [x] List: name, address, rooms count, staff count, is_active toggle
  - [x] Form: name, address, phone, email, working hours (Repeater), Google Maps link, timezone
  - [x] Relation managers: RoomsRelationManager
- [x] RoomResource.php
  - [x] Table: name, capacity, active toggle
  - [x] Form: name, branch select, capacity, sort order

#### Filament Pages (Tenant Panel)
- [x] GeneralSettingsPage.php
  - [x] Unified settings built from SettingsRegistry
  - [x] Sections: General, Booking, Billing, Marketing (grouped tabs)
  - [x] Each setting: label, input type, default, validation
- [~] ModuleManagementPage.php (exists as SuperAdmin resource, needs tenant-level)
  - [ ] Grid of available modules
  - [ ] Toggle on/off with dependency check
  - [ ] Shows: module name, icon, description, status, dependencies
  - [ ] Disabled modules show "Upgrade Plan" badge
- [x] UsageDashboardPage.php
  - [x] Progress bars: users, branches, patients, storage vs plan limits
  - [x] Monthly counters: appointments, WhatsApp, SMS, emails
  - [x] Stats cards: appointments, revenue, new patients this month

#### Database Migrations
- [x] create_branches_table.php
- [x] create_rooms_table.php
- [x] create_settings_table.php
- [x] create_sequences_table.php
- [ ] create_activities_table.php (for HasActivity across all modules)
- [ ] create_audit_logs_table.php (for HasAudit across all modules)

#### Database Seeders
- [x] DefaultBranchSeeder.php (create "Main Branch" on provision)
- [x] DefaultSequenceSeeder.php (PAT-, INV-, APT-, GC-, JE-, PO-)
- [x] DefaultSettingsSeeder.php (all default setting values)

#### Lang Files
- [x] en/core.php
- [x] ar/core.php

---

### Auth Module

#### Models
- [x] User.php - Authenticatable, HasActivity, HasSequence, branch_id FK, locale, two_factor
- [x] Role.php - spatie/laravel-permission roles
- [x] Permission.php - spatie/laravel-permission permissions
- [x] AccessPolicy.php - Odoo ir.rule equivalent, domain_filter (jsonb), role_id FK

#### Filament Resources (Tenant Panel)
- [x] UserResource.php
  - [x] List: name, email, role badges, branch, last login, active toggle
  - [x] Filters: role, branch, active status
  - [x] Form: name, email, phone, password, avatar, branch select, role select, locale, active
  - [ ] Actions: Reset Password, Disable, Force Logout
  - [ ] Bulk: Activate, Deactivate
- [x] RoleResource.php
  - [x] List: name, users count, permissions count
  - [x] Form: name, permission checkboxes grouped by module
  - [ ] System roles (owner, manager, practitioner, receptionist, accountant) cannot be deleted
- [x] AccessPolicyResource.php
  - [x] List: name, model, role, permissions
  - [x] Form: name, model select, domain filter builder (jsonb), role select, permission toggles
  - [ ] Only visible to owner role

#### Filament Pages (Tenant Panel)
- [x] MyProfilePage.php (ProfileResource)
  - [x] Edit: name, email, phone, avatar, locale
  - [x] Change password
- [x] TwoFactorSetupResource.php
  - [x] 2FA enable/disable
  - [ ] Active sessions list

#### Database Migrations
- [x] create_users_table.php
- [x] create_permission_tables.php (spatie)
- [x] create_access_policies_table.php
- [x] create_user_sessions_table.php
- [x] create_login_history_table.php
- [x] create_password_history_table.php
- [x] create_user_profiles_table.php

#### Database Seeders
- [ ] DefaultRoleSeeder.php (owner, manager, practitioner, receptionist, accountant)
- [ ] DefaultPermissionSeeder.php (all permissions from all module manifests)
- [ ] DefaultAccessPolicySeeder.php (branch-scoped policies for non-owner roles)

#### Lang Files
- [x] en/auth.php
- [x] ar/auth.php

---

### BATCH 1 CHECKPOINT
- [x] Tenant admin panel loads at {slug}.laserbase.com/admin
- [x] Login works with tenant user credentials
- [ ] Can create/edit branches and rooms (RoomResource exists, BranchResource missing)
- [x] Can create/edit users with role assignment
- [x] Roles have permission checkboxes grouped by module
- [ ] Settings page shows all registered settings
- [ ] Module management page shows available modules (tenant-level)
- [ ] Usage dashboard shows plan limits vs current usage
- [ ] Access policies filter data by branch for scoped roles
- [x] Profile page allows password change and locale switch

---

## BATCH 2: PATIENTS + TREATMENTS

### Patients Module

#### Models
- [x] Patient.php - BaseModel, HasActivity, HasSequence, HasTags
  - [x] Fields: code, first_name, last_name, phone, email, date_of_birth, gender, national_id
  - [x] Fields: address, referral_source, language, is_vip, is_blacklisted, tags
  - [x] Relationships: medicalHistory(), consentForms(), photos(), notes()
  - [x] Computed: age, total_spent, last_visit_date
  - [x] Searchable: name, phone, email, national_id, code
- [x] PatientMedicalHistory.php - One-to-one with Patient
  - [x] Fields: fitzpatrick_type, skin_conditions, allergies, medications, chronic_conditions
  - [x] Fields: contraindications, is_pregnant, is_breastfeeding, blood_type
  - [x] Fields: emergency_contact_name, emergency_contact_phone
- [x] PatientConsentForm.php
  - [x] Fields: patient_id, consent_template_id, signed_at, signature_data, signature_type
  - [x] Fields: pdf_path, witness_name, ip_address, version
- [x] PatientPhoto.php
  - [x] Fields: patient_id, type (before/after/during/consultation), body_area
  - [x] Fields: treatment_id, appointment_id, notes, taken_by_user_id, taken_at
- [x] PatientNote.php
  - [x] Fields: patient_id, type (clinical/administrative/follow_up/complaint)
  - [x] Fields: content, created_by_user_id, is_pinned, is_private

#### Filament Resources
- [x] PatientResource.php
  - [x] List: code, full_name, phone, gender, last visit, VIP badge, tags
  - [x] Filters: gender, referral source, branch, tags, date range
  - [x] Search: name, phone, email, national_id, code
  - [ ] Actions: Quick Book, Quick Invoice, Send WhatsApp, View
  - [ ] Bulk: Tag, Export, Send Campaign
  - [x] Stats header: total patients, new this month, returning this month
  - [x] Create/Edit page (tabbed):
    - [x] Tab "Personal": first_name, last_name, phone, email, DOB, gender, national_id
    - [x] Tab "Medical": Fitzpatrick type, allergies, medications, conditions, contraindications
    - [x] Tab "Consent Forms": list of signed consents
    - [x] Tab "Photos": gallery grid filtered by body area + type
    - [x] Tab "Notes": timeline of notes, add note form
    - [ ] Fitzpatrick type VISUAL selector with skin tone images
    - [ ] Signature pad for consent forms
    - [ ] Before/after comparison view

#### Database Migrations
- [x] create_patients_table.php
- [x] create_patient_medical_histories_table.php
- [x] create_patient_consent_forms_table.php
- [x] create_patient_photos_table.php
- [x] create_patient_notes_table.php

#### Database Seeders
- [ ] PatientSampleSeeder.php (20 sample patients with Arabic names for demo)

#### Lang Files
- [x] en/patients.php
- [x] ar/patients.php

---

### Treatments Module

#### Models
- [x] TreatmentCategory.php - BaseModel, HasTranslation
  - [x] Self-referencing tree: parent_id FK
  - [x] Fields: name (jsonb), slug, icon, sort_order, is_active
  - [x] Relationships: parent(), children(), treatments()
- [x] Treatment.php - BaseModel, HasTranslation, HasActivity
  - [x] Fields: name (jsonb), description (jsonb), category_id, duration_minutes, buffer_minutes
  - [x] Fields: base_price_minor, recommended_sessions, session_interval_days
  - [x] Fields: contraindications (jsonb), fitzpatrick_min/max, pre_care/post_care instructions
  - [x] Fields: consent_template_id, requires_equipment, is_active, sort_order
  - [x] Relationships: category(), branchPricing(), consentTemplate()
  - [x] Computed: effective_price(branch_id) via getPriceForBranch()
- [x] TreatmentBranchPricing.php
  - [x] Fields: treatment_id, branch_id, price_minor, is_active
  - [x] Composite unique: [treatment_id, branch_id]
- [x] ConsentTemplate.php - BaseModel, HasTranslation
  - [x] Fields: name (jsonb), content (jsonb — rich HTML translatable)
  - [x] Fields: version, is_active, requires_signature

#### Filament Resources
- [x] TreatmentCategoryResource.php
  - [x] List view with categories
  - [ ] Tree view with drag-and-drop reorder
  - [x] Form: translatable name, parent select, icon, active toggle
- [x] TreatmentResource.php
  - [x] List: name, category badge, duration, base price, sessions, active toggle
  - [x] Filters: category, active
  - [x] Form (tabbed):
    - [x] Tab "Details": translatable name/description, category, duration, buffer, sessions, price
    - [x] Tab "Medical": contraindications, Fitzpatrick range, pre-care/post-care instructions
    - [x] Tab "Pricing": BranchPricingRelationManager
    - [ ] Tab "Equipment": equipment types required for availability check
- [x] ConsentTemplateResource.php
  - [x] List: name, version, treatments count
  - [x] Form: translatable name, rich text editor, version, signature required toggle

#### Database Migrations
- [x] create_treatment_categories_table.php
- [x] create_treatments_table.php
- [x] create_treatment_branch_pricing_table.php
- [x] create_consent_templates_table.php

#### Database Seeders
- [ ] TreatmentCategorySeeder.php (Laser Hair Removal, Skin Rejuvenation, Body Contouring, etc.)
- [ ] TreatmentSampleSeeder.php (10 sample treatments with Arabic translations)

#### Lang Files
- [x] en/treatments.php
- [x] ar/treatments.php

---

### BATCH 2 CHECKPOINT
- [x] Can create patients with medical history
- [x] Patient search works (name, phone, code)
- [ ] Fitzpatrick type selector is visual (skin tone images)
- [ ] Consent forms can be signed with signature pad
- [x] Before/after photo upload works with body area tags
- [ ] Patient activity log shows all actions
- [ ] Treatment categories show as tree with drag reorder
- [x] Treatments have branch-specific pricing
- [x] Consent templates have rich text with translatable content
- [x] Patient sequence auto-generates PAT-2024-000001

---

## BATCH 3: BOOKING + EQUIPMENT ✅ COMPLETED

### Equipment Module ✅
- [x] EquipmentManifest.php (using module.json instead)

#### Models ✅
- [x] EquipmentType.php
  - [x] Fields: name (jsonb), manufacturer, model, category (laser/ipl/rf/hifu/etc)
  - [x] Fields: specifications (jsonb), max_shots, image_url
- [x] Equipment.php - BaseModel, HasStateMachine, HasActivity, HasSequence
  - [x] Fields: code, name, equipment_type_id, branch_id, room_id
  - [x] Fields: serial_number, purchase_date, purchase_price_minor, warranty_expiry
  - [x] Fields: total_shots_fired, current_status (active/maintenance/retired/out_of_service)
  - [x] Fields: last_maintenance_at, next_maintenance_at, depreciation_years, notes
  - [x] State machine: active ↔ maintenance → retired, active → out_of_service
  - [x] Computed: shots_remaining, depreciated_value, is_maintenance_due
- [x] EquipmentMaintenanceLog.php
  - [x] Fields: equipment_id, type (preventive/corrective/calibration), description
  - [x] Fields: performed_by, cost_minor, parts_replaced (jsonb), next_due_date, performed_at
- [x] EquipmentShotLog.php
  - [x] Fields: equipment_id, appointment_id, shots_count, energy_setting, spot_size, pulse_duration

#### Filament Resources ✅
- [x] EquipmentTypeResource.php - Catalog of machine types
- [x] EquipmentResource.php
  - [x] List: code, name, type, branch, room, shots fired, status badge, maintenance due
  - [x] Form tabs: Details, Maintenance, Shot Counter, Depreciation
  - [x] Status bar at top (Odoo-style)
  - [x] Actions: Log Maintenance, Record Shots, Move to Room, Retire

#### Database Migrations ✅
- [x] create_equipment_types_table.php
- [x] create_equipment_table.php
- [x] create_equipment_maintenance_logs_table.php
- [x] create_equipment_shot_logs_table.php
- [x] create_treatment_equipment_requirements_table.php (pivot)

#### Database Seeders
- [ ] EquipmentTypeSeeder.php (Candela GentleMax, Alma Soprano, Lumenis, etc.)

---

### Booking Module ✅
- [x] BookingManifest.php (using module.json instead)

#### Models ✅
- [x] Appointment.php - BaseModel, HasStateMachine, HasActivity, HasSequence
  - [x] Fields: code, patient_id, treatment_id, branch_id, practitioner_id, room_id, equipment_id
  - [x] Fields: date, start_time, end_time, duration_minutes, status, price_minor, discount_minor
  - [x] Fields: notes, cancellation_reason, rescheduled_from_id, source
  - [x] Fields: confirmed_at, checked_in_at, started_at, completed_at, cancelled_at
  - [x] State machine: scheduled → confirmed → checked_in → in_progress → completed
  - [x] Also: cancelled, no_show, rescheduled states
- [x] AppointmentTreatmentNote.php - One-to-one (filled after completion)
  - [x] Fields: appointment_id, areas_treated (jsonb), machine_settings (jsonb)
  - [x] Fields: skin_reaction, patient_comfort, shots_fired, notes, post_care_given
  - [x] Fields: follow_up_recommended, follow_up_days, created_by_user_id
- [x] PractitionerSchedule.php
  - [x] Fields: user_id, branch_id, day_of_week, start_time, end_time, is_available
  - [x] Composite unique: [user_id, branch_id, day_of_week]
- [x] PractitionerTimeOff.php - BaseModel, HasStateMachine
  - [x] Fields: user_id, type (vacation/sick/personal/training), start_date, end_date
  - [x] Fields: reason, status (pending/approved/rejected), approved_by_user_id, approved_at
- [x] Waitlist.php
  - [x] Fields: patient_id, treatment_id, branch_id, practitioner_id
  - [x] Fields: preferred_days (jsonb), preferred_times (jsonb), priority, notes
  - [x] Fields: status (waiting/notified/booked/expired), notified_at, expires_at

#### Filament Resources ✅
- [x] AppointmentResource.php
  - [x] List: code, patient, treatment, practitioner, date+time, status badge, branch
  - [x] Filters: status, branch, practitioner, treatment, date range, source
  - [x] Create wizard: Patient → Treatment → Branch → Practitioner → Date/Time → Confirm
  - [x] View: status bar, details, treatment notes, shot log
  - [x] Actions: Check-in, Start, Complete, Cancel, Reschedule, No-Show
  - [x] Complete action: modal for treatment notes
- [x] PractitionerScheduleResource.php - Visual weekly grid editor
- [x] PractitionerTimeOffResource.php - Approval workflow
- [x] WaitlistResource.php - Priority ordering, Book Now action

#### Filament Pages
- [ ] CalendarPage.php
  - [ ] Full calendar view (Day/Week/Month)
  - [ ] Color-coded by status
  - [ ] Click to view/edit
  - [ ] Filters: branch, practitioner, room
  - [ ] Drag to reschedule (optional)
- [ ] DailyAgendaPage.php
  - [ ] Today's appointments as timeline
  - [ ] One-click: Check-in, Start, Complete, No-Show
  - [ ] Designed for reception desk

#### Services
- [ ] AvailabilityService.php
  - [ ] getAvailableSlots(branch, treatment, practitioner?, date)
  - [ ] Checks: schedule, existing appointments, time off, room, equipment, buffers

#### Database Migrations ✅
- [x] create_appointments_table.php
- [x] create_appointment_treatment_notes_table.php
- [x] create_practitioner_schedules_table.php
- [x] create_practitioner_time_off_table.php
- [x] create_waitlist_table.php

#### Database Seeders
- [ ] PractitionerScheduleSeeder.php
- [ ] AppointmentSampleSeeder.php (30 sample appointments)

---

### BATCH 3 CHECKPOINT
- [ ] Calendar page shows appointments in day/week/month view
- [x] Can create appointment via step wizard with availability checking
- [ ] Double-booking prevented (practitioner, room, equipment)
- [x] Status transitions work: scheduled → confirmed → checked_in → in_progress → completed
- [x] Treatment notes captured on completion (areas, settings, reaction)
- [x] Equipment shot log recorded per appointment
- [x] Practitioner schedules editable as weekly grid
- [x] Time off requests with approval workflow
- [x] Waitlist with priority ordering
- [ ] Daily agenda page works for reception
- [x] Activity log on appointment shows full timeline

---

## BATCH 4: BILLING + ACCOUNTING ✅ COMPLETED

### Billing Module ✅
- [x] BillingManifest.php (removed - using module.json instead)

#### Models ✅
- [x] Invoice.php - BaseModel, HasStateMachine, HasSequence, HasActivity
  - [x] Fields: code, patient_id, branch_id, appointment_id, type (standard/credit_note/proforma)
  - [x] Fields: status, subtotal_minor, discount_minor, tax_minor, total_minor, paid_minor, remaining_minor
  - [x] Fields: notes, due_date, issued_at, paid_at, cancelled_at, created_by_user_id
  - [x] State machine: draft → issued → partially_paid → paid (also: overdue, cancelled, refunded)
  - [x] Computed: is_overdue, payment_progress_percentage
- [x] InvoiceLine.php
  - [x] Fields: invoice_id, treatment_id, description, quantity, unit_price_minor
  - [x] Fields: discount_minor, discount_type, tax_rate, tax_minor, total_minor
  - [x] Fields: package_subscription_id, gift_card_id, sort_order
- [x] Payment.php - BaseModel, HasActivity
  - [x] Fields: code, invoice_id, journal_id (payment method via Journal), amount_minor
  - [x] Fields: reference_number, gateway_transaction_id, gift_card_id
  - [x] Fields: received_by_user_id, notes, paid_at
  - [x] Payment methods now use Journals: Cash, Card, Bank, Wallet, Insurance, Online
- [x] TaxRate.php
  - [x] Fields: name (jsonb), rate (decimal), is_default, is_active
- [x] InstallmentPlan.php
  - [x] Fields: invoice_id, total_installments, installment_amount_minor, frequency, start_date, status
- [x] InstallmentSchedule.php
  - [x] Fields: installment_plan_id, installment_number, amount_minor, due_date, paid_at, payment_id, status

#### Filament Resources ✅
- [x] InvoiceResource.php
  - [x] List: code, patient, total, paid, remaining, status badge, date
  - [x] Stats header: today's revenue, month revenue, outstanding total
  - [x] Filters: status, branch, date range, payment method, overdue only
  - [x] Create/Edit: patient select, branch, type, date, due date, line items
  - [x] View: status bar, lines, payment history, actions
  - [x] Record Payment action: modal with amount, method, reference
- [x] PaymentResource.php - Read-only list for reconciliation

#### Filament Widgets ✅
- [x] RevenueWidget.php (today + this month)
- [x] OutstandingWidget.php (unpaid invoices total)
- [x] PaymentMethodBreakdownWidget.php (pie chart)

#### Services ✅
- [x] InvoiceCalculationService.php - All integer arithmetic
- [ ] AutoInvoiceService.php - Creates invoice on AppointmentCompleted (deferred to integration)

#### Database Migrations ✅
- [x] create_invoices_table.php
- [x] create_invoice_lines_table.php
- [x] create_payments_table.php
- [x] create_tax_rates_table.php
- [x] create_installment_plans_table.php
- [x] create_installment_schedule_table.php

#### Database Seeders ✅
- [x] TaxRateSeeder.php (VAT 14% for Egypt) - seeded manually

---

### Accounting Module ✅
- [x] AccountingManifest.php (using module.json instead)

#### Models ✅
- [x] ChartOfAccount.php
  - [x] Self-referencing tree: parent_id
  - [x] Fields: code, name (jsonb), type (asset/liability/equity/revenue/expense)
  - [x] Fields: sub_type, is_system, balance_minor, is_active
- [x] JournalEntry.php - BaseModel, HasStateMachine, HasSequence, HasActivity
  - [x] Fields: code, date, reference, description, source_type/source_id (polymorphic)
  - [x] Fields: status (draft/posted/cancelled), total_debit_minor, total_credit_minor
  - [x] Fields: fiscal_period_id, created_by_user_id, posted_at
  - [x] Validation: total_debit = total_credit
- [x] JournalEntryLine.php
  - [x] Fields: journal_entry_id, account_id, debit_minor, credit_minor, description
  - [x] Fields: branch_id (cost center), partner_type/partner_id (polymorphic)
- [x] FiscalPeriod.php
  - [x] Fields: name, start_date, end_date, status (open/closed/locked), closed_by, closed_at
- [x] Journal.php - Odoo-style journals for organizing entries
  - [x] Fields: code, name (jsonb), type (sales/purchase/cash/bank/general)
  - [x] Fields: sequence_prefix, next_sequence, default_debit_account_id, default_credit_account_id
  - [x] Fields: is_active, description
  - [x] Method: getNextSequence() - generates codes like SAL/2026/00001

#### Filament Resources ✅
- [x] ChartOfAccountResource.php - List view with hierarchy
- [x] JournalResource.php - Manage journals (Sales, Purchase, Cash, Bank, Misc)
- [x] JournalEntryResource.php
  - [x] Create: date, reference, description, lines (account, debit, credit)
  - [x] Balance check warning
  - [x] Actions: Post, Reverse (with auto-reversal entry)
- [x] FiscalPeriodResource.php - Period management with close/reopen/lock actions

#### Filament Pages (deferred to reporting batch)
- [ ] TrialBalancePage.php
- [ ] ProfitLossPage.php (by period/branch)
- [ ] BalanceSheetPage.php
- [ ] GeneralLedgerPage.php
- [ ] CashFlowPage.php

#### Services ✅
- [x] AccountingIntegrationService.php (in Billing module)
  - [x] createInvoiceJournalEntry() - Debit AR, Credit Revenue + Tax Payable
  - [x] createPaymentJournalEntry() - Debit Cash/Bank, Credit AR
  - [x] createRefundJournalEntry() - Reverses payment entry

#### Listeners ✅ (implemented via model events)
- [x] Invoice::issue() → creates journal entry via AccountingIntegrationService
- [x] Payment::created → creates journal entry via AccountingIntegrationService
- [ ] CreateJournalOnRefund.php (method exists, needs event trigger)

#### Database Migrations ✅
- [x] create_chart_of_accounts_table.php
- [x] create_journal_entries_table.php
- [x] create_journal_entry_lines_table.php
- [x] create_fiscal_periods_table.php
- [x] create_journals_table.php

#### Database Seeders ✅
- [x] ChartOfAccountSeeder.php (full COA: 1000-5990) - seeded 34 accounts
- [x] FiscalPeriodSeeder.php - created 2026 fiscal year
- [x] JournalSeeder.php - created SAL, PUR, CSH, BNK, MISC + payment journals

#### Lang Files ✅
- [x] en/accounting.php
- [x] ar/accounting.php

---

### BATCH 4 CHECKPOINT
- [x] Can create invoices with line items and auto tax calculation
- [x] All amounts are integers (minor units) — no float math anywhere
- [x] Record payments: cash, card, bank transfer — partial payments work
- [x] Installment plans create schedule with due dates
- [ ] Auto-invoice fires when appointment completes (if setting on)
- [x] Journal entries auto-created: on invoice issue, on payment
- [x] Journal entries always balance (debit = credit validation)
- [ ] Trial balance page shows correct numbers (deferred to reporting)
- [ ] P&L report filters by period and branch (deferred to reporting)
- [x] Chart of accounts pre-seeded (1000-5990)
- [x] Fiscal periods: can close period to prevent back-dating

---

## BATCH 5: PACKAGES + GIFT CARDS + MEMBERSHIPS ✅ COMPLETED

### Packages Module ✅
- [x] PackagesManifest.php (using module.json instead)

#### Models ✅
- [x] Package.php
  - [x] Fields: name (jsonb), type (session_bundle/value_bundle), base_price_minor
  - [x] Fields: validity_days, is_transferable, is_active, sort_order
- [x] PackageItem.php
  - [x] Fields: package_id, treatment_id, quantity
- [x] PackageSubscription.php - BaseModel, HasStateMachine, HasActivity
  - [x] Fields: patient_id, package_id, invoice_id, status (active/completed/expired/cancelled/frozen)
  - [x] Fields: purchased_at, expires_at, frozen_at, frozen_until
  - [x] Computed: sessions_used, sessions_remaining (per treatment)
- [x] PackageSessionUsage.php
  - [x] Fields: subscription_id, treatment_id, appointment_id, used_at, notes

#### Extensions (deferred to integration phase)
- [ ] PatientModelExtension.php → adds packageSubscriptions() relation
- [ ] PatientFormExtension.php → adds "Packages" tab
- [ ] AppointmentFormExtension.php → adds package session selector
- [ ] InvoiceFormExtension.php → adds package purchase as line type

#### Filament Resources ✅
- [x] PackageResource.php - List, Form with items repeater
- [x] SubscriptionsRelationManager.php - Manage package subscriptions

#### Database Migrations ✅
- [x] create_packages_table.php
- [x] create_package_items_table.php
- [x] create_package_subscriptions_table.php
- [x] create_package_session_usages_table.php

#### Lang Files ✅
- [x] en/packages.php
- [x] ar/packages.php

---

### GiftCards Module ✅
- [x] GiftCardsManifest.php (using module.json instead)

#### Models ✅
- [x] GiftCard.php - BaseModel, HasStateMachine, HasSequence, HasActivity
  - [x] Fields: code (GC-2024-000001), purchaser_patient_id, recipient_patient_id
  - [x] Fields: initial_value_minor, remaining_value_minor
  - [x] Fields: status (draft/active/partially_used/fully_used/expired/cancelled)
  - [x] Fields: purchased_via_invoice_id, expires_at, activated_at, notes
- [x] GiftCardTransaction.php
  - [x] Fields: gift_card_id, type (activate/redeem/refund/adjust/expire)
  - [x] Fields: amount_minor, running_balance_minor, invoice_id, payment_id
  - [x] Fields: notes, created_by_user_id

#### Extensions (deferred to integration phase)
- [ ] PatientModelExtension.php
- [ ] PatientFormExtension.php → adds "Gift Cards" tab
- [ ] InvoiceFormExtension.php → gift card as payment option
- [ ] GiftCardDashboardWidget.php

#### Filament Resources ✅
- [x] GiftCardResource.php - List, View with transaction timeline, Create
- [x] TransactionsRelationManager.php - Transaction history

#### Listeners ✅
- [x] ActivateGiftCardOnInvoicePaid.php

#### Database Migrations ✅
- [x] create_gift_cards_table.php
- [x] create_gift_card_transactions_table.php

#### Lang Files ✅
- [x] en/giftcards.php
- [x] ar/giftcards.php

---

### Memberships Module ✅
- [x] MembershipsManifest.php (using module.json instead)

#### Models ✅
- [x] Membership.php
  - [x] Fields: name (jsonb), tier (silver/gold/platinum/diamond)
  - [x] Fields: price_monthly_minor, price_yearly_minor, discount_percentage
  - [x] Fields: included_sessions_monthly (jsonb), loyalty_multiplier, priority_booking
  - [x] Fields: is_active, sort_order
- [x] MembershipSubscription.php - BaseModel, HasStateMachine, HasActivity
  - [x] Fields: patient_id, membership_id, status (active/expired/cancelled/frozen)
  - [x] Fields: started_at, expires_at, auto_renew, renewal_invoice_id

#### Extensions (deferred to integration phase)
- [ ] PatientModelExtension.php → adds membershipSubscription(), is_member, member_discount
- [ ] PatientFormExtension.php → adds "Membership" tab
- [ ] InvoiceModelExtension.php → auto-apply member discount

#### Filament Resources ✅
- [x] MembershipResource.php - List, Form with tier config
- [x] SubscriptionsRelationManager.php - Manage member subscriptions

#### Database Migrations ✅
- [x] create_memberships_table.php
- [x] create_membership_subscriptions_table.php

#### Lang Files ✅
- [x] en/memberships.php
- [x] ar/memberships.php

---

### BATCH 5 CHECKPOINT
- [x] Can create packages with multiple treatments × quantities
- [x] Patient can purchase package → sessions tracked
- [ ] When booking, can select "use package session" to consume from package (needs integration)
- [x] Package status: active → completed when all sessions used
- [x] Gift cards: create, activate, redeem (partial), track balance
- [ ] Gift card as payment method on invoice works (needs integration)
- [x] Gift card auto-activates when purchase invoice paid
- [x] Memberships: create tiers with discount percentage
- [ ] Member discount auto-applied on new invoices (needs integration)
- [ ] All three modules add tabs to Patient detail via FormExtension (deferred)
- [ ] Patient model has new relationships from ModelExtensions (deferred)
- [ ] Accounting: deferred revenue journals for packages/gift cards (deferred)

---

## BATCH 6: INVENTORY + STAFF + PAYROLL ✅ COMPLETED

### Inventory Module ✅
- [x] module.json (InventoryManifest equivalent)

#### Models ✅
- [x] ProductCategory.php - self-referencing tree, translatable name
- [x] Product.php - consumables: creams, needles, gels, etc.
  - [x] Fields: sku, name (jsonb), description (jsonb), unit, cost_price_minor, sell_price_minor
  - [x] Fields: reorder_point, reorder_quantity, is_active, type (consumable/product/session_bundle)
  - [x] Relationships: category(), stockLevels(), stockMovements(), purchaseOrderLines()
  - [x] Computed: isLowStock(), getStockForBranch()
- [x] StockLevel.php - per product per branch
  - [x] Fields: product_id, branch_id, quantity, reserved_quantity, last_counted_at
  - [x] Composite unique: [product_id, branch_id]
- [x] StockMovement.php - in/out/transfer/adjustment
  - [x] Fields: product_id, from_branch_id, to_branch_id, type (in/out/transfer/adjustment)
  - [x] Fields: quantity, reference_type, reference_id, cost_per_unit_minor, notes
- [x] Supplier.php - vendor management
  - [x] Fields: code, name (jsonb), email, phone, address, contact_person, notes, is_active
  - [x] HasSequence trait for SUP- codes
- [x] PurchaseOrder.php - HasStateMachine: draft → sent → partially_received → received
  - [x] Fields: code, supplier_id, branch_id, status, order_date, expected_date
  - [x] Fields: subtotal_minor, tax_minor, total_minor, received_at, notes
  - [x] State machine with transitions: send(), receive(), cancel()
  - [x] HasSequence trait for PO- codes
- [x] PurchaseOrderLine.php
  - [x] Fields: purchase_order_id, product_id, quantity_ordered, quantity_received
  - [x] Fields: unit_price_minor, tax_rate, total_minor

#### Filament Resources ✅
- [x] ProductCategoryResource.php - List, Form with parent select
- [x] ProductResource.php
  - [x] List: sku, name, category, unit, cost, sell price, stock status
  - [x] Filters: category, is_active, type
  - [x] Form tabs: Details, Pricing, Stock Info
  - [x] Relation managers: StockLevelsRelationManager, StockMovementsRelationManager
- [x] SupplierResource.php - List, Form with contact info
- [x] PurchaseOrderResource.php
  - [x] List: code, supplier, branch, status badge, total, order_date
  - [x] Form tabs: Order Details, Line Items
  - [x] Actions: Send, Receive, Cancel
  - [x] Relation managers: LinesRelationManager

#### Key Features
- [x] Products with stock levels per branch
- [x] Stock tracking with reserved quantity
- [ ] Auto-deduct consumables when appointment completes (listener exists, needs wiring)
- [ ] Reorder alerts when stock < reorder_point (widget needed)
- [ ] Inter-branch stock transfers (model supports, UI needed)
- [x] Purchase order workflow with receiving

#### Listeners
- [x] DeductStockOnAppointmentCompleted.php (implemented, needs event wiring)

#### Extensions (deferred to integration phase)
- [ ] TreatmentFormExtension.php → adds "Consumables" tab

#### Database Migrations ✅
- [x] create_product_categories_table.php
- [x] create_products_table.php
- [x] create_stock_levels_table.php
- [x] create_stock_movements_table.php
- [x] create_suppliers_table.php
- [x] create_purchase_orders_table.php
- [x] create_purchase_order_lines_table.php

#### Lang Files ✅
- [x] en/inventory.php
- [x] ar/inventory.php

---

### Staff Module ✅
- [x] module.json (StaffManifest equivalent)

#### Models ✅
- [x] StaffProfile.php - extends User with HR data
  - [x] Fields: user_id, branch_id, employee_number, job_title, department
  - [x] Fields: commission_type (flat/percentage/tiered), commission_percentage
  - [x] Fields: base_salary_minor, date_of_joining, date_of_termination
  - [x] Fields: bank_name, bank_account, tax_id, social_insurance_number
  - [x] Method: calculateCommission(amountMinor, treatmentId)
  - [x] Relationships: user(), branch(), commissions(), commissionRecords()
- [x] StaffCommission.php - commission rules per treatment/category
  - [x] Fields: staff_profile_id, treatment_id, treatment_category_id, type
  - [x] Fields: flat_amount_minor, percentage, tier_rules_json
- [x] StaffCommissionRecord.php - actual earnings per appointment
  - [x] Fields: staff_profile_id, appointment_id, commission_id, amount_minor
  - [x] Fields: status (pending/approved/paid), source_amount_minor, calculation_details_json
  - [x] State machine: approve(), markAsPaid()

#### Filament Resources ✅
- [x] StaffProfileResource.php
  - [x] List: employee_number, user name, job_title, department, branch, commission_type
  - [x] Form tabs: Personal Info, Employment, Commission Settings, Banking
  - [x] Relation managers: CommissionsRelationManager, CommissionRecordsRelationManager

#### Key Features
- [x] Staff profiles linked to users with HR data
- [x] Commission types: flat, percentage, tiered
- [x] Per-treatment commission rules
- [ ] Commission auto-calculated when appointment completes (listener exists, needs wiring)
- [x] Commission approval workflow (pending → approved → paid)

#### Listeners
- [x] CalculateCommissionOnAppointmentCompleted.php (implemented, needs event wiring)

#### Extensions (deferred to integration phase)
- [ ] UserFormExtension.php → adds "Staff Profile" tab

#### Database Migrations ✅
- [x] create_staff_profiles_table.php
- [x] create_staff_commissions_table.php
- [x] create_staff_commission_records_table.php

#### Lang Files ✅
- [x] en/staff.php
- [x] ar/staff.php

---

### Payroll Module ✅
- [x] module.json (PayrollManifest equivalent)

#### Models ✅
- [x] PayrollRun.php - HasStateMachine: draft → approved → paid
  - [x] Fields: code, year, month, status (draft/approved/paid/cancelled)
  - [x] Fields: total_base_salary_minor, total_commissions_minor, total_bonuses_minor
  - [x] Fields: total_deductions_minor, total_tax_minor, total_social_insurance_minor
  - [x] Fields: total_net_salary_minor, notes, approved_by, approved_at, paid_by, paid_at
  - [x] State machine: approve(), markAsPaid(), cancel()
  - [x] HasSequence trait for PAY- codes
  - [x] Events: PayrollPaid dispatched on markAsPaid()
- [x] PayrollLine.php - per employee salary calculation
  - [x] Fields: payroll_run_id, staff_profile_id, base_salary_minor
  - [x] Fields: commissions_minor, bonuses_minor, deductions_minor
  - [x] Fields: tax_minor, social_insurance_minor, net_salary_minor
  - [x] Fields: commission_records_json, bonus_details_json, deduction_details_json, notes
  - [x] Auto-calculation: net = base + commissions + bonuses - deductions - tax - social
  - [x] Egyptian tax brackets calculation in calculateTax() method

#### Filament Resources ✅
- [x] PayrollRunResource.php
  - [x] List: code, period (year/month), status badge, total_net_salary, approved_at
  - [x] Form tabs: Period Info, Totals, Notes
  - [x] Actions: Approve, Mark as Paid, Cancel
  - [x] Relation managers: LinesRelationManager
- [x] LinesRelationManager.php - Employee salary details

#### Key Features
- [x] Monthly payroll runs with status workflow
- [x] Per-employee salary line calculation
- [x] Egyptian tax brackets implementation
- [x] Social insurance deduction support
- [ ] PDF salary slips (needs implementation)
- [x] Journal entries for salary expenses (listener implemented)

#### Listeners ✅
- [x] CreateSalaryJournalOnPayrollPaid.php
  - [x] Creates journal entry with: Salary Expense (debit), Tax Payable (credit), SI Payable (credit), Cash (credit)

#### Events ✅
- [x] PayrollPaid.php - dispatched when payroll marked as paid

#### Database Migrations ✅
- [x] create_payroll_runs_table.php
- [x] create_payroll_lines_table.php

#### Lang Files ✅
- [x] en/payroll.php
- [x] ar/payroll.php

---

### BATCH 6 CHECKPOINT
- [x] Products with stock levels per branch
- [~] Stock auto-deducts on appointment completion (listener implemented, needs event wiring)
- [ ] Low stock alerts on dashboard (widget needed)
- [x] Purchase orders with receive workflow
- [~] Staff commissions calculated per appointment (listener implemented, needs event wiring)
- [x] Commission approval workflow
- [~] Payroll run generates salary slips (PDF generation pending)
- [x] All financial transactions create journal entries (PayrollPaid listener implemented)

---

## BATCH 7: MARKETING ✅ COMPLETED

### Marketing Module ✅ (Unified module supporting WhatsApp, SMS, Email)
- [x] module.json (MarketingManifest equivalent)

#### Models ✅
- [x] MessageTemplate.php - Templates for all channels (WhatsApp, SMS, Email)
  - [x] Fields: code, name (jsonb), channel, type, subject (jsonb), content (jsonb)
  - [x] Fields: whatsapp_template_name, whatsapp_template_namespace, variables_json
  - [x] Method: render(variables, locale) - renders template with variable substitution
  - [x] Template types: appointment_confirmation, reminder, followup, invoice_receipt, birthday, promotional, custom
- [x] Campaign.php - Marketing campaigns with state machine
  - [x] Fields: name (jsonb), description (jsonb), template_id, channel, audience_filters_json
  - [x] Fields: status, scheduled_at, started_at, completed_at, cancelled_at
  - [x] Fields: total_recipients, sent_count, delivered_count, read_count, failed_count
  - [x] State machine: draft → scheduled → sending → completed (also: paused, cancelled)
  - [x] Computed: delivery_rate, read_rate, failure_rate, progress_percentage
- [x] CampaignRecipient.php - Tracks each recipient in a campaign
  - [x] Fields: campaign_id, patient_id, phone, email, status, variables_json
  - [x] Fields: sent_at, delivered_at, read_at, failed_at, error_message, provider_message_id
  - [x] Status: pending → sent → delivered → read (or failed/bounced/unsubscribed)
- [x] NotificationLog.php - All sent messages across all channels
  - [x] Fields: patient_id, channel, type, template_id, campaign_id, recipient_address
  - [x] Fields: subject, content, variables_json, status, provider_message_id
  - [x] Fields: error_message, cost_minor, reference_type, reference_id
  - [x] Timestamps: queued_at, sent_at, delivered_at, read_at, failed_at
- [x] AutomationRule.php - Automated triggers for notifications
  - [x] Fields: name (jsonb), description (jsonb), trigger_type, template_id, channel
  - [x] Fields: timing_type (immediate/before/after), timing_value, timing_unit
  - [x] Fields: conditions_json, is_active, priority
  - [x] Triggers: appointment_confirmed/reminder/completed, invoice_issued/paid/overdue, birthday, etc.

#### Services ✅
- [x] WhatsAppService.php - WhatsApp Business Cloud API integration
  - [x] sendTextMessage() - for 24h window responses
  - [x] sendTemplateMessage() - for business-initiated messages
  - [x] parseWebhookStatus() - parse Meta webhook payloads
- [x] SmsService.php - Multiple SMS provider support
  - [x] Twilio integration
  - [x] Vonage (Nexmo) integration
  - [x] VictoryLink (Egyptian provider) integration
- [x] EmailService.php - Email sending via Laravel Mail
  - [x] send() - send HTML/text emails
  - [x] sendTemplate() - send via Blade template
  - [x] buildAppointmentReminderHtml() - ready-to-use HTML templates
  - [x] buildInvoiceReceiptHtml() - ready-to-use HTML templates
- [x] NotificationService.php - Unified notification facade
  - [x] send() - send via specified channel
  - [x] sendTemplate() - send using MessageTemplate with variable substitution
  - [x] sendWithFallback() - try channels in priority order

#### Filament Resources ✅
- [x] MessageTemplateResource.php
  - [x] List: code, name, channel badge, type, is_active
  - [x] Filters: channel, type, is_active
  - [x] Form tabs: Details, Content (English/Arabic), WhatsApp Settings, Settings
  - [x] Actions: Edit, Duplicate
- [x] CampaignResource.php
  - [x] List: name, channel badge, status badge, recipients, sent, delivered, scheduled_at
  - [x] Filters: channel, status
  - [x] Form: name, description, channel, template, audience filters, scheduling
  - [x] View page: statistics, timing info, recipients relation
  - [x] Actions: Schedule, Start Now, Pause, Resume, Cancel
  - [x] Relation managers: RecipientsRelationManager
- [x] AutomationRuleResource.php
  - [x] List: name, trigger, channel badge, timing, is_active, priority
  - [x] Filters: trigger_type, channel, is_active
  - [x] Form: name, description, trigger, timing, channel, template, conditions
  - [x] Actions: Activate/Deactivate toggle
- [x] NotificationLogResource.php (read-only)
  - [x] List: patient, recipient, channel badge, type, status badge, sent_at, error
  - [x] Filters: channel, type, status, today, failed_only
  - [x] View: full notification details, timing, error info, provider response
  - [x] Actions: Retry (for failed)

#### Database Migrations ✅
- [x] create_message_templates_table.php
- [x] create_campaigns_table.php
- [x] create_campaign_recipients_table.php
- [x] create_notification_logs_table.php
- [x] create_automation_rules_table.php

#### Configuration ✅
- [x] config/marketing.php
  - [x] WhatsApp Business Cloud API settings
  - [x] SMS provider settings (Twilio, Vonage, VictoryLink)
  - [x] Email settings (from, tracking)
  - [x] Automation settings (reminder hours, followup days, channel priority)
  - [x] Rate limiting settings
  - [x] Template variables list

#### Lang Files ✅
- [x] en/marketing.php (comprehensive translations)
- [x] ar/marketing.php (comprehensive translations)

#### Key Features
- [x] Unified multi-channel support (WhatsApp, SMS, Email) in single module
- [x] Message templates with Arabic/English content
- [x] Campaign builder with audience filters
- [x] Campaign state machine with schedule/pause/resume
- [x] Delivery tracking: queued → sent → delivered → read
- [x] Automation rules with configurable triggers and timing
- [ ] Automated appointment reminders (listener ready, needs event wiring)
- [ ] Follow-up messages (listener ready, needs event wiring)
- [ ] Quota enforcement (needs integration with QuotaService)

---

### BATCH 7 CHECKPOINT
- [~] WhatsApp appointment reminders sent automatically (service ready, needs event wiring)
- [x] Campaign builder: filter audience → select template → schedule → send
- [x] Delivery tracking: sent/delivered/read/failed status
- [x] SMS and Email channels working (services implemented)
- [x] Notification log shows all messages across channels
- [ ] Quota enforcement: messages count against plan limits (needs integration)

---

## BATCH 8: LOYALTY + REPORTING

### Loyalty Module
- [ ] LoyaltyManifest.php

#### Models
- [ ] LoyaltyRule.php - earn: per_spend/per_visit/referral/birthday
- [ ] LoyaltyTransaction.php - earn/redeem/expire/adjust with running balance
- [ ] ReferralProgram.php - referrer + referred rewards

#### Extensions
- [ ] PatientModelExtension.php → adds loyalty_points, loyaltyTransactions()
- [ ] PatientFormExtension.php → adds "Loyalty" tab
- [ ] InvoiceModelExtension.php → points redemption as payment

#### Listeners
- [ ] AwardPointsOnPayment.php
- [ ] AwardPointsOnVisit.php
- [ ] AwardReferralBonus.php

---

### Reporting Module
- [ ] ReportingManifest.php

#### Filament Pages
- [ ] RevenueReportPage.php (by treatment, branch, practitioner, period)
- [ ] PatientReportPage.php (new vs returning, demographics, retention)
- [ ] AppointmentReportPage.php (utilization, no-show rate, peak hours)
- [ ] EquipmentReportPage.php (shots, maintenance cost, utilization)
- [ ] StaffPerformanceReportPage.php (revenue per practitioner, appointment count)
- [ ] InventoryReportPage.php (stock valuation, consumption rate)
- [ ] GiftCardReportPage.php (outstanding balance, redemption rate)
- [ ] CampaignReportPage.php (ROI, conversion, cost per acquisition)
- [ ] FinancialSummaryPage.php (executive: P&L, cash flow, receivables aging)

#### Each Report Has
- [ ] Date range filter, branch filter
- [ ] Summary cards at top
- [ ] Chart (bar/line/pie using Chart.js)
- [ ] Data table below
- [ ] Export buttons: PDF (dompdf), Excel (maatwebsite)

---

### BATCH 8 CHECKPOINT
- [ ] Loyalty points earned on payment + visit
- [ ] Points redeemable as payment on invoice
- [ ] Referral tracking works
- [ ] All 9 report pages load with correct data
- [ ] Reports filter by date range and branch
- [ ] PDF and Excel export functional
- [ ] Dashboard shows key metrics from all modules

---

## BATCH 9: PATIENT PORTAL + API

### PatientPortal Module
- [ ] PatientPortalManifest.php

#### Filament Panel: PortalPanel at /portal
- [ ] Auth: phone + OTP or email + password

#### Pages
- [ ] Dashboard: upcoming appointments, recent invoices, points balance
- [ ] Book Appointment: treatment → branch → date → time → confirm
- [ ] My Appointments: list with cancel/reschedule
- [ ] My Invoices: list with pay online button (Paymob)
- [ ] My Profile: edit personal info
- [ ] Gift Cards: check balance
- [ ] Loyalty: view points and history
- [ ] Consent Forms: view and download signed PDFs
- [ ] Photos: view before/after (if enabled by clinic)

---

### Api Module
- [ ] ApiManifest.php

#### REST API using Laravel Sanctum
- [ ] Patient auth: phone + OTP → token
- [ ] Staff auth: email + password → token
- [ ] Endpoints mirror portal functionality
- [ ] Rate limiting via QuotaService
- [ ] Module-aware: endpoints only available if module active
- [ ] Auto-docs via Scribe or manual OpenAPI spec

---

### BATCH 9 CHECKPOINT
- [ ] Patient can register and login to portal via OTP
- [ ] Patient can book appointments through portal
- [ ] Patient can view and pay invoices online
- [ ] API returns JSON with proper pagination
- [ ] API respects module activation (404 if module off)
- [ ] Rate limiting enforced per plan

---

## SUMMARY PROGRESS

| Batch | Module | Status | Progress |
|-------|--------|--------|----------|
| 1 | Core | Done | ~90% |
| 1 | Auth | Done | ~90% |
| 2 | Patients | Done | ~95% |
| 2 | Treatments | Done | ~90% |
| 3 | Equipment | Done | ~95% |
| 3 | Booking | Done | ~90% |
| 4 | Billing | Done | ~95% |
| 4 | Accounting | Done | ~90% |
| 5 | Packages | Done | ~90% |
| 5 | GiftCards | Done | ~90% |
| 5 | Memberships | Done | ~90% |
| 6 | Inventory | Done | ~85% |
| 6 | Staff | Done | ~85% |
| 6 | Payroll | Done | ~85% |
| 7 | Marketing (unified) | Done | ~90% |
| 8 | Loyalty | Not Started | 0% |
| 8 | Reporting | Not Started | 0% |
| 9 | PatientPortal | Not Started | 0% |
| 9 | Api | Not Started | 0% |

**Overall Progress: ~76% (15 of 19 modules implemented)**

Note: Marketing module is unified (WhatsApp + SMS + Email), reducing total from 21 to 19 modules.

### Remaining Work for Full Completion:
- **Cross-module integration**: Event wiring for listeners (DeductStock, CalculateCommission, SendNotification)
- **Dashboard widgets**: Low stock alerts, commission pending count, notification stats
- **PDF generation**: Salary slips, reports, invoices
- **Extensions**: Form extensions to add tabs to Patient, User, Treatment forms
- **Seeders**: Sample data seeders for demo/testing
- **Quota integration**: Marketing messages count against plan limits

---

## NEXT STEPS (Recommended Order)

1. ~~**Complete Core Module** - Add Sequence model, BranchResource, GeneralSettingsPage, UsageDashboardPage~~ ✅ DONE
2. ~~**Complete Auth Module** - Add DefaultRoleSeeder, DefaultPermissionSeeder, system role protection~~ ✅ DONE
3. ~~**Batch 3: Equipment + Booking Modules** - Core appointment functionality~~ ✅ DONE
4. ~~**Batch 4: Billing + Accounting** - Financial operations~~ ✅ DONE
5. ~~**Batch 5: Packages + GiftCards + Memberships** - Sales add-ons~~ ✅ DONE
6. ~~**Batch 6: Inventory + Staff + Payroll** - Supply chain + HR~~ ✅ DONE
7. ~~**Batch 7: Marketing Module** - Unified WhatsApp, SMS, Email campaigns~~ ✅ DONE
8. **Start Batch 8: Loyalty + Reporting** - Points system, analytics
9. **Start Batch 9: Patient Portal + API** - Self-service, REST endpoints
