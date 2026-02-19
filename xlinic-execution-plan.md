# XLinic SaaS — Complete Execution Plan
## AI Bot Build Instructions

> **PURPOSE**: This document is a complete, step-by-step execution plan for an AI coding
> assistant to build the XLinic SaaS platform from scratch. Follow each phase in order.
> Do NOT skip steps. Each phase builds on the previous one.

---

## 📊 CURRENT PROGRESS STATUS

| Phase | Status | Description |
|-------|---------|-------------|
| **Phase 0** | ✅ **COMPLETED** | Environment setup - Laravel + packages + configs + tests |
| **Phase 1** | ✅ **COMPLETED** | Framework Kernel - 54 files + Laravel integration complete |
| **Phase 2** | ✅ **COMPLETED** | Core + Auth modules - All models, resources, migrations done |
| **Phase 3** | ✅ **COMPLETED** | Patients + Treatments modules - CRM & treatment catalog |
| **Phase 4** | 🔄 **NEXT UP** | Booking + Equipment modules - Appointments & Asset tracking |
| **Phase 5-12** | ⏳ **UPCOMING** | Remaining feature modules & deployment |
| **Super Admin** | ✅ **COMPLETED** | Platform admin panel at /platform with core screens |

**Overall Progress:** **~50% Complete (Phases 0-3 + Super Admin complete)**

### 🎯 ACTUAL IMPLEMENTATION STATUS

**✅ Phase 0 - Environment Setup (COMPLETED):**
- ✅ Laravel 11 project created with proper composer.json
- ✅ All required packages installed (Filament, Spatie, multi-tenancy, etc.)
- ✅ Docker-compose.yml exists for development
- ✅ tenancy.php config file with schema-per-tenant setup
- ✅ xlinic.php framework config with Egyptian market defaults
- ✅ tests/ directory structure with Pest setup and XLinic helpers
- ✅ Enhanced .env.example with multi-tenancy and framework settings

**✅ Phase 1 - Framework Kernel (COMPLETED):**
- ✅ All 53 framework files created with correct namespaces and full implementation
- ✅ Core classes implemented (BaseModel, traits, registries, managers)
- ✅ Module system classes (ModuleManager, ModuleRegistry, DependencyResolver)
- ✅ Security system (RecordPolicy, PermissionRegistry, RecordPolicyEngine)
- ✅ FrameworkServiceProvider created and registered in config/app.php
- ✅ Framework Blade components (status-bar, activity-log, quota-widget)
- ✅ Complete Laravel integration with service provider bootstrap
- ✅ Middleware registration in bootstrap/app.php (tenant, quota)

**✅ Phase 2 - Core + Auth Modules (COMPLETED):**
- ✅ Module manifests (module.json) with comprehensive configurations
- ✅ Core module models (Tenant, TenantSubscription, TenantUsage, Branch, Sequence, Room, TenantModule, Setting)
- ✅ Auth module models (User, Role, Permission, AccessPolicy, UserBranchRole)
- ✅ Service providers for both modules
- ✅ **Complete Filament integration:**
  - ✅ TenantResource with full CRUD, tabs, filters, widgets, relation managers
  - ✅ UserResource with comprehensive user management and security features
  - ✅ RoleResource with permission management and bulk operations
  - ✅ SystemSettingsResource with multi-tab settings management
  - ✅ ModuleManagementResource with module enable/disable/refresh
  - ✅ ProfileResource for user profile management
  - ✅ TwoFactorSetupResource with QR code generation and backup codes
  - ✅ RoomResource with translatable fields and branch assignment
  - ✅ AccessPolicyResource with domain filter builder (Odoo ir.rule style)
- ✅ Database seeders for roles, permissions, demo tenants, and users
- ✅ Complete route files (web.php, api.php) for both modules with API endpoints
- ✅ **All Core migrations:**
  - ✅ `create_tenants_table.php`
  - ✅ `create_tenant_subscriptions_table.php`
  - ✅ `create_tenant_usage_table.php`
  - ✅ `create_branches_table.php`
  - ✅ `create_sequences_table.php`
  - ✅ `create_rooms_table.php`
  - ✅ `create_tenant_modules_table.php`
  - ✅ `create_settings_table.php`
- ✅ **All Auth migrations:**
  - ✅ `create_users_table.php`
  - ✅ `create_user_sessions_table.php`
  - ✅ `create_login_history_table.php`
  - ✅ `create_password_history_table.php`
  - ✅ `create_user_profiles_table.php`
  - ✅ `create_access_policies_table.php`
  - ✅ `create_user_branch_roles_table.php`
  - ✅ Spatie permission migrations (installed via package)
- ✅ Activity log via Spatie activitylog
- ✅ Audits table for detailed record changes

### 🔧 CURRENT ARCHITECTURE STATUS
- ✅ **Multi-tenancy:** Fully integrated - middleware registered, config complete
- ✅ **Security:** Fully integrated - classes implemented and Laravel integration complete
- ✅ **Modularity:** Complete - FrameworkServiceProvider registered and bootstrapping all services
- ✅ **Localization:** Arabic/English support ready in models
- ✅ **Currency:** Egyptian market compliance built into models
- ✅ **Framework:** Complete Laravel + Filament integration with BaseResource inheritance
- ✅ **Database Schema:** All module tables created and migrations run successfully

### 🚨 CRITICAL MISSING COMPONENTS

**✅ Phase 0 COMPLETED - All requirements met**

**✅ Phase 1 COMPLETED - All requirements met (54 files)**

**✅ Phase 2 COMPLETED - All requirements met**

**✅ Phase 3 COMPLETED - All requirements met**

**✅ SUPER ADMIN PANEL COMPLETED:**
- ✅ SuperAdminPanelProvider at `/platform` path
- ✅ **Platform Models (app/Models/):**
  - ✅ SubscriptionPlan - Plan tiers with pricing, limits, and module inclusions
  - ✅ Module - Module registry with tiers, categories, and pricing
  - ✅ PlatformInvoice - Invoices for tenant billing
  - ✅ TenantAddonSubscription - Add-on module subscriptions
  - ✅ TenantActivityLog - Audit trail per tenant
  - ✅ SupportTicket + SupportTicketReply - Support system
  - ✅ PromoCode - Discount and promo code management
  - ✅ Announcement - Platform announcements to tenants
- ✅ **Platform Migrations (8 new tables):**
  - ✅ subscription_plans, modules, platform_invoices
  - ✅ tenant_addon_subscriptions, tenant_activity_logs
  - ✅ support_tickets, support_ticket_replies, promo_codes, announcements
  - ✅ Added subscription_plan_id to tenants table
- ✅ **Filament Resources:**
  - ✅ TenantResource - Full CRUD with tabbed detail view, status badges, filters
  - ✅ SubscriptionPlanResource - Plan management with module selection
  - ✅ ModuleResource - Module registry with tiers and adoption tracking
  - ✅ SupportTicketResource - Ticket management with resolution workflow
- ✅ **Dashboard Widgets:**
  - ✅ PlatformStatsWidget - MRR, active clinics, churn rate, ARR
  - ✅ TenantsOverviewWidget - Stats above tenant list
  - ✅ NeedsAttentionWidget - Alerts for overdue, expiring trials, tickets
  - ✅ RecentSignupsWidget - Latest tenant signups
- ✅ **Relation Managers:**
  - ✅ InvoicesRelationManager - Invoice history per tenant
  - ✅ ActivityLogRelationManager - Activity feed per tenant
  - ✅ SupportTicketsRelationManager - Tickets per tenant
- ✅ **Seeders:**
  - ✅ SubscriptionPlanSeeder - Starter/Professional/Enterprise plans
  - ✅ ModuleSeeder - 24 modules across 6 categories

**✅ Phase 3 COMPLETED - Patients & Treatments:**
- ✅ `modules/Patients/` - Complete patient CRM with models, migrations, Filament resources
- ✅ `modules/Treatments/` - Treatment catalog with categories, pricing, consent templates
- ✅ Patient models: Patient, PatientMedicalHistory, PatientConsentForm, PatientPhoto, PatientNote
- ✅ Treatment models: TreatmentCategory, Treatment, TreatmentBranchPricing, ConsentTemplate
- ✅ Filament resources: PatientResource, TreatmentResource, TreatmentCategoryResource, ConsentTemplateResource
- ✅ Relation managers: Notes, Photos, ConsentForms, BranchPricing
- ✅ Database migrations for all tables
- ✅ Seeders with demo data (5 patients, 7 treatments, 5 categories, 2 consent templates)
- ✅ Arabic/English translations for all modules

### 🎯 **IMMEDIATE NEXT STEPS**

**Phase 4 Implementation:**
1. Create `modules/Equipment/` with equipment tracking and maintenance
2. Create `modules/Booking/` with appointment scheduling
3. Implement availability engine for practitioner/room/equipment scheduling
4. Create calendar views and daily agenda pages
5. Integrate appointment workflow with patient and treatment systems

---

## PROJECT IDENTITY

| Key | Value |
|-----|-------|
| **Project Name** | XLinic |
| **Description** | Odoo-inspired modular SaaS framework for laser & beauty clinics |
| **Target Market** | Egypt / MENA region (Arabic + English) |
| **Architecture** | Modular framework with plugin-style modules |
| **Multi-tenancy** | Schema-per-tenant (PostgreSQL) |

---

## TECHNOLOGY STACK (EXACT VERSIONS)

### Backend
| Technology | Version | Purpose |
|-----------|---------|---------|
| PHP | 8.3+ | Runtime |
| Laravel | 11.x | Framework |
| Filament | 3.2+ | Admin panel builder |
| Livewire | 3.x | Reactive UI (comes with Filament) |
| PostgreSQL | 16+ | Database (schema-per-tenant) |
| Redis | 7+ | Cache, sessions, queues, real-time |
| Laravel Horizon | latest | Queue monitoring dashboard |
| Laravel Reverb | latest | WebSocket server (real-time) |
| Meilisearch | 1.6+ | Full-text search (Arabic + English) |

### Multi-Tenancy
| Technology | Version | Purpose |
|-----------|---------|---------|
| stancl/tenancy | 3.x | Multi-tenant engine (schema separation) |

