# XLinic Framework Architecture
## An Odoo-Inspired Modular SaaS Framework on Laravel + Filament

---

## 1. What Makes Odoo a "Framework" (Not Just an App)

Before building, let's understand what Odoo does that makes it extensible:

```
┌──────────────────────────────────────────────────────────────┐
│                    ODOO'S CORE PATTERNS                       │
├────────────────────┬─────────────────────────────────────────┤
│ Module System      │ Apps install/uninstall, extend each     │
│                    │ other's models, views, menus, security  │
├────────────────────┼─────────────────────────────────────────┤
│ ORM + Inheritance  │ Models auto-create DB tables.           │
│                    │ 3 types: extension, delegation,         │
│                    │ prototype inheritance                   │
├────────────────────┼─────────────────────────────────────────┤
│ View Inheritance   │ Module B can inject fields into Module  │
│                    │ A's form/list views via XPath           │
├────────────────────┼─────────────────────────────────────────┤
│ Menu Registry      │ Menus declared in XML, modules add      │
│                    │ their own menu items declaratively       │
├────────────────────┼─────────────────────────────────────────┤
│ Action System      │ Window actions (open view), server      │
│                    │ actions (run code), automated actions    │
├────────────────────┼─────────────────────────────────────────┤
│ Security Layer     │ Group-based access + record rules       │
│                    │ (domain filters per model)              │
├────────────────────┼─────────────────────────────────────────┤
│ Mixins / Traits    │ mail.thread, portal.mixin add behavior  │
│                    │ (messaging, portal access) to any model │
├────────────────────┼─────────────────────────────────────────┤
│ Hooks & Events     │ Models emit signals, other modules      │
│                    │ react without tight coupling             │
├────────────────────┼─────────────────────────────────────────┤
│ Scheduled Actions  │ Cron jobs declared per module            │
├────────────────────┼─────────────────────────────────────────┤
│ Settings Page      │ Each module contributes to a unified     │
│                    │ settings page                            │
└────────────────────┴─────────────────────────────────────────┘
```

## 2. Our Framework Translation: Odoo → Laravel + Filament

```
┌────────────────────┬──────────────────┬──────────────────────┐
│ Odoo Concept       │ Our Equivalent   │ Implementation       │
├────────────────────┼──────────────────┼──────────────────────┤
│ ir.module          │ ModuleManifest   │ PHP class per module │
│ ir.model           │ Eloquent Model   │ + ModelRegistry      │
│ _inherit (extend)  │ Traits + Macros  │ Model extensions     │
│ _inherits (deleg)  │ Polymorphic rels │ Laravel morphTo      │
│ ir.ui.view         │ Filament Resource│ + ViewExtension      │
│ ir.ui.menu         │ NavigationRegistry│ Declarative menus   │
│ ir.actions.act_win │ Filament Pages   │ + ActionRegistry     │
│ ir.actions.server  │ ServerActions    │ Invokable classes    │
│ ir.rule            │ RecordPolicies   │ Eloquent scopes      │
│ ir.cron            │ ScheduleRegistry │ Laravel scheduler    │
│ res.config.settings│ SettingsRegistry │ Filament settings    │
│ mail.thread        │ HasActivity trait│ Notification system  │
│ portal.mixin       │ HasPortalAccess  │ Patient portal       │
│ ir.sequence        │ SequenceService  │ Auto-numbering       │
│ Bus (Odoo 17)      │ Laravel Events   │ Event/Listener       │
│ QWeb Reports       │ Report Engine    │ Blade + PDF          │
│ XML data files     │ Seeders + Config │ Module seeders       │
│ ir.config_parameter│ Settings model   │ Tenant settings      │
└────────────────────┴──────────────────┴──────────────────────┘
```

---

## 3. Framework Directory Structure

```
xlinic/
├── framework/                          # THE KERNEL (never touched by modules)
│   ├── Core/
│   │   ├── Module/
│   │   │   ├── ModuleManifest.php          # Base class for module definitions
│   │   │   ├── ModuleManager.php           # Boot, resolve, activate, deactivate
│   │   │   ├── ModuleRegistry.php          # Runtime registry of all modules
│   │   │   ├── DependencyResolver.php      # Topological sort for boot order
│   │   │   └── ModuleServiceProvider.php   # Auto-discovers and boots modules
│   │   │
│   │   ├── Model/
│   │   │   ├── BaseModel.php               # Extends Eloquent with framework features
│   │   │   ├── ModelRegistry.php           # Tracks all models, extensions, relations
│   │   │   ├── ModelExtension.php          # How Module B extends Module A's model
│   │   │   ├── Traits/
│   │   │   │   ├── HasTenancy.php          # Automatic tenant scoping
│   │   │   │   ├── HasActivity.php         # Like Odoo's mail.thread
│   │   │   │   ├── HasPortalAccess.php     # Patient portal visibility
│   │   │   │   ├── HasSequence.php         # Auto-numbering (INV-0001)
│   │   │   │   ├── HasStateMachine.php     # Status workflow (draft→confirmed→done)
│   │   │   │   ├── HasAudit.php            # Auto audit logging
│   │   │   │   ├── HasSoftDeletes.php      # Framework-level soft delete
│   │   │   │   ├── HasTranslation.php      # JSON translatable fields
│   │   │   │   ├── HasTags.php             # Tagging system
│   │   │   │   └── HasAttachments.php      # File attachments on any model
│   │   │   └── Scopes/
│   │   │       ├── TenantScope.php         # Global scope for multi-tenancy
│   │   │       ├── BranchScope.php         # Branch-level filtering
│   │   │       └── ActiveScope.php         # Only show is_active=true
│   │   │
│   │   ├── View/
│   │   │   ├── ViewExtensionManager.php    # Module B extends Module A's form
│   │   │   ├── FormExtension.php           # Add fields to another module's form
│   │   │   ├── TableExtension.php          # Add columns to another module's table
│   │   │   ├── PageExtension.php           # Add tabs/sections to pages
│   │   │   └── WidgetExtension.php         # Add dashboard widgets
│   │   │
│   │   ├── Navigation/
│   │   │   ├── NavigationRegistry.php      # Central menu builder
│   │   │   ├── NavigationGroup.php         # Menu group definition
│   │   │   └── NavigationItem.php          # Menu item with module/permission checks
│   │   │
│   │   ├── Action/
│   │   │   ├── ActionRegistry.php          # All registered actions
│   │   │   ├── ServerAction.php            # Base class for server actions
│   │   │   ├── AutomatedAction.php         # Triggered by model events
│   │   │   └── ScheduledAction.php         # Cron-like jobs
│   │   │
│   │   ├── Security/
│   │   │   ├── PermissionRegistry.php      # All permissions from all modules
│   │   │   ├── RecordPolicy.php            # Odoo ir.rule equivalent
│   │   │   ├── RecordPolicyEngine.php      # Evaluates record-level access
│   │   │   ├── FieldAccess.php             # Field-level read/write control
│   │   │   └── SecuritySeeder.php          # Seeds permissions on module activate
│   │   │
│   │   ├── Settings/
│   │   │   ├── SettingsRegistry.php        # Modules register their settings
│   │   │   ├── SettingsPage.php            # Unified Filament settings page
│   │   │   └── SettingDefinition.php       # Setting with type, default, validation
│   │   │
│   │   ├── Sequence/
│   │   │   ├── SequenceService.php         # Auto-number generator
│   │   │   └── SequenceDefinition.php      # Pattern: {prefix}-{year}-{####}
│   │   │
│   │   ├── Report/
│   │   │   ├── ReportRegistry.php          # All available reports
│   │   │   ├── BaseReport.php              # Report base class
│   │   │   └── ReportEngine.php            # Blade → PDF rendering
│   │   │
│   │   ├── Event/
│   │   │   ├── ModelEvent.php              # Standardized model events
│   │   │   └── ModuleEvent.php             # Module lifecycle events
│   │   │
│   │   ├── Quota/
│   │   │   ├── QuotaService.php            # Plan limits enforcement
│   │   │   └── QuotaMiddleware.php         # Request-level checks
│   │   │
│   │   ├── Tenancy/
│   │   │   ├── TenantManager.php           # Current tenant resolution
│   │   │   ├── TenantMiddleware.php        # Set tenant per request
│   │   │   └── TenantAwareJob.php          # Queue jobs with tenant context
│   │   │
│   │   └── Filament/
│   │       ├── BaseResource.php            # Extends Filament Resource with framework
│   │       ├── BasePage.php                # Base page with module checks
│   │       ├── BaseWidget.php              # Base widget with module checks
│   │       ├── BaseRelationManager.php     # Relation with extensions
│   │       └── Panels/
│   │           ├── AdminPanel.php          # Clinic admin panel config
│   │           ├── SuperAdminPanel.php     # Platform owner panel
│   │           └── PortalPanel.php         # Patient-facing panel
│   │
│   └── helpers.php                         # Global framework helpers
│
├── modules/                                # ALL FEATURE MODULES
│   ├── Core/                               # Always active (tenancy, users, settings)
│   ├── Auth/                               # Roles, permissions, access matrix
│   ├── Patients/
│   ├── Booking/
│   ├── Treatments/
│   ├── Equipment/
│   ├── Billing/
│   ├── Accounting/
│   ├── Packages/
│   ├── GiftCards/
│   ├── Memberships/
│   ├── Inventory/
│   ├── Staff/
│   ├── Payroll/
│   ├── Loyalty/
│   ├── MarketingWhatsApp/
│   ├── MarketingSms/
│   ├── MarketingEmail/
│   ├── MarketingSocial/
│   ├── Reporting/
│   ├── PatientPortal/
│   └── Api/
│
├── app/                                    # Standard Laravel app (thin layer)
│   ├── Providers/
│   │   └── AppServiceProvider.php          # Boots the framework
│   └── Http/
│       └── Kernel.php
│
├── config/
│   └── xlinic.php                       # Framework config
├── database/
├── resources/
├── routes/
└── tests/
```

