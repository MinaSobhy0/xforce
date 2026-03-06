# Service Category Configuration Feature

## Overview
Add Equipment, Consumables, Qualified Staff, Service Rooms, and Dynamic Parameters configuration at the **ServiceCategory** level. Services will inherit these configurations using an **Override** pattern with **Minimum** requirements.

## Behavior Rules
1. **Override**: If service has its own config, use it. If empty, fall back to category
2. **Minimum**: Category defines minimum requirements services should meet
3. **Default Template**: Category's `default_parameter_template_id` auto-fills new services

---

## Implementation Checklist

### Phase 1: Database Migrations
- [ ] Create `service_category_equipment` pivot table
- [ ] Create `service_category_qualified_staff` pivot table
- [ ] Create `service_category_rooms` pivot table
- [ ] Create `service_category_consumables` pivot table
- [ ] Add `default_parameter_template_id` to `service_categories`
- [ ] Run migrations on tenant database

### Phase 2: Model Updates
- [ ] Update `ServiceCategory` model - add `$fillable`
- [ ] Add `requiredEquipment()` relationship to ServiceCategory
- [ ] Add `qualifiedStaff()` relationship to ServiceCategory
- [ ] Add `rooms()` relationship to ServiceCategory
- [ ] Add `consumables()` relationship to ServiceCategory
- [ ] Add `defaultParameterTemplate()` relationship to ServiceCategory
- [ ] Update `Service` model - add `getEffectiveEquipment()` method
- [ ] Add `getEffectiveQualifiedStaff()` method to Service
- [ ] Add `getEffectiveRooms()` method to Service
- [ ] Add `getEffectiveConsumables()` method to Service
- [ ] Add boot logic to auto-assign parameter_template_id from category

### Phase 3: Filament Relation Managers
- [ ] Create `CategoryEquipmentRelationManager`
- [ ] Create `CategoryStaffRelationManager`
- [ ] Create `CategoryRoomsRelationManager`
- [ ] Create `CategoryConsumablesRelationManager`

### Phase 4: Filament Resource Updates
- [ ] Convert ServiceCategoryResource form to tabbed layout
- [ ] Add "Basic Info" tab (existing fields)
- [ ] Add "Equipment & Consumables" tab
- [ ] Add "Qualified Staff" tab
- [ ] Add "Service Rooms" tab
- [ ] Add "Dynamic Parameters" tab with default template select
- [ ] Create `ViewServiceCategory` page
- [ ] Register relation managers in resource
- [ ] Add translations for new labels

### Phase 5: Verification
- [ ] Run migrations successfully
- [ ] Test adding equipment to category via relation manager
- [ ] Test adding staff to category via relation manager
- [ ] Test adding rooms to category via relation manager
- [ ] Test adding consumables to category via relation manager
- [ ] Test setting default parameter template
- [ ] Test new service inherits parameter_template_id from category
- [ ] Test `getEffectiveEquipment()` returns category config when service is empty
- [ ] Test `getEffectiveEquipment()` returns service config when service has its own

---

## Database Schema

### Migration 1: Category Pivot Tables
**File**: `modules/Services/Database/Migrations/2026_03_02_000001_create_service_category_configuration_tables.php`

```
service_category_equipment
├── id
├── tenant_id (nullable, indexed)
├── service_category_id (FK -> service_categories, cascade)
├── equipment_id (FK -> equipment, cascade)
├── is_mandatory (boolean, default true)
├── timestamps
└── unique(service_category_id, equipment_id)

service_category_qualified_staff
├── id
├── tenant_id (nullable, indexed)
├── service_category_id (FK -> service_categories, cascade)
├── staff_profile_id (FK -> staff_profiles, cascade)
├── timestamps
└── unique(service_category_id, staff_profile_id)

service_category_rooms
├── id
├── tenant_id (nullable, indexed)
├── service_category_id (FK -> service_categories, cascade)
├── room_id (FK -> rooms, cascade)
├── is_primary (boolean, default false)
├── priority (integer, default 0)
├── timestamps
└── unique(service_category_id, room_id)

service_category_consumables
├── id
├── tenant_id (nullable, indexed)
├── service_category_id (FK -> service_categories, cascade)
├── product_id (FK -> products, cascade)
├── quantity (integer, default 1) -- units consumed per service
├── timestamps
└── unique(service_category_id, product_id)
```

### Migration 2: Add default_parameter_template_id
**File**: `modules/Services/Database/Migrations/2026_03_02_000002_add_default_parameter_template_to_service_categories.php`

```
service_categories
└── default_parameter_template_id (FK -> parameter_templates, nullable, set null on delete)
```

---

## Files to Modify