### Filament Ecosystem
| Package | Purpose |
|---------|---------|
| filament/filament | Core admin panel |
| filament/spatie-laravel-media-library-plugin | File/image management |
| bezhansalleh/filament-shield | Role & permission manager UI |
| filament/spatie-laravel-settings-plugin | Settings management |
| filament/spatie-laravel-translatable-plugin | Translatable resources |
| awcodes/filament-table-repeater | Table repeater for invoice lines |
| coolsam/filament-modules | Module integration helper |

### Laravel Packages
| Package | Purpose |
|---------|---------|
| spatie/laravel-permission | Roles & permissions |
| spatie/laravel-activitylog | Audit trail / activity log (chatter) |
| spatie/laravel-medialibrary | File management with conversions |
| spatie/laravel-settings | Strongly-typed settings |
| spatie/laravel-translatable | JSON translatable fields |
| spatie/laravel-backup | Database & file backups |
| spatie/laravel-query-builder | API query filtering |
| maatwebsite/excel | Excel import/export |
| barryvdh/laravel-dompdf | PDF generation |
| nwidart/laravel-modules | Module scaffolding structure |
| brick/money | Money/currency handling (no floats) |
| staudenmeir/eloquent-has-many-deep | Deep relationship queries |
| spatie/laravel-data | DTOs and data objects |
| laravel/sanctum | API authentication |
| propaganistas/laravel-phone | Phone number validation |
| mcamara/laravel-localization | URL-based locale switching |

### External Services (Integration Later)
| Service | Purpose |
|---------|---------|
| Paymob | Payment gateway (Egypt) |
| WhatsApp Business Cloud API | WhatsApp messaging |
| Twilio / Vodafone EG | SMS messaging |
| AWS S3 / Cloudflare R2 | File storage |
| Mailgun / Resend | Transactional email |
| Meilisearch Cloud | Hosted search (production) |

### Frontend (Minimal — Filament Handles Most)
| Technology | Purpose |
|-----------|---------|
| Alpine.js 3 | Comes with Filament/Livewire |
| Tailwind CSS 3 | Comes with Filament |
| Vite | Asset bundling |
| Chart.js | Dashboard charts |
| Flatpickr | Date/time pickers |

### DevOps & Tooling
| Tool | Purpose |
|------|---------|
| Docker + Docker Compose | Local development |
| Laravel Pint | Code formatting (PSR-12) |
| PHPStan / Larastan | Static analysis (level 6+) |
| Pest | Testing framework |
| Laravel Pail | Real-time log viewer |
| GitHub Actions | CI/CD |

---

## 📁 CURRENT DIRECTORY STRUCTURE (Updated Phase 0 Complete)

```
x_linic/  # Our project (corresponds to xlinic in plan)
├── framework/                          # ✅ KERNEL — Core framework code
│   └── Core/
│       ├── Module/                         # ✅ All files created
│       │   ├── ModuleManifest.php          # ✅ Implemented
│       │   ├── ModuleManager.php
│       │   ├── ModuleRegistry.php
│       │   ├── DependencyResolver.php
│       │   └── ModuleServiceProvider.php
│       ├── Model/
│       │   ├── BaseModel.php
│       │   ├── ModelRegistry.php
│       │   ├── ModelExtension.php
│       │   └── Traits/
│       │       ├── HasTenancy.php
│       │       ├── HasActivity.php
│       │       ├── HasPortalAccess.php
│       │       ├── HasSequence.php
│       │       ├── HasStateMachine.php
│       │       ├── HasAudit.php
│       │       ├── HasTranslation.php
│       │       ├── HasTags.php
│       │       └── HasAttachments.php
│       ├── View/
│       │   ├── ViewExtensionManager.php
│       │   ├── FormExtension.php
│       │   ├── TableExtension.php
│       │   └── WidgetExtension.php
│       ├── Navigation/
│       │   ├── NavigationRegistry.php
│       │   ├── NavigationGroup.php
│       │   └── NavigationItem.php
│       ├── Action/
│       │   ├── ActionRegistry.php
│       │   ├── ServerAction.php
│       │   └── ScheduledAction.php
│       ├── Security/
│       │   ├── PermissionRegistry.php
│       │   ├── RecordPolicy.php
│       │   ├── RecordPolicyEngine.php
│       │   └── FieldAccess.php
│       ├── Settings/
│       │   ├── SettingsRegistry.php
│       │   └── SettingDefinition.php
│       ├── Sequence/
│       │   ├── SequenceService.php
│       │   └── SequenceDefinition.php
│       ├── Report/
│       │   ├── ReportRegistry.php
│       │   ├── BaseReport.php
│       │   └── ReportEngine.php
│       ├── Quota/
│       │   ├── QuotaService.php
│       │   └── QuotaMiddleware.php
│       ├── Tenancy/
│       │   ├── TenantManager.php
│       │   ├── TenantMiddleware.php
│       │   └── TenantAwareJob.php
│       ├── Event/
│       │   ├── ModelEvent.php
│       │   └── ModuleEvent.php
│       └── Filament/
│           ├── BaseResource.php
│           ├── BasePage.php
│           ├── BaseWidget.php
│           ├── BaseRelationManager.php
│           └── Panels/
│               ├── AdminPanel.php
│               ├── SuperAdminPanel.php
│               └── PortalPanel.php
│
├── modules/                            # ALL FEATURE MODULES
│   ├── Core/                           # Module: Core (always active)
│   │   ├── CoreManifest.php
│   │   ├── CoreServiceProvider.php
│   │   ├── Config/
│   │   ├── Models/
│   │   │   ├── Branch.php
│   │   │   ├── Setting.php
│   │   │   └── Sequence.php
│   │   ├── Filament/
│   │   ├── Database/
│   │   │   ├── Migrations/
│   │   │   └── Seeders/
│   │   ├── Routes/
│   │   └── Lang/
│   │       ├── en/
│   │       └── ar/
│   │
│   ├── Auth/                           # Module: Auth & RBAC
│   │   ├── AuthManifest.php
│   │   ├── AuthServiceProvider.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   └── AccessPolicy.php
│   │   ├── Filament/
│   │   │   └── Resources/
│   │   │       ├── UserResource.php
│   │   │       └── RoleResource.php
│   │   └── ...
│   │
│   ├── Patients/                       # Module: Patient CRM
│   ├── Treatments/                     # Module: Treatment Catalog
│   ├── Booking/                        # Module: Appointments & Scheduling
│   ├── Equipment/                      # Module: Equipment & Assets
│   ├── Billing/                        # Module: Invoicing & Payments
│   ├── Accounting/                     # Module: Double-Entry Accounting
│   ├── Packages/                       # Module: Session Bundles
│   ├── GiftCards/                      # Module: Gift Cards & Vouchers
│   ├── Memberships/                    # Module: Memberships
│   ├── Inventory/                      # Module: Consumables & Stock
│   ├── Staff/                          # Module: Staff Profiles
│   ├── Payroll/                        # Module: Payroll & Commissions
│   ├── Loyalty/                        # Module: Points & Rewards
│   ├── MarketingWhatsApp/             # Module: WhatsApp Integration
│   ├── MarketingSms/                  # Module: SMS Campaigns
│   ├── MarketingEmail/                # Module: Email Marketing
│   ├── MarketingSocial/               # Module: Social Media
│   ├── Reporting/                      # Module: Reports & Analytics
│   ├── PatientPortal/                 # Module: Patient-Facing Portal
│   └── Api/                            # Module: REST API
│
├── app/                                # Standard Laravel (thin layer)
│   ├── Providers/
│   │   ├── AppServiceProvider.php
│   │   └── FrameworkServiceProvider.php
│   ├── Http/
│   │   ├── Kernel.php
│   │   └── Middleware/
│   └── Exceptions/
│       └── Handler.php
│
├── config/
│   ├── xlinic.php                   # Framework config
│   ├── tenancy.php                     # Multi-tenant config
│   └── ...
│
├── database/
│   └── migrations/                     # Platform-level migrations only
│       └── tenant/                     # Tenant-level migrations
│
├── resources/
│   ├── views/
│   │   ├── framework/                  # Framework Blade components
│   │   │   ├── components/
│   │   │   │   ├── status-bar.blade.php
│   │   │   │   ├── activity-log.blade.php
│   │   │   │   └── quota-widget.blade.php
│   │   │   └── layouts/
│   │   └── vendor/
│   ├── css/
│   └── js/
│
├── routes/
│   ├── web.php                         # Minimal (modules handle their own)
│   ├── api.php
│   └── tenant.php                      # Tenant-scoped routes
│
├── tests/
│   ├── Unit/
│   │   ├── Framework/
│   │   └── Modules/
│   ├── Feature/
│   └── Pest.php
│
├── docker/
│   ├── Dockerfile
│   ├── nginx/
│   └── postgres/
│
├── docker-compose.yml
├── composer.json
├── package.json
├── vite.config.js
├── phpstan.neon
├── pint.json
├── phpunit.xml
└── .env.example
```

---

## PHASE 0: ENVIRONMENT SETUP
### Goal: Working development environment with all services running

#### Step 0.1: Create Laravel Project
```bash
composer create-project laravel/laravel xlinic "11.*"
cd xlinic
```

#### Step 0.2: Create docker-compose.yml
Create `docker-compose.yml` with these services:
- **app**: PHP 8.3-FPM with required extensions (pdo_pgsql, redis, gd, intl, zip, bcmath)
- **nginx**: Nginx reverse proxy, port 80
- **postgres**: PostgreSQL 16, port 5432, database: `xlinic`
- **redis**: Redis 7, port 6379
- **meilisearch**: Meilisearch 1.6, port 7700
- **mailhog**: MailHog for local email testing, port 1025/8025
- **horizon**: Laravel Horizon worker (same image as app)

Volumes:
- `./:/var/www/html` for app
- `postgres_data` for database persistence
- `redis_data` for Redis persistence
- `meilisearch_data` for search index

#### Step 0.3: Configure .env
```env
APP_NAME=XLinic
APP_URL=http://xlinic.test
APP_LOCALE=en
APP_FALLBACK_LOCALE=ar
APP_FAKER_LOCALE=ar_EG

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=xlinic
DB_USERNAME=xlinic
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025

FILESYSTEM_DISK=s3
# For local dev, use minio or local disk
```