---

## 4. Module Anatomy — The Standard Structure

Every module follows the exact same structure. This is what makes it a framework:

```
modules/GiftCards/
├── GiftCardsManifest.php          # Module identity & dependencies
├── GiftCardsServiceProvider.php   # Boot logic
├── Config/
│   └── gift_cards.php             # Module config defaults
├── Models/
│   ├── GiftCard.php
│   └── GiftCardTransaction.php
├── Filament/
│   ├── Resources/
│   │   ├── GiftCardResource.php
│   │   └── GiftCardResource/
│   │       └── Pages/
│   │           ├── ListGiftCards.php
│   │           ├── CreateGiftCard.php
│   │           └── ViewGiftCard.php
│   ├── Widgets/
│   │   ├── GiftCardStatsWidget.php
│   │   └── GiftCardExpiryWidget.php
│   ├── Pages/
│   │   └── RedeemGiftCard.php
│   └── RelationManagers/
│       └── TransactionsRelationManager.php
├── Extensions/                     # THIS IS THE ODOO-LIKE MAGIC
│   ├── PatientFormExtension.php   # Adds "Gift Cards" tab to Patient form
│   ├── InvoiceFormExtension.php   # Adds gift card payment option
│   ├── PatientModelExtension.php  # Adds giftCards() relation to Patient
│   └── DashboardExtension.php     # Adds widget to main dashboard
├── Actions/
│   ├── IssueGiftCard.php          # Server action
│   ├── RedeemGiftCard.php
│   └── SendExpiryReminders.php    # Scheduled action
├── Policies/
│   ├── GiftCardPolicy.php
│   └── RecordPolicies/
│       └── GiftCardBranchPolicy.php  # Record-level (ir.rule)
├── Events/
│   ├── GiftCardIssued.php
│   ├── GiftCardRedeemed.php
│   └── GiftCardExpired.php
├── Listeners/
│   └── CreateJournalOnRedeem.php  # Listens to GiftCardRedeemed
├── Services/
│   └── GiftCardService.php
├── Notifications/
│   ├── GiftCardIssuedNotification.php
│   └── GiftCardExpiryNotification.php
├── Database/
│   ├── Migrations/
│   │   ├── 2024_01_01_create_gift_cards_table.php
│   │   └── 2024_01_01_create_gift_card_transactions_table.php
│   └── Seeders/
│       ├── GiftCardPermissionSeeder.php
│       ├── GiftCardSequenceSeeder.php
│       └── GiftCardSettingsSeeder.php
├── Routes/
│   ├── web.php
│   └── api.php
├── Reports/
│   ├── GiftCardBalanceReport.php
│   └── views/
│       └── gift-card-balance.blade.php
├── Lang/
│   ├── en/
│   │   └── gift_cards.php
│   └── ar/
│       └── gift_cards.php
└── Tests/
    ├── Unit/
    └── Feature/
```

---

## 5. Module Manifest — The Heart of Each Module

