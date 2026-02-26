<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Factory that creates dynamic importer configurations for any model.
 * Used by ImportTableAction to generate import columns automatically.
 */
class DynamicImporterFactory
{
    protected static array $cache = [];

    /**
     * Get or create importer config for a model.
     */
    public static function getConfig(string $modelClass): array
    {
        if (!isset(static::$cache[$modelClass])) {
            static::$cache[$modelClass] = static::analyzeModel($modelClass);
        }

        return static::$cache[$modelClass];
    }

    /**
     * Analyze a model to detect its field types and configuration.
     */
    public static function analyzeModel(string $modelClass): array
    {
        $model = new $modelClass;
        $reflection = new ReflectionClass($model);

        $config = [
            'model' => $modelClass,
            'fillable' => $model->getFillable(),
            'casts' => method_exists($model, 'getCasts') ? $model->getCasts() : [],
            'translatable' => [],
            'relationships' => [],
            'constants' => [],
            'hidden' => method_exists($model, 'getHidden') ? $model->getHidden() : [],
            'uniqueFields' => [],
        ];

        // Detect translatable fields
        if (property_exists($model, 'translatable')) {
            $config['translatable'] = $model->translatable ?? [];
        }

        // Detect relationship fields (ending with _id)
        foreach ($config['fillable'] as $field) {
            if (Str::endsWith($field, '_id') && $field !== 'tenant_id') {
                $relationName = Str::camel(Str::beforeLast($field, '_id'));
                if (method_exists($model, $relationName)) {
                    $config['relationships'][$field] = [
                        'name' => $relationName,
                        'model' => get_class($model->{$relationName}()->getRelated()),
                    ];
                }
            }
        }

        // Detect constants from model
        foreach ($reflection->getConstants() as $name => $value) {
            if (is_array($value)) {
                // Match patterns like TYPES, STATUSES, CATEGORIES, UNITS, GENDERS, etc.
                $patterns = [
                    'TYPES', 'STATUSES', 'CATEGORIES', 'UNITS', 'FORMS', 'METHODS',
                    'GENDERS', 'ROLES', 'MODES', 'LEVELS', 'PRIORITIES', 'CLASSES',
                    'FREQUENCIES', 'ROUTES', 'SOURCES', 'TARGETS', 'ACTIONS', 'STATES',
                    'PAYMENT_METHODS', 'PAYMENT_TERMS', 'ACCOUNT_TYPES', 'JOURNAL_TYPES',
                ];
                foreach ($patterns as $pattern) {
                    if (Str::contains($name, $pattern) || $name === $pattern) {
                        // Try to match to a field name
                        $fieldGuesses = [
                            Str::snake(Str::singular(Str::before($name, '_' . $pattern) ?: $pattern)),
                            Str::snake(Str::singular($pattern)),
                            strtolower(Str::before($name, '_' . $pattern) ?: $pattern),
                            Str::snake($name),
                            Str::snake(Str::singular($name)),
                        ];

                        foreach ($fieldGuesses as $fieldGuess) {
                            if (in_array($fieldGuess, $config['fillable'])) {
                                $config['constants'][$fieldGuess] = $value;
                                break;
                            }
                        }

                        // Also store with original name for lookup
                        $config['constants']['_' . $name] = $value;
                    }
                }
            }
        }

        // Detect unique fields for record resolution
        $uniqueCandidates = [
            'code', 'sku', 'email', 'serial_number', 'slug',
            'brand_name', 'generic_name', 'account_code', 'journal_code',
            'national_id', 'passport_number', 'employee_id', 'student_id',
            'registration_number', 'license_number', 'barcode',
        ];
        foreach ($uniqueCandidates as $field) {
            if (in_array($field, $config['fillable'])) {
                $config['uniqueFields'][] = $field;
            }
        }

        return $config;
    }

    /**
     * Generate import columns for a model.
     */
    public static function getColumns(string $modelClass): array
    {
        $config = static::getConfig($modelClass);
        $columns = [];

        foreach ($config['fillable'] as $field) {
            // Skip tenant_id and hidden fields
            if ($field === 'tenant_id' || in_array($field, $config['hidden'])) {
                continue;
            }

            // Skip auto-generated fields
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // Handle translatable fields - create EN and AR columns
            if (in_array($field, $config['translatable'])) {
                $columns = array_merge($columns, static::createTranslatableColumns($field, $config));
                continue;
            }

            // Handle relationship fields
            if (isset($config['relationships'][$field])) {
                $columns[] = static::createRelationshipColumn($field, $config['relationships'][$field], $config);
                continue;
            }

            // Handle regular fields based on cast type
            $columns[] = static::createColumn($field, $config);
        }

        return $columns;
    }

