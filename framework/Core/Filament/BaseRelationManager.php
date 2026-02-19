<?php

namespace XLinic\Framework\Core\Filament;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Forms\Form;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use XLinic\Framework\Core\Security\RecordPolicyEngine;
use XLinic\Framework\Core\Security\FieldAccess;
use XLinic\Framework\Core\Tenancy\TenantManager;

/**
 * Base Relation Manager
 *
 * Extended relation manager for Filament with built-in security,
 * tenant awareness, and framework integration. Provides common
 * functionality for all relation managers in the system.
 *
 * @package XLinic\Framework\Core\Filament
 */
abstract class BaseRelationManager extends RelationManager
{
    /**
     * The record policy engine
     */
    protected RecordPolicyEngine $policyEngine;

    /**
     * The field access control
     */
    protected FieldAccess $fieldAccess;

    /**
     * The tenant manager
     */
    protected TenantManager $tenantManager;

    /**
     * Whether to apply tenant filtering
     */
    protected bool $applyTenantFiltering = true;

    /**
     * Whether to apply security policies
     */
    protected bool $applySecurityPolicies = true;

    /**
     * Whether to apply field access control
     */
    protected bool $applyFieldAccessControl = true;

    /**
     * Default table configuration
     */
    protected bool $allowsDuplicates = false;

    /**
     * Boot the relation manager
     */
    public function boot(): void
    {
        parent::boot();

        // Inject dependencies
        $this->policyEngine = app(RecordPolicyEngine::class);
        $this->fieldAccess = app(FieldAccess::class);
        $this->tenantManager = app(TenantManager::class);
    }

    /**
     * Configure the relation manager table
     */
    public function table(Table $table): Table
    {
        $table = parent::table($table);

        // Apply default configurations
        $table = $this->configureDefaultTable($table);

        // Apply security configurations
        $table = $this->configureTableSecurity($table);

        // Apply tenant configurations
        $table = $this->configureTableTenancy($table);

        return $table;
    }

    /**
     * Configure the relation manager form
     */
    public function form(Form $form): Form
    {
        $form = parent::form($form);

        // Apply field access control
        if ($this->applyFieldAccessControl) {
            $form = $this->applyFormFieldAccess($form);
        }

        return $form;
    }

    /**
     * Modify the relation query
     */
    protected function modifyTableQuery(Builder $query): Builder
    {
        $query = parent::modifyTableQuery($query);

        // Apply tenant filtering
        if ($this->applyTenantFiltering) {
            $query = $this->applyTenantFilter($query);
        }

        // Apply security policies
        if ($this->applySecurityPolicies) {
            $query = $this->applySecurityPoliciesFilter($query);
        }

        return $query;
    }

    /**
     * Configure default table settings
     */
    protected function configureDefaultTable(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultPaginationPageOption(25)
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession();
    }

    /**
     * Configure table security
     */
    protected function configureTableSecurity(Table $table): Table
    {
        // Configure record access
        $table = $table->recordCheckboxSelection(
            fn (Model $record) => $this->canSelectRecord($record)
        );

        // Configure bulk actions based on permissions
        if (!$this->canDeleteAny()) {
            $table = $table->selectCurrentPageOnly();
        }

        return $table;
    }

    /**
     * Configure table tenancy
     */
    protected function configureTableTenancy(Table $table): Table
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if ($tenant) {
            // Add tenant context to table state
            $table = $table->persistFiltersInSession()
                ->sessionKey("tenant.{$tenant->id}.relation.{$this->getRelationshipName()}");
        }