```php
// modules/GiftCards/GiftCardsManifest.php

namespace Modules\GiftCards;

use Framework\Core\Module\ModuleManifest;

class GiftCardsManifest extends ModuleManifest
{
    /**
     * Unique module identifier.
     */
    public string $code = 'gift_cards';

    /**
     * Human-readable name (translatable).
     */
    public array $name = [
        'en' => 'Gift Cards & Vouchers',
        'ar' => 'بطاقات الهدايا والقسائم',
    ];

    /**
     * Module description.
     */
    public array $description = [
        'en' => 'Issue, sell, and redeem gift cards. Physical and digital cards with balance tracking.',
        'ar' => 'إصدار وبيع واسترداد بطاقات الهدايا. بطاقات فعلية ورقمية مع تتبع الرصيد.',
    ];

    /**
     * Module category for grouping in UI.
     */
    public string $category = 'sales';

    /**
     * Icon (Heroicon name).
     */
    public string $icon = 'heroicon-o-gift';

    /**
     * Version.
     */
    public string $version = '1.0.0';

    /**
     * Modules that MUST be active for this module to work.
     */
    public array $dependencies = [
        'billing',   // Needs invoicing
        'patients',  // Needs patient records
    ];

    /**
     * Modules that this module CAN extend (soft dependency).
     * Extensions only load if the target module is active.
     */
    public array $optionalDependencies = [
        'accounting',  // If active, auto-create journal entries
        'loyalty',     // If active, earn points on gift card purchases
    ];

    /**
     * Models registered by this module.
     */
    public array $models = [
        GiftCard::class,
        GiftCardTransaction::class,
    ];

    /**
     * Extensions this module applies to other modules.
     * Only loaded if the target module is active.
     */
    public array $extensions = [
        // Target Model → Extension class
        'model' => [
            \Modules\Patients\Models\Patient::class => Extensions\PatientModelExtension::class,
            \Modules\Billing\Models\Invoice::class  => Extensions\InvoiceModelExtension::class,
        ],
        // Target Filament Resource → Form/Table extensions
        'form' => [
            \Modules\Patients\Filament\Resources\PatientResource::class => Extensions\PatientFormExtension::class,
            \Modules\Billing\Filament\Resources\InvoiceResource::class  => Extensions\InvoiceFormExtension::class,
        ],
        'table' => [],
        'dashboard' => [
            Extensions\DashboardExtension::class,
        ],
    ];

    /**
     * Permissions this module introduces.
     */
    public array $permissions = [
        'gift_cards.view_any'   => ['en' => 'View gift cards list',    'ar' => 'عرض قائمة بطاقات الهدايا'],
        'gift_cards.view'       => ['en' => 'View gift card details',  'ar' => 'عرض تفاصيل بطاقة الهدايا'],
        'gift_cards.create'     => ['en' => 'Issue new gift cards',    'ar' => 'إصدار بطاقات هدايا جديدة'],
        'gift_cards.update'     => ['en' => 'Edit gift cards',         'ar' => 'تعديل بطاقات الهدايا'],
        'gift_cards.redeem'     => ['en' => 'Redeem gift cards',       'ar' => 'استرداد بطاقات الهدايا'],
        'gift_cards.void'       => ['en' => 'Void/cancel gift cards',  'ar' => 'إلغاء بطاقات الهدايا'],
        'gift_cards.report'     => ['en' => 'View gift card reports',  'ar' => 'عرض تقارير بطاقات الهدايا'],
    ];

    /**
     * Default permission assignments for built-in roles.
     */
    public array $defaultRolePermissions = [
        'owner'          => ['gift_cards.*'],
        'branch_manager' => ['gift_cards.view_any', 'gift_cards.view', 'gift_cards.create', 'gift_cards.redeem'],
        'receptionist'   => ['gift_cards.view', 'gift_cards.redeem'],
        'accountant'     => ['gift_cards.view_any', 'gift_cards.report'],
    ];

    /**
     * Record-level access policies (Odoo ir.rule equivalent).
     */
    public array $recordPolicies = [
        [
            'model'  => GiftCard::class,
            'name'   => 'Branch-scoped gift cards',
            'domain' => ['branch_id' => '{user.branch_id}'],  // Resolved at runtime
            'roles'  => ['branch_manager', 'receptionist'],    // Applied to these roles
            'global' => false,  // true = applies to ALL roles
        ],
    ];

    /**
     * Navigation items this module adds.
     */
    public array $navigation = [
        [
            'group'      => 'sales',
            'label'      => ['en' => 'Gift Cards', 'ar' => 'بطاقات الهدايا'],
            'icon'       => 'heroicon-o-gift',
            'route'      => 'filament.admin.resources.gift-cards.index',
            'permission' => 'gift_cards.view_any',
            'sort'       => 30,
            'badge'      => 'active_gift_cards_count',  // Dynamic badge
        ],
    ];

    /**
     * Settings this module contributes to the Settings page.
     */
    public array $settings = [
        [
            'key'         => 'gift_cards.default_expiry_days',
            'type'        => 'number',
            'label'       => ['en' => 'Default Expiry (days)', 'ar' => 'مدة الصلاحية الافتراضية (أيام)'],
            'default'     => 365,
            'validation'  => 'required|integer|min:30|max:3650',
            'group'       => 'sales',
            'description' => ['en' => 'Default number of days before a gift card expires'],
        ],
        [
            'key'     => 'gift_cards.allow_partial_redemption',
            'type'    => 'toggle',
            'label'   => ['en' => 'Allow Partial Redemption', 'ar' => 'السماح بالاسترداد الجزئي'],
            'default' => true,
            'group'   => 'sales',
        ],
        [
            'key'     => 'gift_cards.code_prefix',
            'type'    => 'text',
            'label'   => ['en' => 'Code Prefix', 'ar' => 'بادئة الرمز'],
            'default' => 'GC',
            'group'   => 'sales',
        ],
        [
            'key'     => 'gift_cards.require_recipient_info',
            'type'    => 'toggle',
            'label'   => ['en' => 'Require Recipient Info', 'ar' => 'طلب معلومات المستلم'],
            'default' => false,
            'group'   => 'sales',
        ],
    ];

    /**
     * Auto-numbering sequences.
     */
    public array $sequences = [
        [
            'code'    => 'gift_card',
            'prefix'  => 'GC-',
            'padding' => 6,
            'pattern' => '{prefix}{YYYY}-{######}',  // GC-2024-000001
        ],
    ];

    /**
     * Scheduled actions (cron jobs).
     */
    public array $scheduledActions = [
        [
            'action'      => Actions\SendExpiryReminders::class,
            'frequency'   => 'daily',
            'time'        => '09:00',
            'description' => 'Send gift card expiry reminders',
        ],
    ];

    /**
     * Events this module emits.
     */
    public array $events = [
        Events\GiftCardIssued::class,
        Events\GiftCardRedeemed::class,
        Events\GiftCardExpired::class,
    ];

    /**
     * Event listeners (react to other modules' events).
     */
    public array $listeners = [
        // When an invoice is paid, check if any gift cards were purchased
        \Modules\Billing\Events\InvoicePaid::class => [
            Listeners\ActivateGiftCardsOnPayment::class,
        ],
    ];
}
```

---

## 6. The Extension System — The Most Powerful Feature

This is what makes it truly Odoo-like. Module B can extend Module A's models, forms, tables, and pages WITHOUT modifying Module A's code.

### 6a. Model Extensions

