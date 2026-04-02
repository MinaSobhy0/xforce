# CLAUDE.md - XForce HR Platform

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

XForce is an Odoo-inspired modular HR SaaS platform built on Laravel 11 + Filament 3 with PostgreSQL schema-based multi-tenancy. The platform provides comprehensive human resources management with real-time Odoo ERP integration for enterprise clients.

### Core Purpose
- **Primary Focus**: Human Resources Management (Recruitment, Employees, Attendance, Payroll, Leave, Performance)
- **Integration**: Bidirectional sync with Odoo ERP (HR, Payroll, Attendance modules)
- **Target Users**: HR departments, staffing agencies, enterprises needing standalone HR or Odoo-connected HR

## Tech Stack

- **Backend**: Laravel 11.x (PHP 8.2+)
- **Admin Panel**: Filament 3.x
- **Database**: PostgreSQL 15+ with schema-based multi-tenancy
- **Queue**: Redis + Laravel Horizon
- **Cache**: Redis
- **Search**: Laravel Scout (Meilisearch)
- **API**: RESTful + Odoo XML-RPC integration

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

# Odoo Sync
php artisan odoo:sync-employees      # Sync employees from Odoo
php artisan odoo:sync-attendance     # Sync attendance records
php artisan odoo:push-payroll        # Push payroll to Odoo

# Cache (production)
php artisan config:cache && php artisan route:cache && php artisan view:cache

# Tinker with tenant context
php artisan tinker --execute="DB::statement('SET search_path TO \"tenant_xxx\"'); ..."
```

## Architecture

### Multi-Tenancy (PostgreSQL Schema Isolation)

- **Central connection** (`pgsql`): Platform tables in `public` schema (tenants, subscription_plans, modules)
- **Tenant connection** (`tenant`): Per-tenant schemas named `tenant_{slug}` (employees, attendance, payroll)
- Schema switching via `SET search_path TO "tenant_xxx"`
- PgBouncer on port 6432 with `session` pool mode (required for search_path persistence)

### Module System

Each module follows this structure:
```
modules/{Module}/
├── Database/Migrations/     # Tenant-specific migrations (run during provisioning)
├── Database/Seeders/        # Seeders (added to TenantService::runTenantSeeders)
├── Filament/Resources/      # Filament admin resources
├── Models/                  # Eloquent models
├── Services/                # Business logic
├── Providers/               # Service providers
├── Jobs/                    # Queue jobs (especially for Odoo sync)
├── Lang/                    # Translations (en, ar)
└── module.json              # Module manifest
```

### HR Modules Structure

```
modules/
├── Core/                    # Framework core (tenancy, settings, branches)
├── Auth/                    # Authentication, roles, permissions
├── Employees/               # Employee profiles, documents, contracts
├── Recruitment/             # Job postings, applications, interviews
├── Attendance/              # Time tracking, shifts, overtime
├── Leave/                   # Leave requests, balances, policies
├── Payroll/                 # Salary, deductions, payslips
├── Performance/             # Reviews, goals, KPIs
├── Training/                # Courses, certifications, skills
├── Assets/                  # Company assets assigned to employees
├── Announcements/           # Internal communications
├── OdooIntegration/         # Odoo XML-RPC sync services
└── Reporting/               # HR analytics and reports
```

### Framework Kernel (`/framework/Core`)

Custom extensions to Laravel:
- `BaseModel` - All models extend this; auto-scopes by branch, handles PostgreSQL booleans
- `ModuleManager` - Boot order via topological sort (Kahn's algorithm)
- `TenantService` - Tenant provisioning (creates schema, runs migrations, runs seeders)

### Filament Panels (3 panels)

1. **SuperAdmin** (`/platform`) - Platform management for XForce admins
2. **Admin** (`/admin`) - Tenant HR staff (main panel)
3. **Employee** (`/self-service`) - Employee self-service portal

## Odoo Integration Architecture

### Connection Configuration
```php
// config/odoo.php
return [
    'url' => env('ODOO_URL'),           // https://odoo.company.com
    'db' => env('ODOO_DB'),             // odoo_production
    'username' => env('ODOO_USERNAME'), # admin@company.com
    'password' => env('ODOO_API_KEY'),  // API key (not password)
];
```

### Sync Patterns

1. **Pull from Odoo** (Import)
   - Employees: `hr.employee` → `employees` table
   - Departments: `hr.department` → `departments` table
   - Attendance: `hr.attendance` → `attendance_records` table

2. **Push to Odoo** (Export)
   - Payroll entries: `payroll_entries` → `hr.payslip`
   - Leave requests: `leave_requests` → `hr.leave`

3. **Webhook Events** (Real-time)
   - Employee created/updated in Odoo → sync to XForce
   - Attendance check-in/out → sync bidirectionally

### Odoo Service Pattern
```php
// modules/OdooIntegration/Services/OdooEmployeeService.php
class OdooEmployeeService
{
    public function syncFromOdoo(): void
    {
        $odooEmployees = $this->client->search_read('hr.employee', [...]);
        foreach ($odooEmployees as $data) {
            Employee::updateOrCreate(
                ['odoo_id' => $data['id']],
                $this->mapOdooToLocal($data)
            );
        }
    }

