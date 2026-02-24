# Treatment Session Implementation Plan

## Overview

This document outlines the complete implementation plan for the Treatment Session system, including dynamic service parameters, equipment tracking, and session management features.

---

## Current State

### Already Implemented (Basic)
- [x] Doctor Dashboard Page (basic queue view)
- [x] Treatment Session Page (basic session view)
- [x] Session Start/Resume from dashboard
- [x] Patient Info Display
- [x] Medical Alerts (Allergies/Contraindications)
- [x] Session Notes (Add/View)
- [x] Photo Upload (basic)
- [x] Treatment Plan Progress Display
- [x] Complete Session (basic)

---

## Phase 1: Core Parameter Infrastructure

### 1.1 Service Parameters Table
**File:** `modules/Services/database/migrations/xxxx_create_service_parameters_table.php`

```php
Schema::create('service_parameters', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('service_id');
    $table->string('parameter_key', 100);
    $table->json('parameter_config');
    // Config structure:
    // {
    //   "type": "number|text|select|boolean|decimal|date|time|range|textarea",
    //   "label": "Parameter Label",
    //   "required": true|false,
    //   "default_value": mixed,
    //   "unit": "nm|J|W|Hz|ms|°C|%",
    //   "min": number,
    //   "max": number,
    //   "step": number,
    //   "options": [{"value": "x", "label": "X"}],
    //   "help_text": "Description",
    //   "placeholder": "Enter value",
    //   "validation": {"pattern": "regex", "min_length": n, "max_length": n}
    // }
    $table->boolean('is_required')->default(false);
    $table->string('category', 50)->nullable(); // equipment_settings, clinical, safety, outcomes
    $table->integer('display_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
    $table->unique(['service_id', 'parameter_key']);
    $table->index(['service_id', 'is_active']);
    $table->index(['category', 'is_active']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `ServiceParameter` model
- [ ] Add relationship to `Service` model
- [ ] Create Filament Resource for managing parameters

---

### 1.2 Parameter Templates Table
**File:** `modules/Services/database/migrations/xxxx_create_parameter_templates_table.php`

```php
Schema::create('parameter_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->nullable(); // null = system template
    $table->string('template_name', 200);
    $table->string('template_code', 50)->unique();
    $table->text('description')->nullable();
    $table->string('service_category', 100)->nullable();
    $table->json('parameters'); // Array of parameter definitions
    $table->boolean('is_system')->default(false); // Read-only if true
    $table->boolean('is_active')->default(true);
    $table->uuid('created_by');
    $table->uuid('updated_by')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->index(['tenant_id', 'is_active']);
    $table->index(['service_category', 'is_active']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `ParameterTemplate` model
- [ ] Create seeder for default templates (Laser, IPL, Botox, etc.)
- [ ] Create Filament Resource for template management

---

### 1.3 Parameter Presets Table
**File:** `modules/Services/database/migrations/xxxx_create_parameter_presets_table.php`

```php
Schema::create('parameter_presets', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('service_id');
    $table->string('preset_name', 200);
    $table->text('description')->nullable();
    $table->json('preset_values'); // Key-value pairs of parameter values
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->uuid('created_by');
    $table->uuid('updated_by')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('service_id')->references('id')->on('services')->onDelete('cascade');
    $table->index(['service_id', 'is_active']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `ParameterPreset` model
- [ ] Add preset selection to Treatment Session UI

---

### 1.4 Treatment Session Data Table
**File:** `modules/Booking/database/migrations/xxxx_create_treatment_session_data_table.php`

```php
Schema::create('treatment_session_data', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('appointment_id'); // Links to appointment/session
    $table->string('parameter_key', 100);
    $table->json('parameter_value')->nullable();
    // Denormalized columns for efficient querying
    $table->string('value_text', 500)->nullable();
    $table->float('value_numeric')->nullable();
    $table->boolean('value_boolean')->nullable();
    $table->date('value_date')->nullable();
    $table->timestamp('recorded_at');
    $table->uuid('recorded_by');
    $table->timestamp('updated_at')->nullable();
    $table->uuid('updated_by')->nullable();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
    $table->unique(['appointment_id', 'parameter_key']);
    $table->index(['appointment_id', 'parameter_key']);
    $table->index('parameter_key');
    $table->index('value_numeric');
    $table->index('value_date');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `TreatmentSessionData` model
- [ ] Add denormalization logic
- [ ] Add relationship to `Appointment` model

---

### 1.5 Update Services Table
**File:** `modules/Services/database/migrations/xxxx_add_parameters_to_services_table.php`

```php
Schema::table('services', function (Blueprint $table) {
    $table->uuid('parameter_template_id')->nullable()->after('description');
    $table->enum('parameter_mode', ['none', 'template', 'custom'])->default('none')->after('parameter_template_id');
    $table->boolean('has_dynamic_parameters')->default(false)->after('parameter_mode');

    $table->foreign('parameter_template_id')->references('id')->on('parameter_templates')->onDelete('set null');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Update `Service` model with relationships
- [ ] Add parameter configuration to Service Filament Resource

---

### 1.6 Parameter Validation Service
**File:** `modules/Services/Services/ParameterValidationService.php`

```php
class ParameterValidationService
{
    public function validateSessionParameters(Appointment $appointment, array $values): array;
    public function validateParameter(array $definition, $value): bool;
    public function validateByType(string $type, $value, array $rules): bool;
    public function getValidationErrors(): array;
    public function checkRequiredComplete(Appointment $appointment): bool;
}
```

**Tasks:**
- [ ] Create service class
- [ ] Implement type-based validation (number, text, select, boolean, etc.)
- [ ] Implement min/max validation
- [ ] Implement pattern validation
- [ ] Implement required field checking

---

## Phase 2: Equipment Tracking System

### 2.1 Equipment Table (if not exists)
**File:** `modules/Booking/database/migrations/xxxx_create_equipment_table.php`

```php
Schema::create('equipment', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('branch_id');
    $table->string('name');
    $table->string('code')->unique();
    $table->string('type')->nullable(); // laser, ipl, rf, etc.
    $table->string('brand')->nullable();
    $table->string('model')->nullable();
    $table->string('serial_number')->nullable();
    $table->date('purchase_date')->nullable();
    $table->date('warranty_expiry')->nullable();
    $table->date('last_calibration')->nullable();
    $table->date('next_calibration')->nullable();
    $table->date('last_maintenance')->nullable();
    $table->date('next_maintenance')->nullable();
    $table->decimal('total_usage_hours', 10, 2)->default(0);
    $table->integer('total_treatments')->default(0);
    $table->boolean('tracking_enabled')->default(false);
    $table->enum('status', ['active', 'maintenance', 'inactive'])->default('active');
    $table->json('settings')->nullable(); // Default equipment settings
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
    $table->index(['tenant_id', 'status']);
    $table->index(['branch_id', 'status']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `Equipment` model
- [ ] Create Filament Resource for equipment management
- [ ] Add equipment to Room or Service relationship

---

### 2.2 Equipment Tracking Parameters Table
**File:** `modules/Booking/database/migrations/xxxx_create_equipment_tracking_parameters_table.php`

```php
Schema::create('equipment_tracking_parameters', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('equipment_id');
    $table->string('parameter_key', 50);
    $table->string('parameter_name', 255);
    $table->enum('value_type', ['integer', 'decimal', 'boolean', 'time', 'percentage']);
    $table->string('unit', 50)->nullable(); // J, W, Hz, ms, °C, pulses, shots, %
    $table->decimal('min_value', 10, 2)->nullable();
    $table->decimal('max_value', 10, 2)->nullable();
    $table->decimal('default_value', 10, 2)->nullable();
    $table->boolean('is_required')->default(false);
    $table->boolean('is_cumulative')->default(false); // Accumulate across sessions
    $table->boolean('track_in_session')->default(true);
    $table->text('description')->nullable();
    $table->integer('display_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
    $table->unique(['equipment_id', 'parameter_key']);
    $table->index(['equipment_id', 'is_active']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `EquipmentTrackingParameter` model
- [ ] Add to Equipment Filament Resource as relation manager

---

### 2.3 Equipment Session Metrics Table
**File:** `modules/Booking/database/migrations/xxxx_create_equipment_session_metrics_table.php`

```php
Schema::create('equipment_session_metrics', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('appointment_id');
    $table->uuid('equipment_id');
    $table->string('parameter_key', 50);
    $table->decimal('value', 15, 4);
    $table->timestamp('recorded_at');
    $table->uuid('recorded_by')->nullable();
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
    $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
    $table->index(['appointment_id', 'equipment_id']);
    $table->index('parameter_key');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `EquipmentSessionMetric` model
- [ ] Add method to calculate cumulative totals

---

### 2.4 Equipment Usage Log Table
**File:** `modules/Booking/database/migrations/xxxx_create_equipment_usage_logs_table.php`

```php
Schema::create('equipment_usage_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('equipment_id');
    $table->uuid('appointment_id')->nullable();
    $table->uuid('user_id'); // Operator
    $table->enum('action', ['start', 'stop', 'pause', 'resume', 'settings_change', 'emergency_stop']);
    $table->json('parameter_values')->nullable(); // Snapshot of values at action time
    $table->json('cumulative_values')->nullable(); // Running totals
    $table->text('notes')->nullable();
    $table->timestamp('action_at');
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('cascade');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('set null');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->index(['equipment_id', 'action_at']);
    $table->index(['appointment_id']);
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `EquipmentUsageLog` model
- [ ] Add logging methods to TreatmentSession

---

## Phase 3: Session Enhancement

### 3.1 Update Appointments Table
**File:** `modules/Booking/database/migrations/xxxx_add_session_fields_to_appointments_table.php`

```php
Schema::table('appointments', function (Blueprint $table) {
    // Equipment
    $table->uuid('equipment_id')->nullable()->after('room_id');
    $table->json('equipment_settings')->nullable()->after('equipment_id');

    // Session timing
    $table->timestamp('started_at')->nullable();
    $table->timestamp('paused_at')->nullable();
    $table->timestamp('resumed_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->integer('total_pause_duration')->default(0); // in seconds

    // Equipment metrics
    $table->integer('total_pulses')->default(0);
    $table->integer('total_shots')->default(0);
    $table->decimal('total_energy_joules', 10, 2)->default(0);

    // Parameters
    $table->json('parameters_data')->nullable(); // Cache of all parameter values
    $table->json('parameter_completion_status')->nullable();
    $table->timestamp('parameters_completed_at')->nullable();

    // Clinical notes
    $table->text('pre_treatment_notes')->nullable();
    $table->text('during_treatment_notes')->nullable();
    $table->text('post_treatment_notes')->nullable();

    // Patient feedback
    $table->tinyInteger('pain_level')->nullable(); // 1-10
    $table->tinyInteger('satisfaction_level')->nullable(); // 1-5

    // Vital signs
    $table->json('vital_signs')->nullable(); // {blood_pressure: "120/80", pulse: 72}

    // Skin condition
    $table->json('skin_condition_before')->nullable();
    $table->json('skin_condition_after')->nullable();

    // Adverse reactions
    $table->text('adverse_reactions')->nullable();

    // Follow-up
    $table->boolean('follow_up_required')->default(false);
    $table->date('recommended_next_session')->nullable();

    // Safety
    $table->boolean('informed_consent_confirmed')->default(false);
    $table->boolean('safety_protocols_confirmed')->default(false);

    // Consumables & Products
    $table->json('consumables_used')->nullable();
    $table->decimal('consumables_cost', 10, 2)->default(0);
    $table->json('products_sold')->nullable();
    $table->decimal('products_revenue', 10, 2)->default(0);

    $table->foreign('equipment_id')->references('id')->on('equipment')->onDelete('set null');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Update `Appointment` model with new fields
- [ ] Add equipment relationship
- [ ] Add session control methods (start, pause, resume, complete)

---

### 3.2 Session Consumables Table
**File:** `modules/Booking/database/migrations/xxxx_create_session_consumables_table.php`

```php
Schema::create('session_consumables', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('appointment_id');
    $table->uuid('inventory_item_id');
    $table->string('item_name');
    $table->decimal('quantity', 10, 2);
    $table->string('unit')->nullable();
    $table->decimal('unit_cost', 10, 2);
    $table->decimal('total_cost', 10, 2);
    $table->uuid('added_by');
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
    $table->index('appointment_id');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `SessionConsumable` model
- [ ] Add consumables UI to Treatment Session page

---

### 3.3 Session Products Table
**File:** `modules/Booking/database/migrations/xxxx_create_session_products_table.php`

```php
Schema::create('session_products', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id');
    $table->uuid('appointment_id');
    $table->uuid('product_id');
    $table->string('product_name');
    $table->decimal('quantity', 10, 2);
    $table->decimal('unit_price', 10, 2);
    $table->decimal('discount_percent', 5, 2)->default(0);
    $table->decimal('total_price', 10, 2);
    $table->uuid('added_by');
    $table->timestamps();

    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
    $table->foreign('appointment_id')->references('id')->on('appointments')->onDelete('cascade');
    $table->index('appointment_id');
});
```

**Tasks:**
- [ ] Create migration file
- [ ] Create `SessionProduct` model
- [ ] Add products UI to Treatment Session page

---

## Phase 4: Treatment Session UI

### 4.1 Dynamic Parameter Form Component
**File:** `modules/Booking/Resources/views/filament/components/parameter-form.blade.php`

**Tasks:**
- [ ] Create dynamic form renderer based on parameter type
- [ ] Implement each parameter type:
  - [ ] text input
  - [ ] number input (with min/max/step)
  - [ ] decimal input
  - [ ] select dropdown
  - [ ] multi-select
  - [ ] boolean checkbox/toggle
  - [ ] date picker
  - [ ] time picker
  - [ ] range slider
  - [ ] textarea
- [ ] Add unit display
- [ ] Add help text tooltips
- [ ] Add required field indicators
- [ ] Add validation error display

---

### 4.2 Equipment Assignment UI
**File:** `modules/Booking/Filament/Pages/TreatmentSession.php`

**Tasks:**
- [ ] Add equipment selection dropdown
- [ ] Load equipment parameters when selected
- [ ] Display equipment settings form
- [ ] Add equipment metrics tracking
- [ ] Add pause/resume equipment functionality
- [ ] Add emergency stop button

---

### 4.3 Consumables & Products UI

**Tasks:**
- [ ] Add consumables section with search
- [ ] Add products section with search
- [ ] Implement quantity and cost calculation
- [ ] Add remove item functionality
- [ ] Show running totals

---

### 4.4 Clinical Documentation UI

**Tasks:**
- [ ] Add pre-treatment notes section
- [ ] Add during-treatment notes section
- [ ] Add post-treatment notes section
- [ ] Add vital signs input
- [ ] Add skin condition assessment (before/after)
- [ ] Add adverse reactions field
- [ ] Add patient feedback (pain level, satisfaction)
- [ ] Add follow-up scheduling

---

### 4.5 Safety Checks UI

**Tasks:**
- [ ] Add informed consent confirmation checkbox
- [ ] Add safety protocols checklist
- [ ] Display allergy warnings prominently
- [ ] Display contraindication warnings
- [ ] Block session completion if safety checks incomplete

---

## Phase 5: Session Completion & Integration

### 5.1 Session Completion Logic

**Tasks:**
- [ ] Validate all required parameters are filled
- [ ] Validate equipment metrics are recorded
- [ ] Validate safety checks are complete
- [ ] Update appointment status to completed
- [ ] Update equipment cumulative totals
- [ ] Create equipment usage log entry
- [ ] Update treatment plan progress (if applicable)

---

### 5.2 Inventory Integration

**Tasks:**
- [ ] Deduct consumables from inventory on completion
- [ ] Create COGS journal entry for consumables
- [ ] Link products sold to sales order

---

### 5.3 Invoice Integration

**Tasks:**
- [ ] Generate invoice on session completion
- [ ] Include service fee
- [ ] Include products sold
- [ ] Apply any discounts

---

## Phase 6: Parameter Templates (Seeders)

### 6.1 Default Templates Seeder
**File:** `modules/Services/database/seeders/ParameterTemplatesSeeder.php`

**Templates to create:**
- [ ] Laser Hair Removal (14 parameters)
- [ ] IPL/PhotoFacial (13 parameters)
- [ ] Botox Injection (10 parameters)
- [ ] Dermal Filler (11 parameters)
- [ ] Microneedling (10 parameters)
- [ ] Chemical Peel (9 parameters)
- [ ] Body Contouring (10 parameters)
- [ ] Skin Tightening (10 parameters)

---

## Phase 7: Reporting & Analytics

### 7.1 Session Analytics

**Tasks:**
- [ ] Parameter statistics (min, max, avg, median)
- [ ] Session comparison across patients
- [ ] Equipment utilization reports
- [ ] Consumables usage reports
- [ ] Treatment outcomes tracking

---

## File Structure Summary

```
modules/
├── Services/
│   ├── Models/
│   │   ├── ServiceParameter.php
│   │   ├── ParameterTemplate.php
│   │   └── ParameterPreset.php
│   ├── Services/
│   │   └── ParameterValidationService.php
│   ├── database/migrations/
│   │   ├── xxxx_create_service_parameters_table.php
│   │   ├── xxxx_create_parameter_templates_table.php
│   │   ├── xxxx_create_parameter_presets_table.php
│   │   └── xxxx_add_parameters_to_services_table.php
│   └── database/seeders/
│       └── ParameterTemplatesSeeder.php
│
├── Booking/
│   ├── Models/
│   │   ├── Equipment.php
│   │   ├── EquipmentTrackingParameter.php
│   │   ├── EquipmentSessionMetric.php
│   │   ├── EquipmentUsageLog.php
│   │   ├── TreatmentSessionData.php
│   │   ├── SessionConsumable.php
│   │   └── SessionProduct.php
│   ├── Filament/
│   │   ├── Resources/
│   │   │   └── EquipmentResource.php
│   │   └── Pages/
│   │       └── TreatmentSession.php (enhanced)
│   ├── Resources/views/filament/
│   │   ├── pages/
│   │   │   └── treatment-session.blade.php (enhanced)
│   │   └── components/
│   │       ├── parameter-form.blade.php
│   │       ├── equipment-panel.blade.php
│   │       ├── consumables-panel.blade.php
│   │       └── products-panel.blade.php
│   └── database/migrations/
│       ├── xxxx_create_equipment_table.php
│       ├── xxxx_create_equipment_tracking_parameters_table.php
│       ├── xxxx_create_equipment_session_metrics_table.php
│       ├── xxxx_create_equipment_usage_logs_table.php
│       ├── xxxx_create_treatment_session_data_table.php
│       ├── xxxx_add_session_fields_to_appointments_table.php
│       ├── xxxx_create_session_consumables_table.php
│       └── xxxx_create_session_products_table.php
```

---

## Implementation Timeline

| Phase | Description | Duration |
|-------|-------------|----------|
| Phase 1 | Core Parameter Infrastructure | 1 week |
| Phase 2 | Equipment Tracking System | 1 week |
| Phase 3 | Session Enhancement | 1 week |
| Phase 4 | Treatment Session UI | 1-2 weeks |
| Phase 5 | Session Completion & Integration | 1 week |
| Phase 6 | Parameter Templates | 2-3 days |
| Phase 7 | Reporting & Analytics | 1 week |

**Total Estimated Time: 6-8 weeks**

---

## Notes

- All tables use UUIDs for primary keys (consistent with existing schema)
- All tables include tenant_id for multi-tenancy
- JSON columns are used for flexible data storage
- Denormalized columns added for efficient querying
- Soft deletes used where data should be preserved

---

## References

- Backup Project: `/var/www/html/var/www/x_linic_staging_backup_20260220_184120/`
- Key Models: `TreatmentSession.php`, `ServiceParameter.php`, `EquipmentTrackingParameter.php`
- Key Services: `TreatmentSessionService.php`, `ParameterValidationService.php`