```php
// modules/GiftCards/Extensions/PatientModelExtension.php
//
// This adds gift card relations and methods to the Patient model
// without touching the Patient module's code.

namespace Modules\GiftCards\Extensions;

use Framework\Core\Model\ModelExtension;

class PatientModelExtension extends ModelExtension
{
    /**
     * Target model this extension applies to.
     */
    public string $target = \Modules\Patients\Models\Patient::class;

    /**
     * Add new relationships to the Patient model.
     */
    public function relationships(): array
    {
        return [
            'giftCards' => function ($model) {
                return $model->hasMany(\Modules\GiftCards\Models\GiftCard::class, 'purchaser_patient_id');
            },
            'receivedGiftCards' => function ($model) {
                return $model->hasMany(\Modules\GiftCards\Models\GiftCard::class, 'recipient_patient_id');
            },
        ];
    }

    /**
     * Add computed attributes.
     */
    public function attributes(): array
    {
        return [
            'total_gift_card_balance' => function ($model) {
                return $model->giftCards()
                    ->where('status', 'active')
                    ->sum('remaining_value_minor');
            },
        ];
    }

    /**
     * Add scopes.
     */
    public function scopes(): array
    {
        return [
            'hasActiveGiftCards' => function ($query) {
                return $query->whereHas('giftCards', function ($q) {
                    $q->where('status', 'active')
                      ->where('remaining_value_minor', '>', 0);
                });
            },
        ];
    }
}
```

### 6b. Framework ModelRegistry — How Extensions are Applied

```php
// framework/Core/Model/ModelRegistry.php

class ModelRegistry
{
    protected array $extensions = [];

    /**
     * Register a model extension (called during module boot).
     */
    public function registerExtension(string $targetModel, string $extensionClass): void
    {
        $this->extensions[$targetModel][] = $extensionClass;
    }

    /**
     * Apply all registered extensions to a model.
     * Called from BaseModel::booted()
     */
    public function applyExtensions(BaseModel $model): void
    {
        $extensions = $this->extensions[get_class($model)] ?? [];

        foreach ($extensions as $extensionClass) {
            $extension = new $extensionClass();

            // Add relationships via macros
            foreach ($extension->relationships() as $name => $closure) {
                $model::resolveRelationUsing($name, $closure);
            }

            // Add scopes
            foreach ($extension->scopes() as $name => $closure) {
                $model::macro('scope' . ucfirst($name), $closure);
            }

            // Add attributes via accessors
            foreach ($extension->attributes() as $name => $closure) {
                $model::macro('get' . Str::studly($name) . 'Attribute', $closure);
            }
        }
    }
}
```

### 6c. Form Extensions — Module B Adds Fields to Module A's Form

```php
// modules/GiftCards/Extensions/PatientFormExtension.php
//
// This adds a "Gift Cards" tab to the Patient edit form

namespace Modules\GiftCards\Extensions;

use Framework\Core\View\FormExtension;
use Filament\Forms\Components;

class PatientFormExtension extends FormExtension
{
    /**
     * Which resource's form to extend.
     */
    public string $target = \Modules\Patients\Filament\Resources\PatientResource::class;

    /**
     * Where to inject: 'tabs', 'sidebar', 'after_main', 'before_main'
     */
    public string $position = 'tabs';

    /**
     * Priority (lower = first). Useful when multiple modules extend the same form.
     */
    public int $priority = 50;

    /**
     * Required module permission to see this extension.
     */
    public ?string $permission = 'gift_cards.view';

    /**
     * The form components to inject.
     */
    public function getFormComponents(): array
    {
        return [
            Components\Tabs\Tab::make(__('gift_cards::gift_cards.tab_title'))
                ->icon('heroicon-o-gift')
                ->schema([
                    // Active Gift Cards
                    Components\Placeholder::make('active_gift_cards')
                        ->label(__('Active Gift Cards'))
                        ->content(function ($record) {
                            if (! $record) return 'Save patient first';
                            $count = $record->giftCards()->where('status', 'active')->count();
                            $balance = $record->total_gift_card_balance / 100;
                            return "{$count} active cards — EGP {$balance} total balance";
                        }),

                    // Issue New Gift Card action button
                    Components\Actions::make([
                        Components\Actions\Action::make('issueGiftCard')
                            ->label(__('Issue Gift Card'))
                            ->icon('heroicon-o-plus')
                            ->modalHeading(__('Issue New Gift Card'))
                            ->form([
                                Components\TextInput::make('value')
                                    ->label(__('Card Value (EGP)'))
                                    ->numeric()
                                    ->required(),
                                Components\DatePicker::make('expires_at')
                                    ->label(__('Expires At'))
                                    ->default(now()->addDays(
                                        setting('gift_cards.default_expiry_days')
                                    )),
                            ])
                            ->action(function (array $data, $record) {
                                app(GiftCardService::class)->issue($record, $data);
                            })
                            ->visible(fn() => auth()->user()->can('gift_cards.create')),
                    ]),
                ]),
        ];
    }
}
```

### 6d. Table Extensions — Add Columns to Another Module's Table

```php
// modules/GiftCards/Extensions/PatientTableExtension.php
//
// Adds "Gift Card Balance" column to the Patients list

namespace Modules\GiftCards\Extensions;

use Framework\Core\View\TableExtension;
use Filament\Tables\Columns;

class PatientTableExtension extends TableExtension
{
    public string $target = \Modules\Patients\Filament\Resources\PatientResource::class;

    public int $priority = 50;

    public function getColumns(): array
    {
        return [
            Columns\TextColumn::make('total_gift_card_balance')
                ->label(__('GC Balance'))
                ->money('EGP', divideBy: 100)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function getFilters(): array
    {
        return [
            Tables\Filters\Filter::make('has_gift_cards')
                ->label(__('Has Active Gift Cards'))
                ->query(fn ($query) => $query->hasActiveGiftCards()),
        ];
    }

    public function getBulkActions(): array
    {
        return [
            Tables\Actions\BulkAction::make('issueGiftCards')
                ->label(__('Issue Gift Cards'))
                ->icon('heroicon-o-gift')
                ->visible(fn() => auth()->user()->can('gift_cards.create'))
                ->action(function ($records) {
                    // Bulk issue
                }),
        ];
    }
}
```

### 6e. ViewExtensionManager — How the Framework Wires It All Together

```php
// framework/Core/View/ViewExtensionManager.php

class ViewExtensionManager
{
    protected array $formExtensions = [];
    protected array $tableExtensions = [];
    protected array $dashboardExtensions = [];

    /**
     * Register extensions from a module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        foreach ($manifest->extensions['form'] ?? [] as $target => $extension) {
            $this->formExtensions[$target][] = $extension;
        }
        foreach ($manifest->extensions['table'] ?? [] as $target => $extension) {
            $this->tableExtensions[$target][] = $extension;
        }
        foreach ($manifest->extensions['dashboard'] ?? [] as $extension) {
            $this->dashboardExtensions[] = $extension;
        }
    }

    /**
     * Get all form extensions for a given resource.
     * Called from BaseResource::form()
     */
    public function getFormExtensions(string $resourceClass): Collection
    {
        return collect($this->formExtensions[$resourceClass] ?? [])
            ->map(fn($class) => new $class())
            ->filter(fn($ext) => $this->isExtensionVisible($ext))
            ->sortBy('priority');
    }

    /**
     * Check if extension's module is active AND user has permission.
     */
    protected function isExtensionVisible(FormExtension $ext): bool
    {
        // Check if the module providing this extension is active
        if (! app(ModuleRegistry::class)->isActive($ext->getModuleCode())) {
            return false;
        }

        // Check permission
        if ($ext->permission && ! auth()->user()->can($ext->permission)) {
            return false;
        }

        return true;
    }
}
```

