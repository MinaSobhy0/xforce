<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

abstract class BaseImporter extends Importer
{
    /**
     * Column(s) used to identify existing records for updates.
     * Can be a string for single column or array for composite keys.
     */
    protected static string|array $resolveRecordUsing = [];

    /**
     * Whether tenant isolation should be applied.
     */
    protected static bool $tenantAware = true;

    /**
     * The column name for tenant isolation.
     */
    protected static string $tenantColumn = 'tenant_id';

    /**
     * Get the tenant ID for the current context.
     */
    protected function getTenantId(): ?string
    {
        return tenant()?->id ?? session('tenant_id');
    }

    /**
     * Resolve an existing record or create a new one.
     */
    public function resolveRecord(): ?Model
    {
        $resolveUsing = static::$resolveRecordUsing;

        // If no resolution columns specified, create new record
        if (empty($resolveUsing)) {
            return $this->createNewRecord();
        }

        $columns = is_array($resolveUsing) ? $resolveUsing : [$resolveUsing];
        $query = static::getModel()::query();

        // Apply tenant isolation
        if (static::$tenantAware && $tenantId = $this->getTenantId()) {
            $query->where(static::$tenantColumn, $tenantId);
        }

        // Build the where clause for resolution columns
        $hasAllResolveValues = true;
        foreach ($columns as $column) {
            $columnName = $this->columnMap[$column] ?? $column;
            $value = $this->data[$column] ?? $this->data[$columnName] ?? null;

            if ($value === null || $value === '') {
                $hasAllResolveValues = false;
                continue;
            }

            $query->where($column, $value);
        }

        // If we don't have values for all resolution columns, create new
        if (!$hasAllResolveValues) {
            return $this->createNewRecord();
        }

        // Try to find existing record
        $existingRecord = $query->first();

        if ($existingRecord) {
            return $existingRecord;
        }

        return $this->createNewRecord();
    }

    /**
     * Create a new model instance with tenant ID.
     */
    protected function createNewRecord(): Model
    {
        $model = new (static::getModel());

        // Set tenant ID for new records
        if (static::$tenantAware && $tenantId = $this->getTenantId()) {
            $model->{static::$tenantColumn} = $tenantId;
        }

        return $model;
    }

    /**
     * Get the completed notification body.
     */
    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('core::import.notifications.completed_body', [
            'count' => number_format($import->successful_rows),
            'total' => number_format($import->total_rows),
        ]);

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . __('core::import.notifications.failed_rows', [
                'count' => number_format($failedRowsCount),
            ]);
        }

        return $body;
    }

    /**
     * Get the label for the importer.
     */
    public static function getLabel(): string
    {
        $modelLabel = static::getModelLabel();

        return __('core::import.label', ['model' => $modelLabel]);
    }

    /**
     * Get the model label.
     */
    public static function getModelLabel(): string
    {
        return str(class_basename(static::getModel()))
            ->headline()
            ->toString();
    }

    /**
     * Get columns as an associative array for mapping UI.
     */
    public static function getColumnsForMapping(): array
    {
        $result = [];

        foreach (static::getColumns() as $column) {
            $result[$column->getName()] = [
                'name' => $column->getName(),
                'label' => $column->getLabel(),
                'required' => $column->isRequired(),
            ];
        }

        return $result;
    }

    /**
     * Transform data before validation.
     * Override in subclasses for custom transformations.
     */
    protected function beforeValidate(): void
    {
        // Subclasses can override
    }

    /**
     * Hook called after a record is created.
     */
    protected function afterCreate(): void
    {
        // Subclasses can override
    }

    /**
     * Hook called after a record is updated.
     */
    protected function afterUpdate(): void
    {
        // Subclasses can override
    }
}
