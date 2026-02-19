<?php

namespace XLinic\Framework\Core\Filament;

use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Forms\Form;
use XLinic\Framework\Core\View\ViewExtensionManager;
use XLinic\Framework\Core\Security\PermissionRegistry;
use XLinic\Framework\Core\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

abstract class BaseResource extends Resource
{
    /**
     * The resource's navigation icon.
     */
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /**
     * The resource's navigation group.
     */
    protected static ?string $navigationGroup = null;

    /**
     * The resource's navigation sort order.
     */
    protected static ?int $navigationSort = null;

    /**
     * Whether the resource should be tenant scoped.
     */
    protected static bool $isTenantScoped = true;

    /**
     * The permission required to access this resource.
     */
    protected static ?string $permission = null;

    /**
     * The role required to access this resource.
     */
    protected static ?string $role = null;

    /**
     * Whether to apply extensions to this resource.
     */
    protected static bool $applyExtensions = true;

    /**
     * Get the model's Eloquent query builder.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Apply tenant scoping if TenantManager exists
        if (static::$isTenantScoped && class_exists(TenantManager::class)) {
            try {
                if (static::isTenantScopedModel()) {
                    $tenantManager = app(TenantManager::class);
                    $currentTenant = $tenantManager->current();

                    if ($currentTenant) {
                        $query->where('tenant_id', $currentTenant->id);
                    }
                }
            } catch (\Exception $e) {
                // Silently ignore tenant scoping errors
            }
        }

        return $query;
    }

    /**
     * Check if the model is tenant scoped.
     */
    protected static function isTenantScopedModel(): bool
    {
        try {
            $model = static::getModel();
            if (!$model) {
                return false;
            }
            $instance = new $model();

            return method_exists($instance, 'isTenantScoped') && $instance->isTenantScoped();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the current user can access this resource.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check if PermissionRegistry exists
        if (!class_exists(PermissionRegistry::class)) {
            // Fallback: allow access if user is authenticated
            return true;
        }

        try {
            $permissionRegistry = app(PermissionRegistry::class);

            // Check permission if specified
            if (static::$permission) {
                return $permissionRegistry->userCan($user, static::$permission);
            }

            // Check role if specified
            if (static::$role) {
                return $permissionRegistry->userHasRole($user, static::$role);
            }

            // Default permission check based on resource name
            $resourceName = strtolower(class_basename(static::class));
            $defaultPermission = str_replace('resource', '', $resourceName) . '.view';

            return $permissionRegistry->userCan($user, $defaultPermission);
        } catch (\Exception $e) {
            // If permission check fails, allow access for authenticated users
            return true;
        }
    }

    /**
     * Check if the current user can view any records.
     */
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    /**
     * Check if the current user can create records.
     */
    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $permissionRegistry = app(PermissionRegistry::class);
        $resourceName = strtolower(class_basename(static::class));
        $permission = str_replace('resource', '', $resourceName) . '.create';

        return $permissionRegistry->userCan($user, $permission);
    }

    /**
     * Check if the current user can edit records.
     */
    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $permissionRegistry = app(PermissionRegistry::class);
        $resourceName = strtolower(class_basename(static::class));
        $permission = str_replace('resource', '', $resourceName) . '.edit';

