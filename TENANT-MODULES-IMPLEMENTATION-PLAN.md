# LaserBase — Tenant Modules Implementation Plan
## Stack: Schema-per-tenant with PgBouncer (keeping as-is)
## You have: Super Admin panel + Framework kernel done
## Building: All clinic-side modules

---

# BUILD ORDER

```
BATCH 1 → Core + Auth           (branches, users, roles, permissions)
BATCH 2 → Patients + Treatments (CRM + treatment catalog)
BATCH 3 → Booking + Equipment   (appointments, calendar, machines)
BATCH 4 → Billing + Accounting  (invoices, payments, double-entry)
BATCH 5 → Packages + Gift Cards + Memberships (sales add-ons)
BATCH 6 → Inventory + Staff + Payroll (supply chain + HR)
BATCH 7 → Marketing             (WhatsApp, SMS, Email)
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

**CHECKPOINT BATCH 6:**
```
[ ] Products with stock levels per branch
[ ] Stock auto-deducts on appointment completion
[ ] Low stock alerts on dashboard
[ ] Purchase orders with receive workflow
[ ] Staff commissions calculated per appointment
[ ] Commission approval workflow
[ ] Payroll run generates salary slips
[ ] All financial transactions create journal entries
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

**CHECKPOINT BATCH 7:**
```
[ ] WhatsApp appointment reminders sent automatically
[ ] Campaign builder: filter audience → select template → schedule → send
[ ] Delivery tracking: sent/delivered/read/failed status
[ ] SMS and Email channels working
[ ] Notification log shows all messages across channels
[ ] Quota enforcement: messages count against plan limits
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