---

## 7. BaseModel — The Framework's Eloquent Foundation

```php
// framework/Core/Model/BaseModel.php

namespace Framework\Core\Model;

use Illuminate\Database\Eloquent\Model;
use Framework\Core\Model\Traits\*;

abstract class BaseModel extends Model
{
    use HasTenancy;       // Auto tenant scoping
    use HasAudit;         // Auto audit log on changes
    use HasSoftDeletes;   // Soft delete with cascade awareness

    /**
     * Boot: apply model extensions from other modules.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Apply extensions registered by other modules
        app(ModelRegistry::class)->applyExtensions(new static);
    }

    /**
     * Get a setting value (module or global).
     */
    protected function setting(string $key, mixed $default = null): mixed
    {
        return app(SettingsRegistry::class)->get($key, $default);
    }

    /**
     * Generate next sequence number.
     */
    protected function nextSequence(string $code): string
    {
        return app(SequenceService::class)->next($code);
    }

    /**
     * Get display name for audit log.
     */
    public function getDisplayName(): string
    {
        return $this->name
            ?? $this->code
            ?? $this->number
            ?? "#{$this->id}";
    }
}
```

### Optional Traits Applied Per-Model:

```php
// modules/GiftCards/Models/GiftCard.php

class GiftCard extends BaseModel
{
    use HasSequence;       // Auto-generates GC-2024-000001
    use HasStateMachine;   // Status transitions with validation
    use HasActivity;       // Activity log (like Odoo chatter)
    use HasTranslation;    // JSON translatable fields
    use HasAttachments;    // File attachments

    protected string $sequenceCode = 'gift_card';

    /**
     * State machine definition (like Odoo statusbar).
     */
    protected function stateMachine(): array
    {
        return [
            'field' => 'status',
            'states' => [
                'draft'         => ['label' => 'Draft'],
                'active'        => ['label' => 'Active',       'color' => 'success'],
                'partially_used'=> ['label' => 'Partially Used','color' => 'warning'],
                'fully_used'    => ['label' => 'Fully Used',   'color' => 'gray'],
                'expired'       => ['label' => 'Expired',      'color' => 'danger'],
                'cancelled'     => ['label' => 'Cancelled',    'color' => 'danger'],
            ],
            'transitions' => [
                'activate'  => ['from' => ['draft'],                   'to' => 'active'],
                'use'       => ['from' => ['active'],                  'to' => 'partially_used'],
                'exhaust'   => ['from' => ['active', 'partially_used'],'to' => 'fully_used'],
                'expire'    => ['from' => ['active', 'partially_used'],'to' => 'expired'],
                'cancel'    => ['from' => ['draft', 'active'],         'to' => 'cancelled'],
            ],
            'hooks' => [
                'before_activate' => 'validateActivation',
                'after_activate'  => 'onActivated',
                'after_cancel'    => 'onCancelled',
            ],
        ];
    }
}
```

---

## 8. BaseResource — Filament Resource with Framework Powers

```php
// framework/Core/Filament/BaseResource.php

namespace Framework\Core\Filament;

use Filament\Resources\Resource;

abstract class BaseResource extends Resource
{
    /**
     * The module code this resource belongs to.
     * Used for module activation checks.
     */
    protected static ?string $moduleCode = null;

    /**
     * Module + permission check for access.
     */
    public static function canAccess(): bool
    {
        // Check if module is active
        if (static::$moduleCode) {
            if (! app(ModuleRegistry::class)->isActive(static::$moduleCode)) {
                return false;
            }
        }

        return parent::canAccess();
    }

    /**
     * Build form with extensions from other modules.
     */
    public static function form(Form $form): Form
    {
        $baseSchema = static::getBaseFormSchema();

        // Collect form extensions from other active modules
        $extensions = app(ViewExtensionManager::class)
            ->getFormExtensions(static::class);

        // Inject extensions by position
        $schema = static::mergeFormExtensions($baseSchema, $extensions);

        return $form->schema($schema);
    }

    /**
     * Build table with extensions.
     */
    public static function table(Table $table): Table
    {
        $baseTable = static::getBaseTable($table);

        // Collect table extensions
        $extensions = app(ViewExtensionManager::class)
            ->getTableExtensions(static::class);

        // Add extra columns, filters, bulk actions
        foreach ($extensions as $ext) {
            foreach ($ext->getColumns() as $column) {
                $baseTable->pushColumn($column);
            }
            foreach ($ext->getFilters() as $filter) {
                $baseTable->pushFilter($filter);
            }
            foreach ($ext->getBulkActions() as $action) {
                $baseTable->pushBulkAction($action);
            }
        }

        return $baseTable;
    }

    /**
     * Override in each resource to define the base form.
     */
    abstract protected static function getBaseFormSchema(): array;

    /**
     * Merge tab/section extensions into the base form.
     */
    protected static function mergeFormExtensions(array $base, Collection $extensions): array
    {
        $tabs = [];
        $sidebar = [];
        $afterMain = [];

        foreach ($extensions as $extension) {
            $components = $extension->getFormComponents();

            match ($extension->position) {
                'tabs'        => $tabs = array_merge($tabs, $components),
                'sidebar'     => $sidebar = array_merge($sidebar, $components),
                'after_main'  => $afterMain = array_merge($afterMain, $components),
                'before_main' => array_unshift($base, ...$components),
            };
        }

        // If there are tab extensions, wrap the base form in tabs
        if (! empty($tabs)) {
            // Find existing Tabs component or create one
            $base = static::ensureTabsWrapper($base, $tabs);
        }

        return array_merge($base, $afterMain);
    }
}
```

---

## 9. Navigation Registry — Declarative Menu System