    /**
     * Create translatable columns (EN and AR).
     */
    protected static function createTranslatableColumns(string $field, array $config): array
    {
        $columns = [];
        $baseLabel = static::getFieldLabel($field);

        foreach (['en', 'ar'] as $lang) {
            $columnName = "{$field}_{$lang}";
            $label = $baseLabel . ' (' . strtoupper($lang) . ')';

            $column = ImportColumn::make($columnName)
                ->label($label)
                ->fillRecordUsing(function ($record, $state) use ($field, $lang) {
                    $translations = $record->{$field} ?? [];
                    if (!is_array($translations)) {
                        $translations = [];
                    }
                    if ($state !== null && $state !== '') {
                        $translations[$lang] = $state;
                    }
                    $record->{$field} = $translations;
                })
                ->rules(['nullable', 'string'])
                ->guess(static::getColumnGuesses($field, $lang));

            // Make EN required for name fields
            if ($lang === 'en' && in_array($field, ['name', 'title'])) {
                $column->requiredMapping();
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Get guesses for auto-mapping column headers.
     */
    protected static function getColumnGuesses(string $field, ?string $lang = null): array
    {
        $guesses = [];
        $baseField = $field;

        // Add the exact field name
        $guesses[] = $field;
        $guesses[] = Str::headline($field);
        $guesses[] = Str::snake($field);
        $guesses[] = Str::camel($field);
        $guesses[] = Str::kebab($field);
        $guesses[] = strtoupper($field);
        $guesses[] = strtolower($field);

        // Language-specific guesses
        if ($lang) {
            $guesses[] = "{$field}_{$lang}";
            $guesses[] = "{$field} ({$lang})";
            $guesses[] = "{$field} (" . strtoupper($lang) . ")";
            $guesses[] = Str::headline($field) . ' (' . strtoupper($lang) . ')';
            $guesses[] = Str::headline($field) . " ({$lang})";

            // Arabic-specific
            if ($lang === 'ar') {
                $guesses[] = "{$field}_arabic";
                $guesses[] = "{$field} arabic";
                $guesses[] = Str::headline($field) . ' (Arabic)';
                $guesses[] = 'arabic ' . $field;
                $guesses[] = 'الاسم'; // Common Arabic field names
                if ($field === 'name') {
                    $guesses[] = 'الاسم بالعربي';
                    $guesses[] = 'الاسم العربي';
                }
            }

            // English-specific
            if ($lang === 'en') {
                $guesses[] = "{$field}_english";
                $guesses[] = "{$field} english";
                $guesses[] = Str::headline($field) . ' (English)';
                $guesses[] = 'english ' . $field;
                if ($field === 'name') {
                    $guesses[] = 'name';
                    $guesses[] = 'Name';
                    $guesses[] = 'NAME';
                }
            }
        }

        // Common variations
        $variations = [
            'name' => ['name', 'product name', 'item name', 'product_name', 'item_name', 'title'],
            'code' => ['code', 'sku', 'item code', 'product code', 'product_code', 'item_code', 'barcode'],
            'description' => ['description', 'desc', 'notes', 'details'],
            'price' => ['price', 'cost', 'amount', 'value', 'sell price', 'selling price'],
            'quantity' => ['quantity', 'qty', 'stock', 'count', 'amount'],
            'category' => ['category', 'type', 'group', 'classification'],
            'email' => ['email', 'e-mail', 'email address', 'mail'],
            'phone' => ['phone', 'telephone', 'mobile', 'cell', 'phone number'],
            'address' => ['address', 'location', 'street'],
            'date' => ['date', 'datetime', 'created', 'updated'],
        ];

        if (isset($variations[$baseField])) {
            $guesses = array_merge($guesses, $variations[$baseField]);
        }

        // Remove _id suffix for relationship fields
        if (Str::endsWith($field, '_id')) {
            $relationField = Str::beforeLast($field, '_id');
            $guesses[] = $relationField;
            $guesses[] = Str::headline($relationField);
        }

        return array_unique(array_filter($guesses));
    }

    /**
     * Create a relationship column.
     */
    protected static function createRelationshipColumn(string $field, array $relationConfig, array $config): ImportColumn
    {
        $label = static::getFieldLabel(Str::beforeLast($field, '_id'));

        return ImportColumn::make($field)
            ->label($label)
            ->fillRecordUsing(function ($record, $state) use ($field, $relationConfig) {
                if ($state === null || $state === '') {
                    return;
                }

                $resolved = static::resolveRelationship($relationConfig['model'], $state);
                if ($resolved) {
                    $record->{$field} = $resolved;
                }
            })
            ->rules(['nullable', 'string'])
            ->guess(static::getColumnGuesses(Str::beforeLast($field, '_id')));
    }

    /**
     * Resolve a relationship value to an ID.
     */
    public static function resolveRelationship(string $relatedModelClass, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $relatedModel = new $relatedModelClass;
        $tenantId = tenant()?->id ?? session('tenant_id');

        $query = $relatedModelClass::query();

        // Apply tenant filter if model has tenant_id
        if (in_array('tenant_id', $relatedModel->getFillable())) {
            $query->where('tenant_id', $tenantId);
        }

        // If value looks like UUID, try direct lookup
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $found = (clone $query)->find($value);
            if ($found) {
                return $found->id;
            }
        }

        // Get translatable fields
        $translatableFields = property_exists($relatedModel, 'translatable')
            ? $relatedModel->translatable
            : [];

        // Try to find by common fields
        $searchFields = ['code', 'name', 'title', 'email', 'sku'];

        foreach ($searchFields as $searchField) {
            if (!in_array($searchField, $relatedModel->getFillable()) && !in_array($searchField, $translatableFields)) {
                continue;
            }

            $searchQuery = clone $query;

            if (in_array($searchField, $translatableFields)) {
                $found = $searchQuery->where(function ($q) use ($searchField, $value) {
                    $q->whereRaw("{$searchField}->>'en' ILIKE ?", ["%{$value}%"])
                      ->orWhereRaw("{$searchField}->>'ar' ILIKE ?", ["%{$value}%"]);
                })->first();
            } else {
                $found = $searchQuery->where($searchField, 'ILIKE', $value)->first();
                if (!$found) {
                    $found = $searchQuery->where($searchField, 'ILIKE', "%{$value}%")->first();
                }
            }

            if ($found) {
                return $found->id;
            }
        }

        return null;
    }

    /**
     * Create a regular column based on field type.
     */
    protected static function createColumn(string $field, array $config): ImportColumn
    {
        $label = static::getFieldLabel($field);
        $cast = $config['casts'][$field] ?? 'string';

        $column = ImportColumn::make($field)
            ->label($label)
            ->guess(static::getColumnGuesses($field));

        // Check if field has constants
        $constants = $config['constants'][$field] ?? null;

        switch (true) {
            case $cast === 'boolean':
                $column->castStateUsing(fn($state) => static::parseBoolean($state));
                break;

            case $cast === 'integer':
                if (Str::endsWith($field, '_minor')) {
                    // Monetary field
                    $column->label(static::getFieldLabel(Str::beforeLast($field, '_minor')));
                    $column->castStateUsing(fn($state) => static::parseMonetary($state));
                } else {
                    $column->castStateUsing(fn($state) => $state !== null && $state !== '' ? (int) $state : null);
                }
                break;

            case Str::startsWith($cast, 'decimal'):
                $column->castStateUsing(fn($state) => $state !== null && $state !== '' ? (float) $state : null);
                break;

            case $cast === 'date':
            case $cast === 'datetime':
                $column->castStateUsing(fn($state) => static::parseDate($state));
                break;

            case $cast === 'array':
            case $cast === 'json':
                $column->castStateUsing(fn($state) => static::parseArray($state));
                break;

            default:
                if ($constants) {
                    $column->castStateUsing(fn($state) => static::resolveConstant($state, $constants));
                }
                break;
        }

        // Mark certain fields as required
        if (in_array($field, ['name', 'code', 'sku', 'first_name', 'last_name'])) {
            $column->requiredMapping();
        }

        return $column;
    }

    /**
     * Get human-readable label for a field.
     */
    protected static function getFieldLabel(string $field): string
    {
        // Check for translation key
        $translationKey = "core::import.fields.{$field}";
        $translated = __($translationKey);

        if ($translated !== $translationKey) {
            return $translated;
        }

        return Str::headline($field);
    }

    /**
     * Parse boolean value.
     */
    public static function parseBoolean(mixed $state, bool $default = false): bool
    {
        if ($state === null || $state === '') {
            return $default;
        }

        if (is_bool($state)) {
            return $state;
        }

        $state = strtolower(trim((string) $state));

        return in_array($state, ['1', 'true', 'yes', 'on', 'y', 'active', 'enabled', 'نعم'], true);
    }

    /**
     * Parse monetary value to minor units.
     */
    public static function parseMonetary(mixed $state): ?int
    {
        if ($state === null || $state === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d.,\-]/', '', (string) $state);

        if (preg_match('/,\d{1,2}$/', $cleaned)) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        $cleaned = str_replace(',', '', $cleaned);

        return (int) round((float) $cleaned * 100);
    }

    /**
     * Parse date value.
     */
    public static function parseDate(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if ($state instanceof \DateTimeInterface) {
            return $state->format('Y-m-d');
        }

        // Handle Excel numeric dates
        if (is_numeric($state) && $state > 25569) {
            $unixTimestamp = ($state - 25569) * 86400;
            return date('Y-m-d', $unixTimestamp);
        }

        $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'd.m.Y'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $state);
            if ($date !== false && $date->format($format) === $state) {
                return $date->format('Y-m-d');
            }
        }

        $timestamp = strtotime($state);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Parse array value.
     */
    public static function parseArray(mixed $state): ?array
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (is_array($state)) {
            return $state;
        }

        $decoded = json_decode($state, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return array_filter(array_map('trim', explode(',', $state)));
    }

    /**
     * Resolve constant value.
     */
    public static function resolveConstant(mixed $state, array $constants): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }

        $state = trim((string) $state);
        $lowerState = strtolower($state);

        // Direct key match
        if (array_key_exists($state, $constants)) {
            return $state;
        }

        // Lowercase key match
        foreach ($constants as $key => $label) {
            if (strtolower((string) $key) === $lowerState) {
                return $key;
            }
            if (strtolower((string) $label) === $lowerState) {
                return $key;
            }
        }

        return $state;
    }
}
