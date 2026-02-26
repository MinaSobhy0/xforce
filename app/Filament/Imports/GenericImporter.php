<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Generic importer that works with any model by auto-detecting fields.
 * Uses DynamicImporterFactory for intelligent column generation.
 */
class GenericImporter extends Importer
{
    protected static ?string $model = null;

    /**
     * Set the model class for this importer.
     */
    public static function setModel(string $modelClass): void
    {
        static::$model = $modelClass;
    }

    /**
     * Get the model class.
     */
    public static function getModel(): string
    {
        return static::$model;
    }

    /**
     * Dynamically generate columns based on model analysis.
     * Delegates to DynamicImporterFactory for intelligent field detection.
     */
    public static function getColumns(): array
    {
        return DynamicImporterFactory::getColumns(static::$model);
    }

    /**
     * Get validation rules for the import data.
     */
    public function getValidationRules(): array
    {
        $config = DynamicImporterFactory::getConfig(static::getModel());
        $rules = [];

        // Add basic validation for required translatable fields
        foreach (['name', 'title'] as $field) {
            if (in_array($field, $config['translatable'])) {
                $rules["{$field}_en"] = ['nullable', 'string', 'max:255'];
            }
        }

        return $rules;
    }

    /**
     * Get validation messages.
     */
    public function getValidationMessages(): array
    {
        return [
            'required' => __('core::import.validation.field_required'),
            'string' => __('core::import.validation.field_string'),
            'max' => __('core::import.validation.field_max'),
        ];
    }

    /**
     * Handle a failed row with proper error reporting.
     */
    public function getFailedRowMessage(string $errorMessage, int $rowNumber): string
    {
        return __('core::import.errors.row_failed', [
            'row' => $rowNumber,
            'message' => $errorMessage,
        ]);
    }

    /**
     * Resolve record - find existing or create new.
     * Uses DynamicImporterFactory config for unique field detection.
     */
    public function resolveRecord(): ?Model
    {
        $model = new (static::getModel())();
        $tenantId = tenant()?->id ?? session('tenant_id');
        $config = DynamicImporterFactory::getConfig(static::getModel());

        // Set tenant_id for new records
        if (in_array('tenant_id', $config['fillable'])) {
            $model->tenant_id = $tenantId;
        }

        // Try to find existing record by unique fields from config
        $uniqueFields = $config['uniqueFields'] ?? ['code', 'sku', 'email', 'serial_number'];

        foreach ($uniqueFields as $field) {
            if (in_array($field, $config['fillable']) && !empty($this->data[$field])) {
                $query = static::getModel()::query();

                if (in_array('tenant_id', $config['fillable'])) {
                    $query->where('tenant_id', $tenantId);
                }

                $existing = $query->where($field, $this->data[$field])->first();

                if ($existing) {
                    return $existing;
                }
            }
        }

        // Also try to find by translatable name fields
        foreach (['name', 'title'] as $translatableField) {
            if (in_array($translatableField, $config['translatable'])) {
                $enValue = $this->data["{$translatableField}_en"] ?? null;
                $arValue = $this->data["{$translatableField}_ar"] ?? null;

                if ($enValue || $arValue) {
                    $query = static::getModel()::query();

                    if (in_array('tenant_id', $config['fillable'])) {
                        $query->where('tenant_id', $tenantId);
                    }

                    $query->where(function ($q) use ($translatableField, $enValue, $arValue) {
                        if ($enValue) {
                            $q->whereRaw("{$translatableField}->>'en' ILIKE ?", [$enValue]);
                        }
                        if ($arValue) {
                            $q->orWhereRaw("{$translatableField}->>'ar' ILIKE ?", [$arValue]);
                        }
                    });

                    $existing = $query->first();
                    if ($existing) {
                        return $existing;
                    }
                }
            }
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
}