```php
// framework/Core/Navigation/NavigationRegistry.php

class NavigationRegistry
{
    /**
     * Navigation groups with their items.
     * Modules register items here via their manifest.
     */
    protected array $groups = [
        'dashboard' => [
            'label' => ['en' => 'Dashboard', 'ar' => 'لوحة التحكم'],
            'icon'  => 'heroicon-o-home',
            'sort'  => 0,
        ],
        'crm' => [
            'label' => ['en' => 'Patients', 'ar' => 'المرضى'],
            'icon'  => 'heroicon-o-users',
            'sort'  => 10,
        ],
        'operations' => [
            'label' => ['en' => 'Operations', 'ar' => 'العمليات'],
            'icon'  => 'heroicon-o-calendar',
            'sort'  => 20,
        ],
        'sales' => [
            'label' => ['en' => 'Sales', 'ar' => 'المبيعات'],
            'icon'  => 'heroicon-o-shopping-cart',
            'sort'  => 30,
        ],
        'financial' => [
            'label' => ['en' => 'Accounting', 'ar' => 'المحاسبة'],
            'icon'  => 'heroicon-o-calculator',
            'sort'  => 40,
        ],
        'inventory' => [
            'label' => ['en' => 'Inventory', 'ar' => 'المخزون'],
            'icon'  => 'heroicon-o-cube',
            'sort'  => 50,
        ],
        'marketing' => [
            'label' => ['en' => 'Marketing', 'ar' => 'التسويق'],
            'icon'  => 'heroicon-o-megaphone',
            'sort'  => 60,
        ],
        'hr' => [
            'label' => ['en' => 'Staff & HR', 'ar' => 'الموظفين'],
            'icon'  => 'heroicon-o-user-group',
            'sort'  => 70,
        ],
        'reports' => [
            'label' => ['en' => 'Reports', 'ar' => 'التقارير'],
            'icon'  => 'heroicon-o-chart-bar',
            'sort'  => 80,
        ],
        'settings' => [
            'label' => ['en' => 'Settings', 'ar' => 'الإعدادات'],
            'icon'  => 'heroicon-o-cog-6-tooth',
            'sort'  => 99,
        ],
    ];

    /**
     * Build navigation for the current user.
     * Filters by: active module, user permissions, branch access.
     */
    public function build(): array
    {
        $items = collect($this->items)
            ->filter(function ($item) {
                // Module active?
                if (! app(ModuleRegistry::class)->isActive($item['module'])) {
                    return false;
                }
                // User has permission?
                if ($item['permission'] && ! auth()->user()->can($item['permission'])) {
                    return false;
                }
                return true;
            })
            ->groupBy('group')
            ->map(function ($groupItems, $groupKey) {
                return [
                    'group' => $this->groups[$groupKey],
                    'items' => $groupItems->sortBy('sort')->values(),
                ];
            })
            ->filter(fn ($group) => $group['items']->isNotEmpty())
            ->sortBy(fn ($g) => $g['group']['sort']);

        return $items->toArray();
    }
}
```

**The resulting sidebar adapts per user:**

```
Owner sees:                    Receptionist sees:
┌─────────────────────┐       ┌─────────────────────┐
│ 🏠 Dashboard        │       │ 🏠 Dashboard        │
│                     │       │                     │
│ 👤 PATIENTS         │       │ 👤 PATIENTS         │
│   All Patients      │       │   All Patients      │
│   Medical History   │       │                     │
│                     │       │ 📅 OPERATIONS       │
│ 📅 OPERATIONS       │       │   Appointments      │
│   Appointments      │       │   Waitlist          │
│   Rooms & Equipment │       │                     │
│   Waitlist          │       │ 💰 SALES            │
│                     │       │   Invoices          │
│ 💰 SALES            │       │   Gift Cards        │
│   Invoices          │       │                     │
│   Packages          │       └─────────────────────┘
│   Gift Cards        │       (no financial, marketing,
│   Memberships       │        HR, settings, reports)
│                     │
│ 📊 ACCOUNTING       │
│   Journal Entries   │
│   Chart of Accounts │
│   Payments          │
│                     │
│ 📦 INVENTORY        │
│   Products          │
│   Purchase Orders   │
│                     │
│ 📣 MARKETING        │
│   WhatsApp          │
│   SMS Campaigns     │
│   Email             │
│                     │
│ 👥 STAFF & HR       │
│   Employees         │
│   Payroll           │
│   Commissions       │
│                     │
│ 📈 REPORTS          │
│   Revenue           │
│   Patients          │
│   Equipment         │
│                     │
│ ⚙️ SETTINGS         │
│   General           │
│   Modules           │
│   Billing & Plan    │
│   Users & Roles     │
└─────────────────────┘
```

---

## 10. Settings Registry — Unified Settings Page

```php
// framework/Core/Settings/SettingsRegistry.php

class SettingsRegistry
{
    protected array $definitions = [];

    /**
     * Register settings from module manifest.
     */
    public function registerFromManifest(ModuleManifest $manifest): void
    {
        foreach ($manifest->settings as $setting) {
            $setting['module'] = $manifest->code;
            $this->definitions[$setting['key']] = $setting;
        }
    }

    /**
     * Get setting value for current tenant.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Reads from tenant_settings table with Redis caching
        return Cache::tags(['tenant', 'settings'])->remember(
            "setting:{$key}",
            3600,
            fn() => TenantSetting::where('key', $key)->value('value') 
                     ?? $this->definitions[$key]['default'] 
                     ?? $default
        );
    }

    /**
     * Build Filament settings page grouped by module.
     * Each active module's settings appear in the unified page.
     */
    public function buildSettingsSchema(): array
    {
        $grouped = collect($this->definitions)
            ->filter(fn($s) => app(ModuleRegistry::class)->isActive($s['module']))
            ->groupBy('group');

        return $grouped->map(function ($settings, $group) {
            return Forms\Components\Section::make(__(ucfirst($group)))
                ->schema(
                    $settings->map(fn($s) => $this->buildField($s))->toArray()
                )
                ->collapsible();
        })->values()->toArray();
    }
}
```

**Result: One settings page with sections from all active modules:**

```
┌─────────────────────────────────────────────────────────┐
│ ⚙️ Settings                                              │
│                                                          │
│ ▼ General                                                │
│   Clinic Name         [XLinic Clinic    ]             │
│   Timezone            [Africa/Cairo      ▼ ]             │
│   Currency            [EGP              ▼  ]             │
│   Default Language    [Arabic           ▼  ]             │
│                                                          │
│ ▼ Booking                    (from Booking module)       │
│   Min Advance Hours   [2     ]                           │
│   Max Advance Days    [60    ]                           │
│   Cancellation Policy [24 hrs]                           │
│   No-Show Fee         [100 EGP]                          │
│                                                          │
│ ▼ Billing                    (from Billing module)       │
│   VAT Rate            [14 %  ]                           │
│   Invoice Prefix      [INV   ]                           │
│   Auto-Generate       [████ ON]                          │
│                                                          │
│ ▼ Sales                      (from GiftCards + Loyalty)  │
│   GC Expiry Days      [365   ]                           │
│   GC Partial Redeem   [████ ON]                          │
│   GC Code Prefix      [GC    ]                           │
│   Points per EGP      [1     ]                           │
│   Points Expiry Days  [365   ]                           │
│                                                          │
│ ▼ Marketing                  (from Marketing modules)    │
│   WhatsApp Provider   [Meta Cloud API  ▼]                │
│   SMS Provider        [Vodafone EG     ▼]                │
│   Reminder Hours      [24, 2 ]                           │
│   Follow-up Days      [3, 7  ]                           │
│                                                          │
│                              [💾 Save Settings]          │
└─────────────────────────────────────────────────────────┘
```