#### Step 0.4: Install ALL Composer Packages
```bash
# Core
composer require filamentphp/filament:"^3.2"
composer require stancl/tenancy:"^3.0"
composer require nwidart/laravel-modules:"^11.0"

# Spatie ecosystem
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-medialibrary
composer require spatie/laravel-settings
composer require spatie/laravel-translatable
composer require spatie/laravel-backup
composer require spatie/laravel-query-builder
composer require spatie/laravel-data

# Filament plugins
composer require bezhansalleh/filament-shield:"^3.0"
composer require filament/spatie-laravel-media-library-plugin:"^3.0"
composer require filament/spatie-laravel-settings-plugin:"^3.0"
composer require filament/spatie-laravel-translatable-plugin:"^3.0"
composer require awcodes/filament-table-repeater:"^3.0"

# Utilities
composer require brick/money
composer require staudenmeir/eloquent-has-many-deep
composer require laravel/sanctum
composer require laravel/horizon
composer require laravel/reverb
composer require propaganistas/laravel-phone
composer require mcamara/laravel-localization
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
composer require laravel/scout
composer require meilisearch/meilisearch-php

# Dev dependencies
composer require --dev pestphp/pest
composer require --dev pestphp/pest-plugin-laravel
composer require --dev larastan/larastan
composer require --dev laravel/pint
composer require --dev laravel/pail
```

#### Step 0.5: Install NPM Packages
```bash
npm install
npm install -D tailwindcss postcss autoprefixer
npm install chart.js flatpickr
```

#### Step 0.6: Publish Configs
```bash
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider"
php artisan vendor:publish --tag="filament-config"
php artisan horizon:install
php artisan reverb:install
```

#### Step 0.7: Verify Everything Boots
```bash
php artisan serve
# OR
docker-compose up -d
```
Visit http://localhost — should see Laravel welcome page.

**CHECKPOINT**: Laravel boots, PostgreSQL connected, Redis connected, Horizon dashboard accessible.

---

## ✅ PHASE 1: FRAMEWORK KERNEL — COMPLETED
### Goal: Build the core framework that all modules will use
### Duration: This is the most critical phase — everything depends on it
### Status: **COMPLETED** - All 54 framework files implemented

#### Step 1.1: Create Framework Directory Structure
Create the entire `framework/Core/` directory tree as shown in the directory structure above. Every file should be created as an empty class with the correct namespace first, then implemented.

#### Step 1.2: Multi-Tenancy Foundation

**File: `config/tenancy.php`** — Configure stancl/tenancy:
- Tenant model: `App\Models\Tenant`
- Schema-based tenancy (not database-based)
- Central domains: `xlinic.test` (platform admin)
- Tenant identification: subdomain (`{tenant}.xlinic.test`)
- Tenant routes file: `routes/tenant.php`
- Universal routes: login, registration, webhooks
- Tenant migrations path: `database/migrations/tenant`

**File: `app/Models/Tenant.php`**:
- Extends `Stancl\Tenancy\Database\Models\Tenant`
- Properties: id, name, slug, schema_name, owner_user_id, subscription_plan_id, subscription_status, trial_ends_at, settings (jsonb), country_code, timezone, currency_code, is_active
- Relationships: plan(), owner(), addons(), usage()

**File: `framework/Core/Tenancy/TenantManager.php`**:
- `current()`: Get current tenant
- `runForTenant($tenant, $callback)`: Execute code in tenant context
- `getAllTenants()`: List all tenants (platform admin)

**File: `framework/Core/Tenancy/TenantMiddleware.php`**:
- Resolve tenant from subdomain
- Set PostgreSQL search_path to tenant schema
- Set cache prefix, storage path, queue context

**File: `framework/Core/Tenancy/TenantAwareJob.php`**:
- Trait for queued jobs to maintain tenant context
- Serialize/deserialize tenant ID with job payload

**Platform-level migrations** (in `database/migrations/`):
```
create_tenants_table
create_subscription_plans_table
create_plan_modules_table
create_modules_table
create_module_dependencies_table
create_platform_users_table
create_tenant_usage_table
create_tenant_usage_history_table
create_tenant_storage_breakdown_table
create_tenant_addon_subscriptions_table
```

#### Step 1.3: Module System

**File: `framework/Core/Module/ModuleManifest.php`** — Abstract base class:
```
Properties (all declared by child classes):
  - $code: string (unique identifier)
  - $name: array (translatable)
  - $description: array (translatable)
  - $category: string
  - $icon: string
  - $version: string
  - $dependencies: array (required modules)
  - $optionalDependencies: array
  - $models: array
  - $extensions: array ['model' => [], 'form' => [], 'table' => [], 'dashboard' => []]
  - $permissions: array
  - $defaultRolePermissions: array
  - $recordPolicies: array
  - $navigation: array
  - $settings: array
  - $sequences: array
  - $scheduledActions: array
  - $events: array
  - $listeners: array

Methods:
  - getServiceProviderClass(): string
  - getModulePath(): string
  - isCore(): bool
```

**File: `framework/Core/Module/ModuleManager.php`**:
- `discoverModules(string $path)`: Scan modules/ directory for *Manifest.php files
- `boot()`: Full boot sequence (discover → resolve order → load active → boot each)
- `bootModule(ModuleManifest $manifest)`: Register everything from manifest
- `activateModule(string $code)`: Activate for current tenant (with dependency check)
- `deactivateModule(string $code)`: Deactivate (with dependant check)
- `canActivate(string $code)`: Check plan allows + dependencies met
- `canDeactivate(string $code)`: Check no active dependants

**File: `framework/Core/Module/ModuleRegistry.php`**:
- `register(ModuleManifest $manifest)`: Add to runtime registry
- `isActive(string $code)`: Check if module active for current tenant
- `isAllowed(string $code)`: Check if plan allows module
- `getActive()`: All active module codes
- `getAll()`: All registered modules
- `get(string $code)`: Get specific manifest
- Results cached per-tenant in Redis