        return $permissionRegistry->userCan($user, $permission);
    }

    /**
     * Check if the current user can delete records.
     */
    public static function canDelete($record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $permissionRegistry = app(PermissionRegistry::class);
        $resourceName = strtolower(class_basename(static::class));
        $permission = str_replace('resource', '', $resourceName) . '.delete';

        return $permissionRegistry->userCan($user, $permission);
    }

    /**
     * Configure the resource's form.
     */
    public static function form(Form $form): Form
    {
        $form = static::getFormSchema($form);

        // Apply form extensions
        if (static::$applyExtensions) {
            $extensionManager = app(ViewExtensionManager::class);
            $target = static::class;
            $extensionManager->applyFormExtensions($target, $form);
        }

        return $form;
    }

    /**
     * Configure the resource's table.
     */
    public static function table(Table $table): Table
    {
        $table = static::getTableSchema($table);

        // Apply table extensions
        if (static::$applyExtensions) {
            $extensionManager = app(ViewExtensionManager::class);
            $target = static::class;
            $extensionManager->applyTableExtensions($target, $table);
        }

        return $table;
    }

    /**
     * Get the form schema (can be overridden by subclasses).
     */
    protected static function getFormSchema(Form $form): Form
    {
        return $form;
    }

    /**
     * Get the table schema (can be overridden by subclasses).
     */
    protected static function getTableSchema(Table $table): Table
    {
        return $table;
    }

    /**
     * Get the resource's display name.
     */
    public static function getModelLabel(): string
    {
        return static::$modelLabel ?? static::getDefaultModelLabel();
    }

    /**
     * Get the default model label based on the model class.
     */
    protected static function getDefaultModelLabel(): string
    {
        $model = static::getModel();
        return str_headline(class_basename($model));
    }

    /**
     * Get the resource's plural display name.
     */
    public static function getPluralModelLabel(): string
    {
        return static::$pluralModelLabel ?? str_plural(static::getModelLabel());
    }

    /**
     * Get the resource's navigation badge.
     */
    public static function getNavigationBadge(): ?string
    {
        if (!static::canViewAny()) {
            return null;
        }

        $count = static::getEloquentQuery()->count();
        return $count > 0 ? (string) $count : null;
    }

    /**
     * Get the resource's navigation badge color.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    /**
     * Determine if the navigation item should be active.
     */
    public static function isNavigationActive(): bool
    {
        return request()->routeIs(static::getRouteBaseName() . '.*');
    }

    /**
     * Get additional navigation data.
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        // Add permission-based visibility
        $items = array_filter($items, function ($item) {
            return static::canAccess();
        });

        return $items;
    }

    /**
     * Get global search results.
     */
    public static function getGlobalSearchResults(string $search): Collection
    {
        if (!static::canViewAny()) {
            return collect([]);
        }

        return parent::getGlobalSearchResults($search);
    }

    /**
     * Get global search result title.
     */
    public static function getGlobalSearchResultTitle($record): string
    {
        if (method_exists($record, 'getDisplayName')) {
            return $record->getDisplayName();
        }

        return parent::getGlobalSearchResultTitle($record);
    }

    /**
     * Get global search result details.
     */
    public static function getGlobalSearchResultDetails($record): array
    {
        $details = [];

        if (method_exists($record, 'getTenantId') && $record->getTenantId()) {
            $details['Tenant'] = $record->tenant?->name ?? 'Unknown';
        }

        if (isset($record->created_at)) {
            $details['Created'] = $record->created_at->format('M j, Y');
        }

        return array_merge($details, parent::getGlobalSearchResultDetails($record));
    }

    /**
     * Get the resource's pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecords::route('/'),
            'create' => Pages\CreateRecord::route('/create'),
            'edit' => Pages\EditRecord::route('/{record}/edit'),
        ];
    }

    /**
     * Get common form components for tenant-scoped resources.
     */
    protected static function getTenantFormComponents(): array
    {
        $components = [];

        $tenantManager = app(TenantManager::class);
        $currentTenant = $tenantManager->current();

        if ($currentTenant && auth()->user()?->can('view-all-tenants')) {
            $components[] = \Filament\Forms\Components\Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'name')
                ->default($currentTenant->id)
                ->required()
                ->disabled(!auth()->user()?->can('manage-tenants'));
        }

        return $components;
    }

    /**
     * Get common table columns for tenant-scoped resources.
     */
    protected static function getTenantTableColumns(): array
    {
        $columns = [];

        if (auth()->user()?->can('view-all-tenants')) {
            $columns[] = \Filament\Tables\Columns\TextColumn::make('tenant.name')
                ->label('Tenant')
                ->sortable()
                ->toggleable();
        }

        return $columns;
    }

    /**
     * Get activity log relation for auditable resources.
     */
    protected static function getActivityRelation(): string
    {
        return 'activities';
    }

    /**
     * Get the resource's record title attribute.
     */
    public static function getRecordTitleAttribute(): ?string
    {
        $model = new (static::getModel());

        if (method_exists($model, 'getDisplayName')) {
            return 'display_name';
        }

        return parent::getRecordTitleAttribute();
    }
}