---

## 11. Event System — Cross-Module Communication

```php
// Instead of modules calling each other directly,
// they communicate through events.

// modules/Billing/Events/InvoicePaid.php
class InvoicePaid
{
    public function __construct(
        public Invoice $invoice,
        public Payment $payment,
    ) {}
}

// modules/GiftCards/Listeners/ActivateGiftCardsOnPayment.php
// Only registered if GiftCards module is active
class ActivateGiftCardsOnPayment
{
    public function handle(InvoicePaid $event): void
    {
        if ($event->invoice->type !== 'gift_card_purchase') return;
        // Activate the gift cards linked to this invoice
    }
}

// modules/Accounting/Listeners/CreateJournalOnPayment.php
// Only registered if Accounting module is active
class CreateJournalOnPayment
{
    public function handle(InvoicePaid $event): void
    {
        // Create double-entry journal
    }
}

// modules/Loyalty/Listeners/EarnPointsOnPayment.php
// Only registered if Loyalty module is active
class EarnPointsOnPayment
{
    public function handle(InvoicePaid $event): void
    {
        // Award loyalty points
    }
}

// modules/MarketingWhatsApp/Listeners/SendReceiptOnPayment.php
// Only registered if Marketing WhatsApp module is active
class SendReceiptOnPayment
{
    public function handle(InvoicePaid $event): void
    {
        // Send WhatsApp receipt
    }
}
```

**Visual: How events flow between modules:**

```
                    InvoicePaid Event
                         │
            ┌────────────┼────────────────┬──────────────┐
            ▼            ▼                ▼              ▼
       ┌─────────┐ ┌──────────┐ ┌──────────────┐ ┌──────────┐
       │Accounting│ │Gift Cards│ │   Loyalty    │ │ WhatsApp │
       │         │ │          │ │              │ │          │
       │Create   │ │Activate  │ │Award Points  │ │Send      │
       │Journal  │ │Cards     │ │              │ │Receipt   │
       └─────────┘ └──────────┘ └──────────────┘ └──────────┘
       (if active)  (if active)  (if active)     (if active)

Each listener only runs if its module is active.
No module directly calls another module.
```

---

## 12. Module Boot Sequence

```php
// framework/Core/Module/ModuleManager.php

class ModuleManager
{
    /**
     * Boot sequence — runs on every request.
     */
    public function boot(): void
    {
        // 1. Discover all module manifests
        $manifests = $this->discoverModules(base_path('modules'));

        // 2. Resolve boot order using dependency graph
        $ordered = $this->dependencyResolver->resolve($manifests);

        // 3. Check which modules are active for this tenant
        $activeModules = $this->getActiveTenantModules();

        // 4. Boot each active module in dependency order
        foreach ($ordered as $manifest) {
            if ($manifest->is_core || in_array($manifest->code, $activeModules)) {
                $this->bootModule($manifest);
            }
        }
    }

    protected function bootModule(ModuleManifest $manifest): void
    {
        // Register in module registry
        app(ModuleRegistry::class)->register($manifest);

        // Register model extensions (only if target module is also active)
        foreach ($manifest->extensions['model'] ?? [] as $target => $ext) {
            if ($this->isModuleActiveForModel($target)) {
                app(ModelRegistry::class)->registerExtension($target, $ext);
            }
        }

        // Register view extensions
        app(ViewExtensionManager::class)->registerFromManifest($manifest);

        // Register navigation items
        app(NavigationRegistry::class)->registerFromManifest($manifest);

        // Register settings
        app(SettingsRegistry::class)->registerFromManifest($manifest);

        // Register scheduled actions
        app(ScheduleRegistry::class)->registerFromManifest($manifest);

        // Register permissions
        app(PermissionRegistry::class)->registerFromManifest($manifest);

        // Register event listeners
        foreach ($manifest->listeners as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        // Boot the module's service provider
        app()->register($manifest->getServiceProviderClass());
    }
}
```

---

## 13. HasStateMachine Trait — Odoo-Style Status Bars

```php
// framework/Core/Model/Traits/HasStateMachine.php

trait HasStateMachine
{
    public function transitionTo(string $transition): static
    {
        $machine = $this->stateMachine();
        $field = $machine['field'];
        $currentState = $this->{$field};
        $definition = $machine['transitions'][$transition] ?? null;

        if (! $definition) {
            throw new InvalidTransitionException("Unknown transition: {$transition}");
        }

        if (! in_array($currentState, $definition['from'])) {
            throw new InvalidTransitionException(
                "Cannot {$transition}: current state is {$currentState}, " .
                "expected one of: " . implode(', ', $definition['from'])
            );
        }

        // Run before hook
        $beforeHook = $machine['hooks']["before_{$transition}"] ?? null;
        if ($beforeHook && method_exists($this, $beforeHook)) {
            $this->{$beforeHook}();
        }

        // Perform transition
        $oldState = $currentState;
        $this->{$field} = $definition['to'];
        $this->save();

        // Run after hook
        $afterHook = $machine['hooks']["after_{$transition}"] ?? null;
        if ($afterHook && method_exists($this, $afterHook)) {
            $this->{$afterHook}();
        }

        // Emit event
        event(new StateTransitioned($this, $transition, $oldState, $definition['to']));

        // Log activity
        if (in_array(HasActivity::class, class_uses_recursive($this))) {
            $this->logActivity("Status changed: {$oldState} → {$definition['to']}");
        }

        return $this;
    }

    /**
     * Get available transitions from current state.
     */
    public function availableTransitions(): array
    {
        $machine = $this->stateMachine();
        $currentState = $this->{$machine['field']};

        return collect($machine['transitions'])
            ->filter(fn($def) => in_array($currentState, $def['from']))
            ->keys()
            ->toArray();
    }

    /**
     * Filament status bar component.
     */
    public static function getStatusBarFormComponent(): Forms\Components\Component
    {
        $instance = new static;
        $machine = $instance->stateMachine();

        return Forms\Components\ViewField::make($machine['field'])
            ->view('framework::components.status-bar', [
                'states' => $machine['states'],
                'field'  => $machine['field'],
            ]);
    }
}
```

**Status bar renders like Odoo:**

```
┌────────────────────────────────────────────────────────────┐
│ Gift Card: GC-2024-000042                                  │
│                                                            │
│ ● Draft  ──────  ● Active  ──────  ● Used  ────  ○ Expired│
│                     ▲ current                              │
│                                                            │
│ [Activate]  [Cancel]                                       │
└────────────────────────────────────────────────────────────┘
```

---

## 14. HasActivity Trait — Odoo Chatter

