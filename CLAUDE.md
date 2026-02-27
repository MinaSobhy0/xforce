# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

XLinic is an Odoo-inspired modular SaaS framework for laser and beauty clinics built on Laravel 11 + Filament 3 with PostgreSQL schema-based multi-tenancy.

## Common Commands

```bash
# Development
php artisan serve                    # Start dev server
npm run dev                          # Vite dev server

# Database
php artisan migrate                  # Run central migrations
php artisan migrate --database=tenant --path=modules/Core/Database/Migrations  # Tenant migrations

# Testing
./vendor/bin/pest                    # Run all tests
./vendor/bin/pest --filter=name      # Run specific test
./vendor/bin/phpstan analyse         # Static analysis

# Code Quality
./vendor/bin/pint                    # Auto-format PHP code
./vendor/bin/pint --test             # Check code style

# Tenant Management
php artisan tenant:create {name}     # Create tenant
php artisan tenant:migrate           # Migrate all tenants

# Cache (production)
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Tinker with tenant context
php artisan tinker --execute="DB::statement('SET search_path TO \"tenant_xxx\"'); ..."
```

## Architecture

### Multi-Tenancy (PostgreSQL Schema Isolation)

- **Central connection** (`pgsql`): Platform tables in `public` schema (tenants, subscription_plans, modules)
- **Tenant connection** (`tenant`): Per-tenant schemas named `tenant_{slug}` (patients, invoices, appointments)
- Schema switching via `SET search_path TO "tenant_xxx"`
- PgBouncer on port 6432 with `session` pool mode (required for search_path persistence)

### Module System (25 modules in `/modules`)

Each module follows this structure:
```
modules/{Module}/
├── Database/Migrations/     # Tenant-specific migrations (run during provisioning)
├── Database/Seeders/        # Seeders (added to TenantService::runTenantSeeders)
├── Filament/Resources/      # Filament admin resources
├── Models/                  # Eloquent models
├── Services/                # Business logic
├── Providers/               # Service providers
├── Lang/                    # Translations (en, ar)
└── module.json              # Module manifest
```

**Key modules**: Core, Auth, Patients, Services, Booking, Billing, Accounting, Inventory, Payroll, Staff

### Framework Kernel (`/framework/Core`)

Custom extensions to Laravel:
- `BaseModel` - All models extend this; auto-scopes by branch, handles PostgreSQL booleans
- `ModuleManager` - Boot order via topological sort (Kahn's algorithm)
- `TenantService` - Tenant provisioning (creates schema, runs migrations, runs seeders)

### Filament Panels (3 panels)

1. **SuperAdmin** (`/platform`) - Platform management for XLinic admins
2. **Admin** (`/admin`) - Tenant clinic staff (main panel)
3. **Owner** (`/owner`) - Clinic owner read-only view

## Key Patterns

### Model Traits (in `framework/Core/Model/Traits`)
- `HasTenancy` - Automatic tenant_id scoping
- `HasSequence` - Auto-numbering (INV-0001)
- `HasActivity` - Audit trail logging
- `HasStateMachine` - Status workflows

### BaseModel Auto-Branch Assignment
Models with `branch_id` in fillable get auto-assigned from `BranchContext`. Opt out with:
```php
protected bool $autoSetBranchId = false;
```

### Tenant Provisioning Flow
1. Create schema `tenant_{slug}`
2. Run all migrations from `modules/*/Database/Migrations`
3. Run seeders defined in `TenantService::runTenantSeeders()`

Migrations in `database/migrations/` are NOT run for tenants - only module migrations.

### Foreign Key Strategy
- Use `restrictOnDelete()` for accounting/financial tables (prevent data loss)
- Use `cascadeOnDelete()` for child records (invoice_lines, etc.)
- Soft deletes enabled on most models

## Database Conventions

- **Primary keys**: Integer (migrated from UUID)
- **Tenant ID**: Not needed in tenant tables (schema isolation handles it)
- **Translations**: JSONB columns with `{'en': '...', 'ar': '...'}`
- **Currency**: EGP (Egyptian Pound)
- **Tax**: 14% VAT
- **Timezone**: Africa/Cairo

## Module Development

### Adding a Migration
1. Create in `modules/{Module}/Database/Migrations/`
2. File is auto-included in tenant provisioning

### Adding a Seeder
1. Create in `modules/{Module}/Database/Seeders/`
2. Add to `TenantService::runTenantSeeders()` array
3. Run on existing tenants manually via tinker

### Filament Resource Navigation
```php
protected static ?string $navigationGroup = 'HR';  // Group name
protected static ?int $navigationSort = 10;         // Order within group
```

## Common Issues

### "relation does not exist" in tenant
Migration not in `modules/*/Database/Migrations/` - move it there from `database/migrations/`

### Duplicate key violation on branch_id
Model auto-sets branch_id via BaseModel creating event. Set `$autoSetBranchId = false` to disable.

### PgBouncer search_path issues
Ensure PgBouncer uses `session` pool mode, not `transaction` mode.