        return $table;
    }

    /**
     * Apply tenant filtering to query
     */
    protected function applyTenantFilter(Builder $query): Builder
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if (!$tenant) {
            return $query;
        }

        $model = $query->getModel();

        // Check if model is tenant-aware
        if (method_exists($model, 'getTenantColumn')) {
            $tenantColumn = $model->getTenantColumn();
            return $query->where($tenantColumn, $tenant->id);
        }

        // Check for standard tenant column
        if ($query->getModel()->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'tenant_id')) {
            return $query->where('tenant_id', $tenant->id);
        }

        return $query;
    }

    /**
     * Apply security policies to query
     */
    protected function applySecurityPoliciesFilter(Builder $query): Builder
    {
        return $this->policyEngine->applyToQuery($query);
    }

    /**
     * Apply field access control to form
     */
    protected function applyFormFieldAccess(Form $form): Form
    {
        $user = auth()->user();
        if (!$user) {
            return $form;
        }

        // Get model instance for field access checking
        $model = $this->getRelatedModel();

        // Filter form components based on field access
        $components = $form->getComponents();
        $filteredComponents = [];

        foreach ($components as $component) {
            if ($this->canAccessFormField($component, $model, $user)) {
                $filteredComponents[] = $component;
            }
        }

        return $form->schema($filteredComponents);
    }

    /**
     * Check if user can access form field
     */
    protected function canAccessFormField(mixed $component, Model $model, mixed $user): bool
    {
        // Get field name from component
        $fieldName = $this->getFieldNameFromComponent($component);

        if (!$fieldName) {
            return true; // Allow non-field components
        }

        // Check field access
        return $this->fieldAccess->canWrite($model, $fieldName, $user);
    }

    /**
     * Get field name from form component
     */
    protected function getFieldNameFromComponent(mixed $component): ?string
    {
        if (method_exists($component, 'getName')) {
            return $component->getName();
        }

        if (method_exists($component, 'getStatePath')) {
            return $component->getStatePath();
        }

        return null;
    }

    /**
     * Check if user can select record
     */
    protected function canSelectRecord(Model $record): bool
    {
        return $this->policyEngine->canAccess($record);
    }

    /**
     * Get the related model instance
     */
    protected function getRelatedModel(): Model
    {
        $relationshipName = $this->getRelationshipName();
        $ownerRecord = $this->getOwnerRecord();

        return $ownerRecord->{$relationshipName}()->getRelated();
    }

    /**
     * Check if user can view any records
     */
    public function canViewAny(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check general relation access permission
        if (method_exists($this, 'getViewAnyPermission')) {
            $permission = $this->getViewAnyPermission();
            if ($permission && !$user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user can view specific record
     */
    public function canView(Model $record): bool
    {
        return $this->policyEngine->canAccess($record);
    }

    /**
     * Check if user can create records
     */
    public function canCreate(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check create permission
        if (method_exists($this, 'getCreatePermission')) {
            $permission = $this->getCreatePermission();
            if ($permission && !$user->can($permission)) {
                return false;
            }
        }

        // Check if user can create related model
        $relatedModel = $this->getRelatedModel();
        return $user->can('create', $relatedModel);
    }

    /**
     * Check if user can edit record
     */
    public function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check record access first
        if (!$this->policyEngine->canAccess($record)) {
            return false;
        }

        // Check edit permission
        return $user->can('update', $record);
    }

    /**
     * Check if user can delete record
     */
    public function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check record access first
        if (!$this->policyEngine->canAccess($record)) {
            return false;
        }

        // Check delete permission
        return $user->can('delete', $record);
    }

    /**
     * Check if user can delete any records
     */
    public function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check general delete permission
        if (method_exists($this, 'getDeleteAnyPermission')) {
            $permission = $this->getDeleteAnyPermission();
            if ($permission && !$user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle record creation
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Apply tenant context to data
        $data = $this->applyTenantContextToData($data);

        // Apply user context to data
        $data = $this->applyUserContextToData($data);

        // Create the record
        return parent::handleRecordCreation($data);
    }

    /**
     * Handle record update
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Filter data based on field access
        $data = $this->filterDataByFieldAccess($record, $data);

        // Update the record
        return parent::handleRecordUpdate($record, $data);
    }

    /**
     * Apply tenant context to data
     */
    protected function applyTenantContextToData(array $data): array
    {
        $tenant = $this->tenantManager->getCurrentTenant();

        if (!$tenant) {
            return $data;
        }

        $model = $this->getRelatedModel();

        // Add tenant ID if model supports it
        if (method_exists($model, 'getTenantColumn')) {
            $tenantColumn = $model->getTenantColumn();
            $data[$tenantColumn] = $tenant->id;
        } elseif ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'tenant_id')) {
            $data['tenant_id'] = $tenant->id;
        }

        return $data;
    }

    /**
     * Apply user context to data
     */
    protected function applyUserContextToData(array $data): array
    {
        $user = auth()->user();

        if (!$user) {
            return $data;
        }

        $model = $this->getRelatedModel();

        // Add user ID for trackable models
        if (method_exists($model, 'getCreatedByColumn')) {
            $createdByColumn = $model->getCreatedByColumn();
            $data[$createdByColumn] = $user->id;
        } elseif ($model->getConnection()->getSchemaBuilder()->hasColumn($model->getTable(), 'created_by')) {
            $data['created_by'] = $user->id;
        }

        return $data;
    }

    /**
     * Filter data by field access permissions
     */
    protected function filterDataByFieldAccess(Model $record, array $data): array
    {
        $user = auth()->user();

        if (!$user || !$this->applyFieldAccessControl) {
            return $data;
        }

        $filteredData = [];

        foreach ($data as $field => $value) {
            if ($this->fieldAccess->canWrite($record, $field, $user)) {
                $filteredData[$field] = $value;
            }
        }

        return $filteredData;
    }

    /**
     * Get default permissions for relation manager
     */
    protected function getDefaultPermissions(): array
    {
        $relationshipName = $this->getRelationshipName();
        $modelName = str_replace('_', '', $relationshipName);

        return [
            'viewAny' => "view_{$modelName}",
            'view' => "view_{$modelName}",
            'create' => "create_{$modelName}",
            'update' => "update_{$modelName}",
            'delete' => "delete_{$modelName}",
            'deleteAny' => "delete_{$modelName}",
        ];
    }

    /**
     * Log relation manager activity
     */
    protected function logActivity(string $action, Model $record, array $data = []): void
    {
        logger()->info('Relation manager activity', [
            'relation_manager' => static::class,
            'relationship' => $this->getRelationshipName(),
            'action' => $action,
            'record_id' => $record->getKey(),
            'record_class' => get_class($record),
            'user_id' => auth()->id(),
            'tenant_id' => $this->tenantManager->getCurrentTenant()?->id,
            'data' => $data,
        ]);
    }

    /**
     * Get breadcrumb for relation manager
     */
    public function getBreadcrumb(): string
    {
        $relationshipName = $this->getRelationshipName();
        return str($relationshipName)->headline()->toString();
    }

    /**
     * Get navigation badge
     */
    public function getBadge(): ?string
    {
        try {
            $count = $this->getTableQuery()->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Set tenant filtering
     */
    public function setApplyTenantFiltering(bool $apply): static
    {
        $this->applyTenantFiltering = $apply;
        return $this;
    }

    /**
     * Set security policies
     */
    public function setApplySecurityPolicies(bool $apply): static
    {
        $this->applySecurityPolicies = $apply;
        return $this;
    }

    /**
     * Set field access control
     */
    public function setApplyFieldAccessControl(bool $apply): static
    {
        $this->applyFieldAccessControl = $apply;
        return $this;
    }
}