```php
// framework/Core/Model/Traits/HasActivity.php

trait HasActivity
{
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /**
     * Log an activity message (like Odoo's log_note).
     */
    public function logActivity(string $message, ?User $user = null): Activity
    {
        return $this->activities()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'type'    => 'note',
            'body'    => $message,
        ]);
    }

    /**
     * Log a status change with before/after.
     */
    public function logChange(string $field, mixed $old, mixed $new): Activity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'type'    => 'change',
            'body'    => "Changed {$field}: {$old} → {$new}",
            'changes' => ['field' => $field, 'old' => $old, 'new' => $new],
        ]);
    }

    /**
     * Schedule a follow-up activity (like Odoo's planned activities).
     */
    public function scheduleFollowUp(
        string $type,
        string $summary,
        Carbon $dueDate,
        ?User $assignedTo = null
    ): Activity {
        return $this->activities()->create([
            'user_id'     => auth()->id(),
            'assigned_to' => $assignedTo?->id,
            'type'        => 'planned',
            'activity_type' => $type,  // 'call', 'email', 'meeting', 'todo'
            'summary'     => $summary,
            'due_date'    => $dueDate,
            'status'      => 'pending',
        ]);
    }
}
```

**Renders as a chatter at the bottom of every record:**

```
┌──────────────────────────────────────────────────────────┐
│ 💬 Activity                                               │
│                                                           │
│ [📝 Log Note]  [📅 Schedule Activity]  [📧 Send Message] │
│                                                           │
│ ┌─── 📌 Planned Activities ───────────────────────────┐  │
│ │ 📞 Call patient for follow-up  Due: Jan 25  @Sarah  │  │
│ │ [✅ Done]  [❌ Cancel]  [🔄 Reschedule]             │  │
│ └─────────────────────────────────────────────────────┘  │
│                                                           │
│ Today, 2:30 PM — Ahmed (Receptionist)                     │
│   Redeemed EGP 500 from gift card. Remaining: EGP 500.   │
│                                                           │
│ Today, 10:00 AM — System                                  │
│   Status changed: draft → active                          │
│                                                           │
│ Yesterday, 4:15 PM — Fatima (Manager)                     │
│   Gift card issued for VIP patient birthday.              │
│   Value: EGP 1,000. Expires: Dec 31, 2024.               │
└──────────────────────────────────────────────────────────┘
```

---

## 15. HasSequence Trait — Auto-Numbering Like Odoo

```php
// framework/Core/Model/Traits/HasSequence.php

trait HasSequence
{
    protected static function bootHasSequence(): void
    {
        static::creating(function ($model) {
            $field = $model->sequenceField ?? 'code';
            if (empty($model->{$field})) {
                $model->{$field} = app(SequenceService::class)
                    ->next($model->sequenceCode);
            }
        });
    }
}

// framework/Core/Sequence/SequenceService.php

class SequenceService
{
    /**
     * Generate next sequence value with locking.
     * Pattern: {prefix}{YYYY}-{######}
     * Example: INV-2024-000042
     */
    public function next(string $code): string
    {
        return DB::transaction(function () use ($code) {
            $sequence = Sequence::lockForUpdate()->where('code', $code)->first();

            $number = $sequence->next_number;
            $sequence->increment('next_number');

            return $this->format($sequence, $number);
        });
    }

    protected function format(Sequence $sequence, int $number): string
    {
        $pattern = $sequence->pattern;
        // {prefix} → sequence prefix
        // {YYYY}   → current year
        // {MM}     → current month
        // {######} → zero-padded number

        return str_replace(
            ['{prefix}', '{YYYY}', '{MM}', '{######}'],
            [
                $sequence->prefix,
                now()->format('Y'),
                now()->format('m'),
                str_pad($number, $sequence->padding, '0', STR_PAD_LEFT),
            ],
            $pattern
        );
    }
}
```

---

## 16. Report Engine

```php
// framework/Core/Report/BaseReport.php

abstract class BaseReport
{
    abstract public string $code;
    abstract public array $name;       // Translatable
    abstract public string $module;    // Which module owns this
    abstract public string $model;     // Primary model

    /**
     * Available filters for the report.
     */
    abstract public function filters(): array;

    /**
     * Query builder for report data.
     */
    abstract public function query(array $filters): Builder;

    /**
     * Column definitions for display.
     */
    abstract public function columns(): array;

    /**
     * Blade view for PDF rendering.
     */
    abstract public function pdfView(): string;

    /**
     * Built-in grouping options.
     */
    public function groupBy(): array
    {
        return []; // e.g., ['branch', 'practitioner', 'treatment', 'month']
    }

    /**
     * Chart configuration.
     */
    public function charts(): array
    {
        return []; // e.g., ['type' => 'bar', 'x' => 'month', 'y' => 'revenue']
    }
}
```

---

## 17. Complete Framework Boot Flow

```
HTTP Request
    │
    ▼
[Laravel Kernel]
    │
    ▼
[TenantMiddleware] ─── Resolve tenant from subdomain/domain
    │                   Set DB schema, cache prefix, storage path
    ▼
[ModuleManager::boot()]
    │
    ├── 1. Discover all manifests in /modules
    ├── 2. Resolve dependency order (topological sort)
    ├── 3. Load tenant's active modules from tenant_modules
    ├── 4. For each active module (in order):
    │      ├── Register model extensions → ModelRegistry
    │      ├── Register view extensions  → ViewExtensionManager
    │      ├── Register navigation       → NavigationRegistry
    │      ├── Register settings         → SettingsRegistry
    │      ├── Register permissions      → PermissionRegistry
    │      ├── Register schedules        → ScheduleRegistry
    │      ├── Register event listeners  → EventDispatcher
    │      └── Boot service provider     → Container
    │
    ▼
[QuotaMiddleware] ─── Check plan limits (concurrent sessions, etc.)
    │
    ▼
[Filament Panel]
    │
    ├── NavigationRegistry → Build sidebar (module + permission filtered)
    ├── BaseResource → Load form/table with extensions
    ├── BaseWidget → Load dashboard widgets (module filtered)
    └── SettingsPage → Build unified settings (module filtered)
    │
    ▼
[Response] ─── Everything is module-aware, tenant-scoped, and permission-checked
```

---

## 18. Key Differences from Odoo

| Aspect | Odoo | Our Framework |
|--------|------|---------------|
| Language | Python | PHP (Laravel) |
| ORM | Custom Odoo ORM | Eloquent (battle-tested) |
| Views | XML-defined, XPath inheritance | PHP-defined, class-based extensions |
| Frontend | OWL (Odoo Web Library) | Livewire + Alpine (Filament) |
| Deployment | Each customer = separate DB | Schema-per-tenant (more efficient) |
| Module install | DB migration + XML load | Artisan command + seeders |
| Upgrade path | Often breaks on major versions | Laravel ecosystem = stable upgrades |
| Performance | Heavy ORM, slow on large datasets | Eloquent + Redis caching = fast |
| Customization | Override Python methods | Traits, macros, events (cleaner) |
| Multi-tenancy | One DB per customer (heavy) | Schema-per-tenant (lighter) |
| Real-time | Limited (bus) | Laravel Echo + WebSockets |