**File: `framework/Core/Module/DependencyResolver.php`**:
- Implements topological sort (Kahn's algorithm)
- Input: array of ModuleManifest
- Output: ordered array (dependencies before dependants)
- Throws CircularDependencyException if cycle detected

**File: `framework/Core/Module/ModuleServiceProvider.php`**:
- Laravel service provider that triggers ModuleManager::boot()
- Registered in config/app.php providers array

#### Step 1.4: Base Model

**File: `framework/Core/Model/BaseModel.php`**:
- Extends `Illuminate\Database\Eloquent\Model`
- Uses traits: HasTenancy, HasAudit
- UUID primary keys (ordered UUID v7 via `Str::orderedUuid()`)
- `protected $keyType = 'string'` and `public $incrementing = false`
- Auto-generates UUID in `creating` event
- `booted()`: calls `ModelRegistry::applyExtensions()`
- Helper: `setting(string $key)` — reads from SettingsRegistry
- Helper: `nextSequence(string $code)` — generates next number
- Helper: `getDisplayName()` — returns human-readable identifier

**File: `framework/Core/Model/ModelRegistry.php`**:
- `registerExtension(string $targetModel, string $extensionClass)`: Store extension
- `applyExtensions(BaseModel $model)`: Apply all registered extensions
  - Add relationships via `Model::resolveRelationUsing()`
  - Add scopes via `Model::macro()`
  - Add accessors via `Model::macro()`

**File: `framework/Core/Model/ModelExtension.php`** — Abstract base:
- `$target`: string (target model class)
- `relationships()`: array of closures
- `scopes()`: array of closures
- `attributes()`: array of closures

#### Step 1.5: Model Traits

**File: `framework/Core/Model/Traits/HasTenancy.php`**:
- Adds global scope that filters by current tenant's schema
- Works with stancl/tenancy's schema switching

**File: `framework/Core/Model/Traits/HasActivity.php`**:
- `activities()`: MorphMany to Activity model
- `logActivity(string $message)`: Create activity note
- `logChange(string $field, $old, $new)`: Log field change
- `scheduleFollowUp(string $type, string $summary, Carbon $dueDate, ?User $assignedTo)`: Create planned activity
- Uses spatie/laravel-activitylog under the hood

**File: `framework/Core/Model/Traits/HasStateMachine.php`**:
- `abstract stateMachine()`: Returns state/transition definition array
- `transitionTo(string $transition)`: Execute transition with validation
- `availableTransitions()`: Get valid transitions from current state
- `canTransitionTo(string $transition)`: Check if transition valid
- Calls before/after hooks defined in stateMachine()
- Fires StateTransitioned event
- Auto-logs activity if HasActivity is also used
- `getStatusBarFormComponent()`: Returns Filament component for status bar UI

**File: `framework/Core/Model/Traits/HasSequence.php`**:
- Boot method: auto-generates sequence on creating event
- `$sequenceCode`: string property (set in model)
- `$sequenceField`: string property (default: 'code')

**File: `framework/Core/Model/Traits/HasAudit.php`**:
- Auto-logs create/update/delete to audit_logs table
- Records: user_id, action, model, model_id, old_values, new_values, ip_address
- Configurable: `$auditExclude` array to skip sensitive fields

**File: `framework/Core/Model/Traits/HasTranslation.php`**:
- Uses spatie/laravel-translatable
- JSON columns: `{"en": "...", "ar": "..."}`
- `getTranslation(string $field, ?string $locale = null)`
- Integrates with Filament's translatable plugin

**File: `framework/Core/Model/Traits/HasTags.php`**:
- `tags` jsonb column
- `addTag(string $tag)`, `removeTag(string $tag)`, `hasTag(string $tag)`
- Scope: `scopeWithTag(string $tag)`, `scopeWithAnyTags(array $tags)`

**File: `framework/Core/Model/Traits/HasAttachments.php`**:
- Uses spatie/laravel-medialibrary
- `registerMediaCollections()`: Define collections (documents, photos)
- Helper methods for upload/download/delete

#### Step 1.6: View Extension System

**File: `framework/Core/View/ViewExtensionManager.php`**:
- `registerFromManifest(ModuleManifest $manifest)`: Parse extensions from manifest
- `getFormExtensions(string $resourceClass)`: Get all form extensions for a resource
- `getTableExtensions(string $resourceClass)`: Get all table extensions
- `getDashboardExtensions()`: Get all dashboard widget extensions
- Filters by: module active + user permission
- Sorts by priority

**File: `framework/Core/View/FormExtension.php`** — Abstract base:
- `$target`: string (target Filament Resource class)
- `$position`: string ('tabs', 'sidebar', 'after_main', 'before_main')
- `$priority`: int (lower = first)
- `$permission`: ?string
- `abstract getFormComponents()`: Returns Filament form component array
- `getModuleCode()`: Returns owning module code

**File: `framework/Core/View/TableExtension.php`** — Abstract base:
- `$target`: string
- `$priority`: int
- `getColumns()`: Returns Filament table columns
- `getFilters()`: Returns Filament table filters
- `getBulkActions()`: Returns bulk actions
- `getHeaderActions()`: Returns header actions

**File: `framework/Core/View/WidgetExtension.php`** — Abstract base:
- `$priority`: int
- `$permission`: ?string
- `getWidget()`: Returns Filament widget class

#### Step 1.7: Base Filament Classes

**File: `framework/Core/Filament/BaseResource.php`**:
- Extends `Filament\Resources\Resource`
- `static ?string $moduleCode = null`: Module this belongs to
- `canAccess()`: Checks ModuleRegistry::isActive() + parent::canAccess()
- `form()`: Calls `getBaseFormSchema()` then merges FormExtensions from ViewExtensionManager
- `table()`: Calls `getBaseTable()` then merges TableExtensions
- `abstract getBaseFormSchema()`: Each resource defines its own form
- `mergeFormExtensions()`: Injects tab/section extensions into form
- Automatic Activity log tab at bottom if model uses HasActivity

**File: `framework/Core/Filament/BasePage.php`**:
- `canAccess()`: Module check
- Automatic breadcrumb from NavigationRegistry

**File: `framework/Core/Filament/BaseWidget.php`**:
- `canView()`: Module + permission check
- `getModuleCode()`: Which module this belongs to

**File: `framework/Core/Filament/BaseRelationManager.php`**:
- Module + permission check
- Automatic table extensions support

#### Step 1.8: Navigation Registry

**File: `framework/Core/Navigation/NavigationRegistry.php`**:
- Predefined groups: dashboard, crm, operations, sales, financial, inventory, marketing, hr, reports, settings
- Each group: label (translatable), icon, sort order
- `registerFromManifest(ModuleManifest $manifest)`: Add navigation items
- `build()`: Build sidebar for current user (filter by active module + permission + branch)
- Result cached per user session, busted on module activate/deactivate

#### Step 1.9: Settings Registry

**File: `framework/Core/Settings/SettingsRegistry.php`**:
- `registerFromManifest(ModuleManifest $manifest)`: Store setting definitions
- `get(string $key, mixed $default = null)`: Get value (with Redis cache)
- `set(string $key, mixed $value)`: Set value (bust cache)
- `buildSettingsSchema()`: Build Filament form for unified settings page
- Groups settings by category from all active modules

**File: `framework/Core/Settings/SettingDefinition.php`**:
- Value object: key, type, label, default, validation, group, description, module

#### Step 1.10: Security Layer

**File: `framework/Core/Security/PermissionRegistry.php`**:
- `registerFromManifest(ModuleManifest $manifest)`: Store permissions
- `seedForModule(string $moduleCode)`: Insert permissions into DB on activation
- `getForModule(string $moduleCode)`: All permissions for a module
- `getAll()`: All permissions from all active modules

**File: `framework/Core/Security/RecordPolicy.php`**:
- Odoo ir.rule equivalent
- Properties: model, name, domain (jsonb filter), role_id, perm_read/write/create/delete
- `evaluate(User $user, BaseModel $model)`: Apply domain filter

**File: `framework/Core/Security/RecordPolicyEngine.php`**:
- `apply(Builder $query, string $model, string $permission)`: Add record policy scopes to query
- Called automatically from BaseModel's global scope
- Resolves `{user.branch_id}`, `{user.id}`, etc. in domain filters

#### Step 1.11: Sequence Service

**File: `framework/Core/Sequence/SequenceService.php`**:
- `next(string $code)`: Generate next number (with DB locking)
- Pattern: `{prefix}{YYYY}-{######}` → `INV-2024-000042`
- Handles year rollover (reset or continue based on config)
- Thread-safe with `SELECT FOR UPDATE`

**File: `framework/Core/Sequence/SequenceDefinition.php`**:
- Value object: code, prefix, padding, pattern, reset_on_year

#### Step 1.12: Quota Service

**File: `framework/Core/Quota/QuotaService.php`**:
- `canCreate(string $resource)`: Hard limit check → QuotaCheck VO
- `canConsume(string $resource)`: Soft limit check → SoftQuotaCheck VO
- `hasFeature(string $feature)`: Feature flag check
- `increment(string $resource, int $amount = 1)`: Bump counter
- `decrement(string $resource, int $amount = 1)`: Reduce counter
- `incrementStorage(float $mb, string $category)`: Track storage
- `getDashboard()`: All quotas with usage for UI
- `isApproachingLimit(string $resource, int $threshold = 80)`: Warning check
- `recalculateStorage()`: Async full recalculation
- `recordOverage(string $resource, int $unitCost)`: Log overage charge

**File: `framework/Core/Quota/QuotaMiddleware.php`**:
- Check concurrent session limit
- Add X-Quota-* response headers for API calls

#### Step 1.13: Report Engine

**File: `framework/Core/Report/ReportRegistry.php`**:
- `register(BaseReport $report)`: Register from module
- `getAll()`: All reports for current user (module + permission filtered)
- `getByModule(string $moduleCode)`: Reports for specific module

**File: `framework/Core/Report/BaseReport.php`** — Abstract base:
- Properties: $code, $name, $module, $model
- `abstract filters()`: Array of filter definitions
- `abstract query(array $filters)`: Eloquent query builder
- `abstract columns()`: Column definitions
- `pdfView()`: Blade template path for PDF
- `groupBy()`: Available grouping options
- `charts()`: Chart definitions

**File: `framework/Core/Report/ReportEngine.php`**:
- `generate(string $reportCode, array $filters, string $format)`: Run report
- Formats: `screen` (Filament table), `pdf` (DomPDF), `excel` (Maatwebsite)

#### Step 1.14: Framework Blade Components

Create these Blade views in `resources/views/framework/components/`:

- **status-bar.blade.php**: Odoo-style status indicator showing states as dots/pills with active state highlighted, forward/back arrows, available transition buttons
- **activity-log.blade.php**: Chatter component showing timeline of activities, with "Log Note" and "Schedule Activity" buttons at top
- **quota-widget.blade.php**: Progress bars showing resource usage vs limits

#### Step 1.15: Framework Service Provider

**File: `app/Providers/FrameworkServiceProvider.php`**:
- Register as singleton: ModuleManager, ModuleRegistry, ModelRegistry, ViewExtensionManager, NavigationRegistry, SettingsRegistry, PermissionRegistry, SequenceService, QuotaService, ReportRegistry, RecordPolicyEngine
- Boot: call ModuleManager::boot()
- Register middleware: TenantMiddleware, QuotaMiddleware, CheckModule

**Register in `config/app.php`:**
```php
'providers' => [
    // ... Laravel providers
    App\Providers\FrameworkServiceProvider::class,
],
```

**✅ CHECKPOINT PASSED**: Framework kernel complete. All 54 framework files implemented with:
- ✅ Complete module system with dependency resolution (Kahn's algorithm)
- ✅ Multi-tenant foundation with TenantManager and middleware
- ✅ BaseModel with UUID v7 and comprehensive traits
- ✅ Security system with RBAC and record policies
- ✅ View extension system for Filament integration
- ✅ Navigation, Settings, Sequence, Quota, and Report registries
- ✅ Complete Filament panels (Admin, SuperAdmin, Portal)

---

## ✅ PHASE 2: CORE MODULE + AUTH MODULE — COMPLETED
### Goal: Users can log in, manage branches, roles, permissions
### Status: **COMPLETED** - Core tenant management and Auth system implemented

#### Step 2.1: Core Module

**Files to create in `modules/Core/`**:

Models:
- `Branch.php`: Uses BaseModel, HasTranslation, HasActivity. Fields from schema doc.
- `Room.php`: Uses BaseModel, HasTranslation. Belongs to Branch.
- `TenantModule.php`: Tracks active/inactive modules per tenant.

Filament Resources:
- `BranchResource.php`: CRUD for branches. List with active status. Form with translatable name, address, working hours (JSON editor), Google Maps link, contact info.
- `RoomResource.php`: CRUD for rooms. Nested under branch.

Filament Pages:
- `ModuleManagementPage.php`: Grid of all modules with toggle switches. Shows plan limits. Handles activation/deactivation with dependency checks. Shows upgrade prompts for unavailable modules.
- `GeneralSettingsPage.php`: Unified settings page built from SettingsRegistry.
- `UsageDashboardPage.php`: Quota usage dashboard with progress bars.

Seeders:
- `CoreSequenceSeeder.php`: Seed sequence definitions for all modules.
- `CoreSettingsSeeder.php`: Seed default settings.
- `PlanSeeder.php`: Seed Starter, Professional, Enterprise plans with limits and module mappings.
- `ModuleSeeder.php`: Seed all module records into public.modules table.

Migrations (tenant-level):
- `create_branches_table.php`
- `create_rooms_table.php`
- `create_tenant_modules_table.php`
- `create_settings_table.php`
- `create_sequences_table.php`
- `create_activities_table.php` (for HasActivity trait)
- `create_audit_logs_table.php` (for HasAudit trait)

#### Step 2.2: Auth Module

Models:
- `User.php`: Uses BaseModel, HasActivity. Extends `Authenticatable`. Fields from schema. Uses spatie/laravel-permission for roles.
- `AccessPolicy.php`: Record-level access rules (Odoo ir.rule). Fields: model, name, domain_filter (jsonb), role_id, perm_read/write/create/delete.

Filament Resources:
- `UserResource.php`: CRUD for staff users. Form: name, email, phone, avatar, branch assignment, role assignment (per branch!), 2FA toggle, active status. Table: name, email, roles, branch, last login, status.
- `RoleResource.php`: CRUD for roles. Form: translatable name, permission checkboxes grouped by module. Show which modules each permission belongs to. System roles (owner, manager, etc.) cannot be deleted.
- `AccessPolicyResource.php`: CRUD for record-level rules. Form: model select, domain builder (jsonb), role select, permission toggles.

Filament Pages:
- `MyProfilePage.php`: Current user's profile edit, password change, 2FA setup, locale preference.

Seeders:
- `RoleSeeder.php`: Seed default roles: owner, branch_manager, practitioner, receptionist, technician, accountant.
- `PermissionSeeder.php`: Seed all permissions from all module manifests.
- `DefaultAccessPolicySeeder.php`: Seed branch-scoped policies for branch_manager, receptionist, etc.

Migrations:
- `create_users_table.php`
- `create_roles_table.php` (or use spatie's migration)
- `create_permissions_table.php` (or use spatie's migration)
- `create_access_policies_table.php`
- `create_user_roles_table.php` (with branch_id for branch-scoped roles)

#### Step 2.3: Filament Panel Configuration

**File: `framework/Core/Filament/Panels/AdminPanel.php`**:
- Panel ID: `admin`
- Path: `/admin`
- Auth: standard Filament auth with tenant User model
- Navigation: built from NavigationRegistry
- Plugins: SpatieLaravelTranslatablePlugin, ShieldPlugin
- Colors: customizable per tenant (from settings)
- Dark mode: enabled
- RTL: auto-detect from locale (Arabic = RTL)
- Locales: `['en', 'ar']`
- Global search: enabled (uses Meilisearch)
- Notifications: enabled (database + broadcast)
- Tenant: branch selector in top bar (for multi-branch clinics)

**File: `framework/Core/Filament/Panels/SuperAdminPanel.php`**:
- Panel ID: `super-admin`
- Path: `/platform`
- Separate auth guard for platform admins
- Resources: TenantResource, PlanResource, PlatformUserResource
- Widgets: TotalTenants, MRR, ChurnRate, UsageOverview
- No module system (always full access)

**✅ CHECKPOINT PASSED**: Core and Auth modules fully implemented:

**Core Module:**
- ✅ Complete module manifest with permissions, roles, navigation
- ✅ Tenant model with multi-tenancy support (schema-per-tenant)
- ✅ TenantSubscription model with billing cycle management
- ✅ TenantUsage model with quota tracking
- ✅ TenantService with full CRUD and database schema management
- ✅ Branch model with multi-branch support
- ✅ Room model with translatable name/description and branch assignment
- ✅ TenantModule model for tracking active modules per tenant
- ✅ Setting model for tenant-specific configuration
- ✅ Egyptian market defaults (EGP, 14% VAT, Arabic support)
- ✅ All Core migrations run successfully

**Auth Module:**
- ✅ Complete User model with Laravel authentication contracts
- ✅ Spatie roles & permissions integration
- ✅ Filament user interface support with panel access control
- ✅ Two-factor authentication ready
- ✅ Account lockout and password policies
- ✅ Session management and login history tracking
- ✅ User profiles with extended information
- ✅ 7 predefined roles (admin, manager, staff, receptionist, doctor, nurse, technician)
- ✅ AccessPolicy model for record-level access rules (Odoo ir.rule equivalent)
- ✅ UserBranchRole model for branch-scoped role assignments
- ✅ All Auth migrations run successfully

**Next Phase Ready:** Phase 4: Booking & Equipment modules

---

## ✅ PHASE 3: PATIENT & TREATMENT MODULES — COMPLETED
### Goal: Patient CRM and treatment catalog fully functional
### Status: **COMPLETED** - All models, migrations, resources, and seeders implemented

#### Step 3.1: Patients Module

Models:
- `Patient.php`: Uses BaseModel, HasActivity, HasSequence, HasTags, HasAttachments, HasPortalAccess. All fields from schema. Sequence: `PAT-{YYYY}-{######}`. Searchable (Meilisearch).
- `PatientMedicalHistory.php`: One-to-one with Patient. All medical fields from schema.
- `PatientConsentForm.php`: Belongs to Patient and ConsentTemplate. Signature data (base64), PDF storage.
- `PatientPhoto.php`: Belongs to Patient. Uses media library. Types: before, after, during, consultation. Body area tags.
- `PatientNote.php`: Belongs to Patient. Types: clinical, administrative, follow_up, complaint.

Filament Resources:
- `PatientResource.php`: 
  - **List page**: Table with search, filters (gender, referral source, branch, tags, date range, has upcoming appointments). Quick stats at top (total patients, new this month, returning).
  - **Create/Edit page**: Tabbed form:
    - Tab 1 "Personal": Name, phone, email, DOB, gender, national ID, address, referral source
    - Tab 2 "Medical": Fitzpatrick type selector (visual!), allergies (tag input), medications, conditions checkboxes, contraindication flags
    - Tab 3 "Consent Forms": List of signed consents, sign new button
    - Tab 4 "Photos": Before/after gallery with body area filter
    - Tab 5 "Notes": Timeline of notes
    - (Other modules will add tabs via FormExtension: Gift Cards, Packages, Loyalty, etc.)
  - **Bottom**: Activity log (chatter) showing all interactions
- `PatientNoteResource.php`: Inline relation manager on PatientResource

Seeders:
- `PatientPermissionSeeder.php`
- `PatientSequenceSeeder.php`
- `PatientSettingsSeeder.php`

Migrations:
- `create_patients_table.php`
- `create_patient_medical_histories_table.php`
- `create_patient_consent_forms_table.php`
- `create_patient_photos_table.php`
- `create_patient_notes_table.php`

#### Step 3.2: Treatments Module

Models:
- `TreatmentCategory.php`: Self-referencing tree (parent_id). HasTranslation. Nested set or adjacency list.
- `Treatment.php`: Uses BaseModel, HasTranslation, HasSequence, HasActivity. All fields from schema. Belongs to TreatmentCategory. HasMany: TreatmentBranchPricing.
- `TreatmentBranchPricing.php`: Override price per branch.
- `ConsentTemplate.php`: HasTranslation. Rich text content, version tracking.

Filament Resources:
- `TreatmentCategoryResource.php`: Tree view with drag-and-drop reorder. Translatable name.
- `TreatmentResource.php`:
  - Form: Translatable name/description, category select, duration, buffer time, recommended sessions, interval days, base price (Money field), contraindications (tag input), Fitzpatrick min/max, pre/post care instructions (rich editor, translatable), consent template select.
  - Relation managers: BranchPricingRelationManager (table repeater: branch, price, active).
- `ConsentTemplateResource.php`: Rich text editor with translatable content. Version history.

Migrations:
- `create_treatment_categories_table.php`
- `create_treatments_table.php`
- `create_treatment_branch_pricing_table.php`
- `create_consent_templates_table.php`

**CHECKPOINT**: ✅ PASSED - All Phase 3 requirements implemented.

---

## 📋 PHASE 3 COMPLETION VERIFICATION

### ✅ **Files Successfully Created in Phase 3:**

**Patients Module:**
- ✅ `modules/Patients/module.json` - Module manifest with permissions, navigation
- ✅ `modules/Patients/Providers/PatientsServiceProvider.php` - Module service provider
- ✅ `modules/Patients/Providers/RouteServiceProvider.php` - Route service provider
- ✅ `modules/Patients/Config/config.php` - Module configuration
- ✅ `modules/Patients/Routes/web.php` - Web routes
- ✅ `modules/Patients/Routes/api.php` - API routes
- ✅ `modules/Patients/Lang/en/patients.php` - English translations
- ✅ `modules/Patients/Lang/ar/patients.php` - Arabic translations

**Patients Models:**
- ✅ `modules/Patients/Models/Patient.php` - Main patient model with HasTenancy, HasActivity, HasSequence
- ✅ `modules/Patients/Models/PatientMedicalHistory.php` - One-to-one medical history with Fitzpatrick types
- ✅ `modules/Patients/Models/PatientConsentForm.php` - Signed consent form tracking
- ✅ `modules/Patients/Models/PatientPhoto.php` - Before/after photos with media library
- ✅ `modules/Patients/Models/PatientNote.php` - Clinical and administrative notes

**Patients Migrations:**
- ✅ `modules/Patients/Database/Migrations/2024_01_01_000001_create_patients_table.php`
- ✅ `modules/Patients/Database/Migrations/2024_01_01_000002_create_patient_medical_histories_table.php`
- ✅ `modules/Patients/Database/Migrations/2024_01_01_000003_create_patient_consent_forms_table.php`
- ✅ `modules/Patients/Database/Migrations/2024_01_01_000004_create_patient_photos_table.php`
- ✅ `modules/Patients/Database/Migrations/2024_01_01_000005_create_patient_notes_table.php`

**Patients Filament Resources:**
- ✅ `modules/Patients/Filament/Resources/PatientResource.php` - Full CRUD with tabs
- ✅ `modules/Patients/Filament/Resources/PatientResource/Pages/ListPatients.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/Pages/CreatePatient.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/Pages/ViewPatient.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/Pages/EditPatient.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/RelationManagers/NotesRelationManager.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/RelationManagers/PhotosRelationManager.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/RelationManagers/ConsentFormsRelationManager.php`
- ✅ `modules/Patients/Filament/Resources/PatientResource/Widgets/PatientStatsOverview.php`

**Treatments Module:**
- ✅ `modules/Treatments/module.json` - Module manifest
- ✅ `modules/Treatments/Providers/TreatmentsServiceProvider.php`
- ✅ `modules/Treatments/Providers/RouteServiceProvider.php`
- ✅ `modules/Treatments/Config/config.php`
- ✅ `modules/Treatments/Routes/web.php`
- ✅ `modules/Treatments/Routes/api.php`
- ✅ `modules/Treatments/Lang/en/treatments.php`
- ✅ `modules/Treatments/Lang/ar/treatments.php`

**Treatments Models:**
- ✅ `modules/Treatments/Models/TreatmentCategory.php` - Self-referencing tree structure
- ✅ `modules/Treatments/Models/Treatment.php` - Main treatment model with pricing, Fitzpatrick ranges
- ✅ `modules/Treatments/Models/TreatmentBranchPricing.php` - Branch-specific pricing overrides
- ✅ `modules/Treatments/Models/ConsentTemplate.php` - Consent templates with versioning

**Treatments Migrations:**
- ✅ `modules/Treatments/Database/Migrations/2024_01_01_000006_create_consent_templates_table.php`
- ✅ `modules/Treatments/Database/Migrations/2024_01_01_000007_create_treatment_categories_table.php`
- ✅ `modules/Treatments/Database/Migrations/2024_01_01_000008_create_treatments_table.php`
- ✅ `modules/Treatments/Database/Migrations/2024_01_01_000009_create_treatment_branch_pricing_table.php`

**Treatments Filament Resources:**
- ✅ `modules/Treatments/Filament/Resources/TreatmentCategoryResource.php`
- ✅ `modules/Treatments/Filament/Resources/TreatmentCategoryResource/Pages/*.php`
- ✅ `modules/Treatments/Filament/Resources/TreatmentResource.php`
- ✅ `modules/Treatments/Filament/Resources/TreatmentResource/Pages/*.php`
- ✅ `modules/Treatments/Filament/Resources/TreatmentResource/RelationManagers/BranchPricingRelationManager.php`
- ✅ `modules/Treatments/Filament/Resources/ConsentTemplateResource.php`
- ✅ `modules/Treatments/Filament/Resources/ConsentTemplateResource/Pages/*.php`

**Database Seeders:**
- ✅ `modules/Patients/Database/Seeders/PatientsModuleSeeder.php` - 5 demo patients
- ✅ `modules/Treatments/Database/Seeders/TreatmentsModuleSeeder.php` - 7 treatments, 5 categories, 2 consent templates

**Core Module Updates:**
- ✅ `modules/Core/Models/Branch.php` - Branch model for multi-branch support
- ✅ `modules/Core/Database/Migrations/2024_01_01_000000_create_branches_table.php`
- ✅ `modules/Core/Database/Migrations/2024_01_01_000001_create_sequences_table.php`

**Framework Fixes:**
- ✅ Fixed `framework/Core/Model/Audit.php` - Extends Model directly to avoid infinite recursion
- ✅ Fixed `framework/Core/Model/Traits/HasSequence.php` - Correct method call for sequence generation
- ✅ Fixed `database/migrations/2026_02_19_023928_create_activity_log_table.php` - UUID morphs for activity log

### 🎯 **Phase 3 Checkpoint: PASSED**
- ✅ Patient CRM fully implemented with 5 demo patients
- ✅ Treatment catalog with 5 categories and 7 treatments
- ✅ Consent templates with version tracking
- ✅ Branch-specific pricing support
- ✅ Fitzpatrick skin type tracking for safety
- ✅ Arabic/English translations for all labels
- ✅ All migrations run successfully
- ✅ All seeders populate demo data

**Next Step: Phase 4 - Booking & Equipment Modules**

---

## PHASE 4: BOOKING & EQUIPMENT MODULES
### Goal: Full appointment scheduling with equipment tracking

#### Step 4.1: Equipment Module

Models:
- `EquipmentType.php`: HasTranslation. Manufacturer, model, category (laser, ipl, rf, etc.), specifications (jsonb).
- `Equipment.php`: Uses BaseModel, HasActivity, HasSequence. Belongs to EquipmentType, Branch, Room. Shot counter, maintenance tracking, depreciation. Statuses: active, maintenance, retired, out_of_service.
- `EquipmentMaintenanceLog.php`: Maintenance records with cost, parts replaced, next due date.
- `EquipmentShotLog.php`: Per-appointment shot usage with energy/spot size settings.

Filament Resources:
- `EquipmentTypeResource.php`: Catalog of machine types.
- `EquipmentResource.php`: Full asset management. Status bar (active→maintenance→retired). Shot counter progress bar (current vs max). Maintenance schedule timeline. Depreciation calculations.
- Relation managers for maintenance logs and shot logs.

Migrations:
- `create_equipment_types_table.php`
- `create_equipment_table.php`
- `create_rooms_equipment_pivot_table.php`
- `create_equipment_maintenance_logs_table.php`
- `create_equipment_shot_logs_table.php`
- `create_treatment_equipment_requirements_table.php`

#### Step 4.2: Booking Module

Models:
- `Appointment.php`: Uses BaseModel, HasStateMachine, HasActivity, HasSequence. All fields from schema. Statuses: scheduled→confirmed→checked_in→in_progress→completed (also: cancelled, no_show, rescheduled).
- `AppointmentTreatmentNote.php`: Clinical notes per appointment. Areas treated, machine settings, skin reaction, patient comfort.
- `PractitionerSchedule.php`: Weekly recurring schedule per practitioner per branch.
- `PractitionerTimeOff.php`: Vacation, sick, personal leave with approval workflow.
- `Waitlist.php`: Patients waiting for a slot.

Filament Resources:
- `AppointmentResource.php`:
  - **List page**: Calendar view (default) + table view toggle. Filter by branch, practitioner, room, date range, status.
  - **Create page**: Step wizard:
    1. Select patient (search/create inline)
    2. Select treatment
    3. Select branch → practitioner → room (show availability)
    4. Pick date/time (visual slot picker showing available slots)
    5. Confirm + optional notes
  - **View page**: Full appointment details. Status bar at top. Treatment notes section. Shot log (if laser). Before/after photos. Actions: Check-in, Start, Complete, Cancel, Reschedule, No-Show.
- `PractitionerScheduleResource.php`: Weekly timetable editor. Visual grid (days × hours).
- `PractitionerTimeOffResource.php`: Leave requests with approval workflow.
- `WaitlistResource.php`: Prioritized list with auto-match when slot opens.

Filament Pages:
- `CalendarPage.php`: Full calendar view (FullCalendar.js via Livewire). Day/week/month views. Drag to reschedule. Color-coded by status. Click to view/edit. Branch and practitioner filters.
- `DailyAgendaPage.php`: Today's appointments with check-in/check-out workflow. Designed for reception use.

Key Logic:
- **Availability engine**: Calculate available slots based on practitioner schedule, room availability, equipment availability, existing appointments, time-off, buffer times.
- **Conflict detection**: Prevent double-booking of practitioner, room, or equipment.
- **Auto-reminders**: Queue WhatsApp/SMS reminders (24h and 2h before — configurable).

Migrations:
- `create_appointments_table.php`
- `create_appointment_treatment_notes_table.php`
- `create_practitioner_schedules_table.php`
- `create_practitioner_time_off_table.php`
- `create_waitlist_table.php`

**CHECKPOINT**: Full appointment lifecycle works. Calendar view shows appointments. Can create appointments with availability checking. Status transitions work (scheduled→confirmed→checked_in→in_progress→completed). Equipment shot logs recorded per appointment. Practitioner schedules editable.

---

## PHASE 5: BILLING & ACCOUNTING MODULES
### Goal: Invoicing, payments, and double-entry accounting

#### Step 5.1: Billing Module

Models:
- `Invoice.php`: Uses BaseModel, HasStateMachine, HasSequence, HasActivity. Statuses: draft→issued→partially_paid→paid (also: overdue, cancelled, refunded). All fields from schema.
- `InvoiceLine.php`: Treatment, description, quantity, unit price, discount, tax, total.
- `Payment.php`: Uses BaseModel, HasActivity. Methods: cash, card, bank_transfer, wallet, gift_card, insurance, installment. Gateway integration ready.
- `InstallmentPlan.php`: Payment plan definition.
- `InstallmentSchedule.php`: Individual installment with due date and status.

Filament Resources:
- `InvoiceResource.php`:
  - **List**: Table with status badges, amount, patient, date. Quick filters by status. Totals at bottom.
  - **Create/Edit**: Header (patient, branch, type, date). Line items (table repeater with treatment select, quantity, price auto-fill, discount, tax auto-calculate). Totals section auto-calculated. Notes.
  - **View**: Status bar. Line items read-only. Payment history table. Actions: Issue, Record Payment, Add Credit Note, Cancel.
  - **Record Payment modal**: Amount, method select, reference, gateway. Supports partial payments.
- `PaymentResource.php`: Read-only list of all payments. Filters by method, date range, branch.

Filament Widgets:
- `RevenueWidget.php`: Today's / this month's revenue.
- `OutstandingWidget.php`: Total unpaid invoices.
- `PaymentMethodBreakdown.php`: Pie chart of payment methods.

Key Logic:
- **Auto-invoice**: Generate invoice when appointment completes (if setting enabled).
- **Tax calculation**: VAT 14% (configurable). Tax inclusive/exclusive.
- **Multi-payment**: One invoice can have multiple payments (partial payments).
- **Installments**: Create payment schedule, track individual installments, overdue alerts.
- **Currency**: All amounts stored as minor units (piasters). Use `brick/money` for display.

Events Emitted:
- `InvoiceIssued`, `InvoicePaid`, `InvoiceOverdue`, `PaymentReceived`, `RefundProcessed`

Migrations:
- `create_invoices_table.php`
- `create_invoice_lines_table.php`
- `create_payments_table.php`
- `create_installment_plans_table.php`
- `create_installment_schedule_table.php`
- `create_tax_rates_table.php`

#### Step 5.2: Accounting Module

Models:
- `ChartOfAccount.php`: Self-referencing tree. Types: asset, liability, equity, revenue, expense. Sub-types. Balance tracking.
- `JournalEntry.php`: Uses BaseModel, HasStateMachine, HasSequence, HasActivity. Statuses: draft→posted (also: cancelled). Must balance (total debit = total credit).
- `JournalEntryLine.php`: Account, debit/credit, description, partner (polymorphic), branch (cost center).
- `FiscalPeriod.php`: Monthly periods with open/closed/locked status.

Filament Resources:
- `ChartOfAccountResource.php`: Tree view showing account hierarchy. Balance column. Type badges.
- `JournalEntryResource.php`:
  - **List**: Table with number, date, reference, total, status.
  - **Create/Edit**: Header (date, reference, description). Lines (table repeater: account select, debit, credit, description). Auto-balance check. Warning if unbalanced.
  - **View**: Lines with debit/credit totals. Source link (invoice, payment, etc.). Reverse button.

Filament Pages:
- `TrialBalancePage.php`: All accounts with debit/credit totals for a period.
- `ProfitLossPage.php`: Revenue - Expenses = Net Income. Filterable by period, branch.
- `BalanceSheetPage.php`: Assets = Liabilities + Equity. Point-in-time.
- `CashFlowPage.php`: Inflows/outflows by category.
- `GeneralLedgerPage.php`: All transactions for a specific account.
- `AccountReconciliationPage.php`: Match bank statements to journal entries.

Key Logic:
- **Auto-journal**: When Billing emits InvoicePaid, Accounting creates journal entry automatically.
- **Deferred revenue**: Package/gift card purchases create deferred revenue (liability). Sessions consumed move to revenue.
- **Depreciation**: Monthly scheduled job calculates equipment depreciation, creates journal entries.
- **Period closing**: Lock fiscal periods to prevent back-dating.

Listeners (reacts to Billing events):
- `CreateJournalOnInvoiceIssued`: DR Accounts Receivable, CR Revenue + VAT
- `CreateJournalOnPaymentReceived`: DR Cash/Bank, CR Accounts Receivable
- `CreateJournalOnRefund`: Reverse of the original

Seeders:
- `ChartOfAccountSeeder.php`: Full pre-seeded chart of accounts (from schema doc: 1000-5990 accounts).
- `FiscalPeriodSeeder.php`: Create 12 monthly periods for current year.
- `TaxRateSeeder.php`: VAT 14% (Egypt default).

Migrations:
- `create_chart_of_accounts_table.php`
- `create_journal_entries_table.php`
- `create_journal_entry_lines_table.php`
- `create_fiscal_periods_table.php`
- `create_tax_rates_table.php`

**CHECKPOINT**: Can create invoices from appointments. Record payments (cash, card). Journal entries auto-created on payment. Trial balance shows. P&L report works. Chart of accounts pre-seeded. Deferred revenue tracked. All financial flows have double-entry journal entries.

---

## PHASE 6: PACKAGES, GIFT CARDS & MEMBERSHIPS
### Goal: Session bundles, gift cards, and memberships with financial integration

#### Step 6.1: Packages Module

Models:
- `Package.php`: Uses BaseModel, HasTranslation, HasActivity. Types: session_bundle, value_bundle.
- `PackageItem.php`: Treatment + quantity included.
- `PackageSubscription.php`: Uses HasStateMachine. Patient's purchased package. Statuses: active→completed (also: expired, cancelled, frozen). Tracks used/remaining sessions.
- `PackageSessionUsage.php`: Log of each session consumed from a package.

Extensions:
- **PatientFormExtension**: Adds "Packages" tab showing active packages, remaining sessions.
- **PatientModelExtension**: Adds `packageSubscriptions()` relationship.
- **InvoiceFormExtension**: Adds package selection when creating invoice.
- **AppointmentFormExtension**: Adds package session selector when booking (use existing package).

#### Step 6.2: GiftCards Module

Models:
- `GiftCard.php`: Uses BaseModel, HasStateMachine, HasSequence, HasActivity. All fields from schema.
- `GiftCardTransaction.php`: Redeem/refund/adjustment with running balance.

Extensions:
- **PatientFormExtension**: Adds "Gift Cards" tab.
- **PatientModelExtension**: Adds `giftCards()`, `receivedGiftCards()`, `total_gift_card_balance`.
- **InvoiceFormExtension**: Adds gift card as payment method.
- **DashboardExtension**: Active gift cards count + total outstanding balance widget.

#### Step 6.3: Memberships Module

Models:
- `Membership.php`: Uses BaseModel, HasTranslation. Tier definition with discount %, included sessions, loyalty multiplier.
- `MembershipSubscription.php`: Uses HasStateMachine, HasActivity. Patient's active membership. Auto-renewal tracking.

Extensions:
- **PatientFormExtension**: Adds "Membership" tab.
- **PatientModelExtension**: Adds `membershipSubscription()`, `is_member`, `member_discount`.
- **InvoiceModelExtension**: Auto-apply member discount to invoice lines.
- **BookingFormExtension**: Show membership badge + priority booking indicator.

**CHECKPOINT**: Can create and sell packages. Sessions consumed from packages during appointments. Gift cards issued, sold, redeemed (partial). Memberships with auto-discount on invoices. All financial transactions create proper journal entries (deferred revenue flows).

---

## PHASE 7: INVENTORY & STAFF MODULES
### Goal: Consumables tracking and staff/payroll management

#### Step 7.1: Inventory Module

Models: `ProductCategory`, `Product`, `StockLevel`, `StockMovement`, `Supplier`, `PurchaseOrder`, `PurchaseOrderLine`

Key features:
- Auto-deduct consumables when appointment completes (based on treatment's avg_usage_per_session)
- Reorder alerts when stock below reorder_point
- Purchase order workflow: draft→sent→partially_received→received
- Multi-branch stock levels
- Inter-branch stock transfers

#### Step 7.2: Staff Module

Models: `StaffProfile`, `StaffCommission`, `StaffCommissionRecord`

Key features:
- Commission rules per treatment or treatment category
- Types: flat per treatment, percentage of revenue, tiered
- Auto-calculate commissions when appointment completes (listens to AppointmentCompleted event)
- Commission approval workflow
- Payroll integration ready (links to Payroll module)

#### Step 7.3: Payroll Module

Models: `PayrollRun`, `PayrollLine`

Key features:
- Monthly payroll run: base salary + approved commissions + bonuses - deductions
- Generate salary slips (PDF)
- Create journal entries for salary expenses
- Depends on: Accounting (for journals), Staff (for commissions)

**CHECKPOINT**: Consumables tracked per appointment. Stock levels update automatically. Purchase orders for reordering. Staff commissions calculated per appointment. Payroll runs generate journal entries.

---

## PHASE 8: MARKETING & NOTIFICATIONS
### Goal: WhatsApp, SMS, Email, and social media integration

#### Step 8.1: MarketingWhatsApp Module

Models: Part of shared `NotificationLog`, `Campaign` models in a Marketing base.

Key features:
- WhatsApp Business Cloud API integration
- Appointment reminders (automated)
- Follow-up messages (automated after X days)
- Campaign builder with audience filters (last visit, tags, treatments, branch)
- Template management (WhatsApp requires pre-approved templates)
- Delivery tracking: sent, delivered, read, failed

#### Step 8.2: MarketingSms Module

Same pattern as WhatsApp but with SMS provider (Twilio / Vodafone EG).

#### Step 8.3: MarketingEmail Module

Same pattern with Mailgun/Resend. Includes HTML email builder.

#### Step 8.4: MarketingSocial Module

Models: `SocialMediaPost`

Key features:
- Post scheduling to Instagram, Facebook, TikTok
- UTM parameter generation for tracking
- Basic engagement metrics
- Content calendar view

Shared Models (used by all marketing modules):
- `NotificationTemplate.php`: Channel-specific templates with placeholders.
- `NotificationLog.php`: Unified log across all channels.
- `Campaign.php`: Audience filter + content + scheduling + analytics.

**CHECKPOINT**: WhatsApp appointment reminders sent automatically. Campaign builder creates and sends multi-channel campaigns. Delivery tracking shows in logs. SMS and email working.

---

## PHASE 9: LOYALTY & REPORTING
### Goal: Points/rewards system and comprehensive reporting

#### Step 9.1: Loyalty Module

Models: `LoyaltyRule`, `LoyaltyTransaction`, `ReferralProgram`

Key features:
- Earn points on spend, visit, referral, birthday, review
- Redeem points for discounts
- Points expiry
- Referral tracking (referrer + referred rewards)
- Membership multiplier (Gold members earn 1.5x)

Extensions:
- **PatientFormExtension**: Loyalty tab showing points, history
- **InvoiceModelExtension**: Points redemption as payment method
- **DashboardExtension**: Loyalty program stats widget

#### Step 9.2: Reporting Module

Filament Pages (each is a full report with filters + chart + table + PDF/Excel export):
- `RevenueReportPage.php`: Revenue by treatment, branch, practitioner, period
- `PatientReportPage.php`: New vs returning, demographics, retention rate
- `AppointmentReportPage.php`: Utilization, no-show rate, cancellation rate, peak hours
- `EquipmentReportPage.php`: Shots fired, maintenance costs, utilization per machine
- `StaffPerformanceReportPage.php`: Revenue per practitioner, appointment count, commission
- `InventoryReportPage.php`: Stock valuation, consumption rate, reorder forecast
- `GiftCardReportPage.php`: Outstanding balance, redemption rate, expiry forecast
- `CampaignReportPage.php`: ROI per campaign, conversion rate, cost per acquisition
- `FinancialSummaryPage.php`: Executive dashboard — P&L, cash flow, receivables aging

**CHECKPOINT**: Loyalty points earn and redeem working. All reports generate with filters. PDF and Excel export functional. Dashboard shows key metrics from all modules.

---

## PHASE 10: PATIENT PORTAL & API
### Goal: Patient self-service and external API

#### Step 10.1: PatientPortal Module

Separate Filament panel (PortalPanel):
- Patient registration/login (phone + OTP or email + password)
- View upcoming appointments
- Book new appointment (treatment select → branch → date → time)
- View treatment history with before/after photos
- View and pay invoices
- Check gift card balance
- Check loyalty points
- Download consent forms
- Update personal info

#### Step 10.2: Api Module

- RESTful API using Laravel Sanctum
- Endpoints for all CRUD operations
- Respects module activation (endpoints only available if module active)
- Rate limiting via QuotaService (X-RateLimit headers)
- API documentation (auto-generated from routes or manual with Scribe)

**CHECKPOINT**: Patients can self-book through portal. API returns proper JSON with pagination, filtering (via spatie/query-builder), and module-awareness.

---

## PHASE 11: TESTING & QUALITY
### Goal: Comprehensive test coverage and code quality

#### Step 11.1: Test Structure
```
tests/
├── Unit/
│   ├── Framework/
│   │   ├── ModuleManagerTest.php
│   │   ├── DependencyResolverTest.php
│   │   ├── QuotaServiceTest.php
│   │   ├── SequenceServiceTest.php
│   │   ├── StateMachineTest.php
│   │   └── RecordPolicyEngineTest.php
│   └── Modules/
│       ├── Billing/
│       │   ├── InvoiceCalculationTest.php
│       │   └── InstallmentPlanTest.php
│       ├── Accounting/
│       │   └── JournalBalanceTest.php
│       ├── Booking/
│       │   └── AvailabilityEngineTest.php
│       └── ...
├── Feature/
│   ├── TenancyTest.php
│   ├── ModuleActivationTest.php
│   ├── PatientCrudTest.php
│   ├── AppointmentFlowTest.php
│   ├── InvoicePaymentFlowTest.php
│   ├── GiftCardFlowTest.php
│   ├── PackageFlowTest.php
│   └── ...
└── Pest.php
```

#### Step 11.2: Key Test Scenarios
1. Module activation respects dependencies
2. Module deactivation blocked if dependants active
3. Quota enforcement blocks creation when limit reached
4. Appointment can't double-book practitioner/room/equipment
5. Invoice totals calculate correctly with tax and discount
6. Journal entries always balance (debit = credit)
7. Gift card balance decreases on redeem, never goes negative
8. Package sessions count down correctly
9. Record policies filter data by branch for scoped roles
10. Sequence numbers are unique even under concurrent requests

#### Step 11.3: Static Analysis
```bash
# PHPStan level 6
vendor/bin/phpstan analyse --level=6

# Laravel Pint formatting
vendor/bin/pint

# Run all tests
vendor/bin/pest --parallel
```

---

## PHASE 12: DEPLOYMENT PREPARATION
### Goal: Production-ready configuration

#### Step 12.1: Docker Production Build
- Multi-stage Dockerfile (build assets → production image)
- Nginx config with proper caching headers
- PHP-FPM tuning (pm.max_children, memory_limit)
- Redis persistence config
- PostgreSQL tuning (shared_buffers, work_mem)

#### Step 12.2: CI/CD Pipeline (GitHub Actions)
```yaml
Jobs:
  1. lint: Run Pint + PHPStan
  2. test: Run Pest tests with PostgreSQL service
  3. build: Build Docker image
  4. deploy: Push to registry, deploy to staging/production
```

#### Step 12.3: Production Configs
- Queue: Redis with Horizon (configure supervisord)
- Cache: Redis with proper TTLs
- Sessions: Redis
- Storage: AWS S3 or Cloudflare R2
- Search: Meilisearch Cloud
- SSL: Let's Encrypt via Caddy or Nginx
- Monitoring: Laravel Telescope (dev only) + Sentry (production)
- Backups: spatie/laravel-backup → S3 (daily DB + weekly files)

---

## QUICK REFERENCE: MODULE CREATION CHECKLIST

When creating ANY new module, follow this exact pattern:

```
1. [ ] Create {Module}Manifest.php with all properties
2. [ ] Create {Module}ServiceProvider.php
3. [ ] Create Models/ with all models extending BaseModel
4. [ ] Apply appropriate traits (HasStateMachine, HasSequence, etc.)
5. [ ] Create Database/Migrations/ for all tables
6. [ ] Create Database/Seeders/ for permissions, sequences, settings
7. [ ] Create Filament/Resources/ extending BaseResource
8. [ ] Create Filament/Widgets/ for dashboard widgets
9. [ ] Create Extensions/ for cross-module integrations
10. [ ] Create Events/ for emitted events
11. [ ] Create Listeners/ for reacting to other modules' events
12. [ ] Create Actions/ for server/scheduled actions
13. [ ] Create Policies/ for authorization + record policies
14. [ ] Create Lang/en/ and Lang/ar/ for translations
15. [ ] Create Routes/web.php and Routes/api.php
16. [ ] Create Tests/ unit and feature tests
17. [ ] Register module in CoreSeeder (modules table)
18. [ ] Add to plan_modules mapping
```

---

## EXECUTION ORDER SUMMARY

```
PHASE 0  → Environment + All packages installed                    [✅ COMPLETED]
PHASE 1  → Framework kernel (module system, base classes)          [✅ COMPLETED]
PHASE 2  → Core module (tenant mgmt) + Auth module (users, RBAC)   [✅ COMPLETED]
PHASE 3  → Patients module + Treatments module                     [✅ COMPLETED]
PHASE 4  → Booking module + Equipment module                       [🔄 NEXT UP]
PHASE 5  → Billing module + Accounting module                      [⏳ UPCOMING]
PHASE 6  → Packages + Gift Cards + Memberships modules             [⏳ UPCOMING]
PHASE 7  → Inventory + Staff + Payroll modules                     [⏳ UPCOMING]
PHASE 8  → Marketing modules (WhatsApp, SMS, Email, Social)        [⏳ UPCOMING]
PHASE 9  → Loyalty + Reporting modules                             [⏳ UPCOMING]
PHASE 10 → Patient Portal + API modules                            [⏳ UPCOMING]
PHASE 11 → Testing + Quality assurance                             [⏳ UPCOMING]
PHASE 12 → Deployment preparation                                  [⏳ UPCOMING]
```

Each phase should be **fully tested and working** before moving to the next.
Each phase **builds on** the previous — never skip ahead.

---

## 📋 PHASE 0 COMPLETION VERIFICATION

### ✅ **Files Successfully Created in Phase 0:**

**Configuration Files:**
- ✅ `config/tenancy.php` - Multi-tenant configuration with schema-per-tenant
- ✅ `config/xlinic.php` - Framework configuration with Egyptian market defaults
- ✅ `.env.example` - Enhanced with multi-tenancy and framework settings

**Test Infrastructure:**
- ✅ `tests/Pest.php` - Pest configuration with XLinic test helpers
- ✅ `tests/TestCase.php` - Base test case with tenant cleanup
- ✅ `tests/CreatesApplication.php` - Laravel application factory
- ✅ `tests/Feature/TenancyTest.php` - Tenant context isolation tests
- ✅ `tests/Unit/Framework/ModuleManagerTest.php` - Framework unit tests
- ✅ `tests/Unit/Framework/` - Directory for framework tests
- ✅ `tests/Unit/Modules/` - Directory for module-specific tests

**Core Laravel Files (Already Existed):**
- ✅ `composer.json` - All required packages installed
- ✅ `docker-compose.yml` - Development environment setup
- ✅ Laravel 11 application structure

### 🎯 **Phase 0 Checkpoint: PASSED**
- ✅ Working development environment
- ✅ All packages installed and configured
- ✅ Multi-tenancy configuration ready
- ✅ Framework configuration with Egyptian defaults
- ✅ Test infrastructure with Pest setup
- ✅ Ready to integrate framework (Phase 1)

**Next Step: Complete Phase 2 Implementation**

## 📋 PHASE 1 COMPLETION VERIFICATION

### ✅ **Files Successfully Created/Updated in Phase 1:**

**Laravel Integration:**
- ✅ `app/Providers/FrameworkServiceProvider.php` - Complete framework bootstrap
- ✅ `config/app.php` - FrameworkServiceProvider registered
- ✅ `bootstrap/app.php` - Middleware registration (tenant, quota)

**Framework Blade Components:**
- ✅ `resources/views/framework/components/status-bar.blade.php` - Odoo-style status timeline
- ✅ `resources/views/framework/components/activity-log.blade.php` - Chatter component with timeline
- ✅ `resources/views/framework/components/quota-widget.blade.php` - Resource usage progress bars
- ✅ `resources/views/framework/layouts/` - Directory created for future layouts

**Framework Files (All 53 from Previous Implementation):**
- ✅ Complete module system with dependency resolution
- ✅ Multi-tenant foundation with managers and middleware
- ✅ BaseModel with UUID v7 and comprehensive traits
- ✅ Security system with RBAC and record policies
- ✅ All registries (Module, Model, Navigation, Settings, etc.)
- ✅ Filament integration base classes (BaseResource, BasePage, etc.)

### 🎯 **Phase 1 Checkpoint: PASSED**
- ✅ Framework kernel fully implemented and integrated
- ✅ Laravel service provider bootstraps all framework components
- ✅ Middleware registered for tenancy and quota management
- ✅ Blade components ready for use in Filament resources
- ✅ Module system can discover, load, and manage modules
- ✅ Complete foundation ready for Phase 2 modules

**Next Step: Complete Phase 2 - Core & Auth Module Integration**

---

## NAMING CONVENTIONS

| Item | Convention | Example |
|------|-----------|---------|
| Module directory | PascalCase | `modules/GiftCards/` |
| Manifest class | {Module}Manifest | `GiftCardsManifest` |
| Service Provider | {Module}ServiceProvider | `GiftCardsServiceProvider` |
| Model | Singular PascalCase | `GiftCard`, `JournalEntry` |
| Migration | Laravel convention | `create_gift_cards_table` |
| Table name | Plural snake_case | `gift_cards`, `journal_entries` |
| Column name | snake_case | `remaining_value_minor` |
| Route name | module.resource.action | `gift-cards.redeem` |
| Permission | module.action | `gift_cards.redeem` |
| Setting key | module.key | `gift_cards.default_expiry_days` |
| Sequence code | snake_case | `gift_card` |
| Event class | PastTense | `GiftCardRedeemed` |
| Listener class | VerbPhrase | `CreateJournalOnRedeem` |
| Filament Resource | {Model}Resource | `GiftCardResource` |
| Translation file | module snake_case | `gift_cards.php` |
| Test class | {Thing}Test | `GiftCardFlowTest` |

---

## CURRENCY HANDLING RULES

- **ALWAYS** store amounts as integers in minor units (piasters/cents)
- Column naming: `*_minor` suffix (e.g., `price_minor`, `total_minor`)
- **NEVER** use float/decimal for money
- Use `brick/money` for arithmetic and display
- Display: `Money::ofMinor(500000, 'EGP')` → `EGP 5,000.00`
- Tax: stored as `decimal(5,2)` → `14.00` means 14%
- All calculations in integer arithmetic to avoid rounding errors

---

## TRANSLATABLE FIELDS RULES

- Translatable fields use jsonb columns: `{"en": "...", "ar": "..."}`
- Use spatie/laravel-translatable on models
- Use filament/spatie-laravel-translatable-plugin for forms
- Default locale: `en`, fallback: `ar`
- Always provide both `en` and `ar` in seeders
- RTL support: detect from locale, apply to Filament panel

---

## API RESPONSE FORMAT

```json
{
  "data": { ... },
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 100
  },
  "links": {
    "next": "...",
    "prev": null
  }
}
```

Error format:
```json
{
  "error": {
    "code": "quota_exceeded",
    "message": "Patient limit reached (500/500)",
    "details": {
      "resource": "patients",
      "limit": 500,
      "current": 500,
      "upgrade_url": "/settings/billing"
    }
  }
}
```
