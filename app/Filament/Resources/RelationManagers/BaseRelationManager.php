<?php

namespace App\Filament\Resources\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base RelationManager with lazy loading disabled and auto ViewAction support.
 *
 * Features:
 * - Auto-detects if related model has a Resource with view page
 * - Automatically adds ViewAction to table actions
 * - No configuration needed in most cases
 *
 * Usage:
 * 1. Extend this class instead of RelationManager
 * 2. Define your table() method as usual
 * 3. ViewAction will be auto-added if a Resource exists for the related model
 *
 * To explicitly set the resource (for non-standard naming):
 *   protected static ?string $viewResource = MyResource::class;
 *
 * To disable auto ViewAction:
 *   protected static bool $autoViewAction = false;
 */
abstract class BaseRelationManager extends RelationManager
{
    /**
     * Disable lazy loading so relation manager data loads with the page.
     */
    protected static bool $isLazy = false;

    /**
     * Enable/disable automatic ViewAction injection.
     */
    protected static bool $autoViewAction = true;

    /**
     * Optional: Explicitly set the resource class for viewing records.
     * If not set, will try to auto-detect based on the related model.
     */
    protected static ?string $viewResource = null;

    /**
     * Cache for discovered resource classes.
     */
    protected static array $resourceCache = [];

    /**
     * Get the table with automatic ViewAction injection.
     */
    public function table(Table $table): Table
    {
        // Let child class configure the table first
        $table = parent::table($table);

        // Auto-inject ViewAction if enabled
        if (static::$autoViewAction) {
            $table = $this->injectViewAction($table);
        }

        return $table;
    }

    /**
     * Inject ViewAction into the table if resource has a view page.
     */
    protected function injectViewAction(Table $table): Table
    {
        $resource = $this->resolveViewResource();

        if (!$resource || !$this->resourceHasViewPage($resource)) {
            return $table;
        }

        $existingActions = $table->getActions();
        $hasViewAction = collect($existingActions)->contains(
            fn ($action) => $action->getName() === 'view'
        );

        if (!$hasViewAction) {
            $viewAction = Tables\Actions\ViewAction::make()
                ->url(fn (Model $record) => $this->getRecordViewUrl($record, $resource));

            $table->actions([
                $viewAction,
                ...$existingActions,
            ]);
        }

        return $table;
    }

    /**
     * Resolve the resource class for viewing records.
     */
    protected function resolveViewResource(): ?string
    {
        // Use explicitly set resource
        if (static::$viewResource) {
            return static::$viewResource;
        }

        // Try to auto-detect
        try {
            $relationshipName = static::getRelationshipName();
            $relatedModel = $this->getOwnerRecord()->{$relationshipName}()->getRelated();
            $modelClass = get_class($relatedModel);

            return $this->discoverResourceForModel($modelClass);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Discover the Filament resource for a given model class.
     */
    protected function discoverResourceForModel(string $modelClass): ?string
    {
        if (isset(static::$resourceCache[$modelClass])) {
            return static::$resourceCache[$modelClass];
        }

        $modelName = class_basename($modelClass);

        // Extract module name from model namespace
        $moduleName = null;
        if (preg_match('/Modules\\\\([^\\\\]+)\\\\/', $modelClass, $matches)) {
            $moduleName = $matches[1];
        }

        // Try various resource naming patterns
        $candidates = [];

        if ($moduleName) {
            // Standard pattern: Model -> ModelResource
            $candidates[] = "Modules\\{$moduleName}\\Filament\\Resources\\{$modelName}Resource";
            // Singular pattern: Models -> ModelResource
            $candidates[] = "Modules\\{$moduleName}\\Filament\\Resources\\" . Str::singular($modelName) . "Resource";
        }

        // App-level resources
        $candidates[] = "App\\Filament\\Resources\\{$modelName}Resource";
        $candidates[] = "App\\Filament\\Tenant\\Resources\\{$modelName}Resource";

        foreach ($candidates as $resourceClass) {
            if (class_exists($resourceClass) && is_subclass_of($resourceClass, Resource::class)) {
                static::$resourceCache[$modelClass] = $resourceClass;
                return $resourceClass;
            }
        }

        static::$resourceCache[$modelClass] = null;
        return null;
    }

    /**
     * Check if a resource has a view page.
     */
    protected function resourceHasViewPage(string $resourceClass): bool
    {
        try {
            $pages = $resourceClass::getPages();
            return isset($pages['view']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the URL for viewing a record.
     */
    protected function getRecordViewUrl(Model $record, string $resourceClass): ?string
    {
        try {
            return $resourceClass::getUrl('view', ['record' => $record]);
        } catch (\Exception $e) {
            return null;
        }
    }
}