    public function pushToOdoo(Employee $employee): int
    {
        return $this->client->create('hr.employee', $this->mapLocalToOdoo($employee));
    }
}
```

### Sync Tracking
Every synced model should have:
```php
$table->integer('odoo_id')->nullable()->index();
$table->timestamp('odoo_synced_at')->nullable();
$table->string('odoo_sync_status')->default('pending'); // pending, synced, error
$table->text('odoo_sync_error')->nullable();
```

## Development Rules

### Code Standards

1. **PSR-12** coding standard (enforced by Laravel Pint)
2. **Strict types** in all PHP files: `declare(strict_types=1);`
3. **Type hints** on all method parameters and return types
4. **PHPStan** level 5 minimum for static analysis

### Model Conventions

1. **All models extend `BaseModel`** from framework
2. **Use traits** for common functionality:
   - `HasTenancy` - Automatic tenant_id scoping
   - `HasSequence` - Auto-numbering (EMP-0001)
   - `HasActivity` - Audit trail logging
   - `HasOdooSync` - Odoo synchronization fields and methods
   - `HasStateMachine` - Status workflows

3. **Soft deletes** on all HR-sensitive models
4. **Translations** via JSONB columns: `{'en': '...', 'ar': '...'}`

### Database Conventions

- **Primary keys**: Integer (not UUID)
- **Foreign keys**: `restrictOnDelete()` for critical data, `cascadeOnDelete()` for child records
- **Money fields**: Store in minor units as integer (`salary_minor` = cents/piasters)
- **Dates**: Use `date` type for dates, `timestamp` for datetime
- **Enums**: Use string columns with class constants, not database enums

### Module Development

#### Adding a New Module

1. Create module structure in `/modules/{ModuleName}/`
2. Add `module.json` with dependencies
3. Create ServiceProvider in `Providers/`
4. Register in `config/modules.php`

#### Adding a Migration

**IMPORTANT**: All tenant migrations MUST be in `modules/{Module}/Database/Migrations/`, NOT in `database/migrations/`.

```bash
php artisan make:migration create_employees_table --path=modules/Employees/Database/Migrations
```

#### Adding a Filament Resource

```bash
# Create resource in module
php artisan make:filament-resource Employee --model=Modules\\Employees\\Models\\Employee
# Then move to modules/Employees/Filament/Resources/
```

### Odoo Integration Rules

1. **Never block UI** for Odoo sync - use queued jobs
2. **Idempotent syncs** - running twice should produce same result
3. **Track sync status** - always update `odoo_synced_at` and `odoo_sync_status`
4. **Handle failures gracefully** - log errors, notify admins, allow retry
5. **Map IDs carefully** - maintain `odoo_id` reference on all synced models
6. **Respect rate limits** - batch operations, use delays between API calls
7. **Validate data** - Odoo data may not match our validation rules

### API Design

1. **RESTful endpoints** for mobile/external access
2. **Versioned API**: `/api/v1/employees`
3. **Sanctum tokens** for authentication
4. **Resource classes** for consistent JSON responses
5. **Rate limiting** per tenant

### Security Rules

1. **Never expose Odoo credentials** in responses or logs
2. **Tenant isolation** - always verify tenant access
3. **Audit logging** for sensitive HR operations
4. **Encrypt sensitive data** (salaries, national IDs)
5. **RBAC** via Spatie Permission, scoped per tenant

## Key Patterns

### Employee Lifecycle States
```php
const STATUS_DRAFT = 'draft';           // Being onboarded
const STATUS_ACTIVE = 'active';         // Currently employed
const STATUS_ON_LEAVE = 'on_leave';     // Extended leave
const STATUS_SUSPENDED = 'suspended';   // Temporarily suspended
const STATUS_TERMINATED = 'terminated'; // Employment ended
const STATUS_RESIGNED = 'resigned';     // Voluntarily left
```

### Leave Request Workflow
```
draft → submitted → approved/rejected → (if approved) taken → completed
                  ↘ cancelled
```

### Payroll Processing
```
draft → calculated → reviewed → approved → paid → (synced to Odoo)
```

## Common Issues & Solutions

### "relation does not exist" in tenant
Migration not in `modules/*/Database/Migrations/` - move it there.

### Odoo sync failures
1. Check `odoo_sync_error` column for details
2. Verify Odoo API credentials in `.env`
3. Check Odoo server accessibility
4. Review field mapping in sync service

### Duplicate key violation
Model auto-sets `branch_id` via BaseModel. Set `$autoSetBranchId = false` to disable.

### PgBouncer search_path issues
Ensure PgBouncer uses `session` pool mode, not `transaction` mode.

## Environment Variables

```env
# Odoo Integration
ODOO_ENABLED=true
ODOO_URL=https://odoo.company.com
ODOO_DB=odoo_production
ODOO_USERNAME=api@company.com
ODOO_API_KEY=your-api-key

# Queue (for Odoo sync jobs)
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# Multi-tenancy
DB_CONNECTION=pgsql
DB_PGBOUNCER=true
```

## File Structure Reference

```
xforce/
├── app/                     # Laravel app (helpers, base classes)
├── config/                  # Configuration files
├── database/migrations/     # ONLY central/platform migrations
├── framework/Core/          # Custom framework extensions
├── modules/                 # All HR modules (main codebase)
│   ├── Core/
│   ├── Auth/
│   ├── Employees/
│   ├── Attendance/
│   ├── Payroll/
│   ├── Leave/
│   ├── OdooIntegration/
│   └── ...
├── public/                  # Web root
├── resources/               # Views, assets
├── routes/                  # Route definitions
├── storage/                 # Logs, cache, uploads
└── tests/                   # Test suites
```

## Quick Reference

| Task | Command/Location |
|------|------------------|
| Create tenant migration | `modules/{Module}/Database/Migrations/` |
| Add Filament resource | `modules/{Module}/Filament/Resources/` |
| Odoo sync service | `modules/OdooIntegration/Services/` |
| Queue job for sync | `modules/OdooIntegration/Jobs/` |
| Translation files | `modules/{Module}/Lang/{en,ar}/` |
| Model with Odoo sync | Use `HasOdooSync` trait |