| File | Changes |
|------|---------|
| `modules/Services/Models/ServiceCategory.php` | Add fillable, relationships |
| `modules/Services/Models/Service.php` | Add effective config methods, boot logic |
| `modules/Services/Filament/Resources/ServiceCategoryResource.php` | Add tabs, default template select, relation managers |

## Files to Create

| File | Purpose |
|------|---------|
| `modules/Services/Database/Migrations/2026_03_02_000001_create_service_category_configuration_tables.php` | Pivot tables |
| `modules/Services/Database/Migrations/2026_03_02_000002_add_default_parameter_template_to_service_categories.php` | Template FK |
| `modules/Services/Filament/Resources/ServiceCategoryResource/RelationManagers/CategoryEquipmentRelationManager.php` | Equipment RM |
| `modules/Services/Filament/Resources/ServiceCategoryResource/RelationManagers/CategoryStaffRelationManager.php` | Staff RM |
| `modules/Services/Filament/Resources/ServiceCategoryResource/RelationManagers/CategoryRoomsRelationManager.php` | Rooms RM |
| `modules/Services/Filament/Resources/ServiceCategoryResource/RelationManagers/CategoryConsumablesRelationManager.php` | Consumables RM |
| `modules/Services/Filament/Resources/ServiceCategoryResource/Pages/ViewServiceCategory.php` | View page |

---

## Model Code Examples

### ServiceCategory Relationships
```php
public function requiredEquipment(): BelongsToMany
{
    return $this->belongsToMany(Equipment::class, 'service_category_equipment')
        ->withPivot(['is_mandatory'])
        ->withTimestamps();
}

public function qualifiedStaff(): BelongsToMany
{
    return $this->belongsToMany(StaffProfile::class, 'service_category_qualified_staff')
        ->withTimestamps();
}

public function rooms(): BelongsToMany
{
    return $this->belongsToMany(Room::class, 'service_category_rooms')
        ->withPivot(['is_primary', 'priority'])
        ->withTimestamps();
}

public function consumables(): BelongsToMany
{
    return $this->belongsToMany(Product::class, 'service_category_consumables')
        ->withPivot(['quantity'])
        ->withTimestamps();
}

public function defaultParameterTemplate(): BelongsTo
{
    return $this->belongsTo(ParameterTemplate::class, 'default_parameter_template_id');
}
```

### Service Effective Config Methods
```php
/**
 * Get effective equipment (service's own OR category's fallback).
 */
public function getEffectiveEquipment(): Collection
{
    if ($this->requiredEquipment()->exists()) {
        return $this->requiredEquipment;
    }

    return $this->category?->requiredEquipment ?? collect();
}

/**
 * Get effective qualified staff (service's own OR category's fallback).
 */
public function getEffectiveQualifiedStaff(): Collection
{
    if ($this->qualifiedStaff()->exists()) {
        return $this->qualifiedStaff;
    }

    return $this->category?->qualifiedStaff ?? collect();
}

/**
 * Get effective rooms (service's own OR category's fallback).
 */
public function getEffectiveRooms(): Collection
{
    if ($this->rooms()->exists()) {
        return $this->rooms;
    }

    return $this->category?->rooms ?? collect();
}

/**
 * Get effective consumables (service's own OR category's fallback).
 */
public function getEffectiveConsumables(): Collection
{
    // Note: Service uses consumables_required JSON field, category uses pivot
    if (!empty($this->consumables_required)) {
        return collect($this->consumables_required);
    }

    return $this->category?->consumables ?? collect();
}
```

### Service Boot Logic for Default Template
```php
protected static function booted(): void
{
    static::creating(function (Service $service) {
        // Auto-assign parameter template from category if not set
        if (empty($service->parameter_template_id) && $service->category_id) {
            $category = ServiceCategory::find($service->category_id);
            if ($category?->default_parameter_template_id) {
                $service->parameter_template_id = $category->default_parameter_template_id;
                $service->parameter_mode = 'template';
                $service->has_dynamic_parameters = true;
            }
        }
    });
}
```

---

## Verification Commands

```bash
# Run migrations
php artisan migrate --database=tenant --path=modules/Services/Database/Migrations

# Verify tables exist
php artisan tinker --execute="
    DB::statement('SET search_path TO \"tenant_demo\"');
    \$tables = ['service_category_equipment', 'service_category_qualified_staff',
                'service_category_rooms', 'service_category_consumables'];
    foreach (\$tables as \$t) {
        echo \$t . ': ' . (Schema::hasTable(\$t) ? 'YES' : 'NO') . PHP_EOL;
    }
"

# Test effective equipment
php artisan tinker --execute="
    DB::statement('SET search_path TO \"tenant_demo\"');
    \$service = Modules\\Services\\Models\\Service::first();
    dump(\$service->getEffectiveEquipment()->pluck('name'));
"
```
