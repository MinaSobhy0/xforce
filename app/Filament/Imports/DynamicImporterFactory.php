<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

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
            'belongsToMany' => [],
            'constants' => [],
            'hidden' => method_exists($model, 'getHidden') ? $model->getHidden() : [],
            'uniqueFields' => [],
            'requiredFields' => static::detectRequiredFields($model),
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

        // Detect BelongsToMany relationships
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip magic methods and common non-relationship methods
            if (Str::startsWith($method->getName(), '__') ||
                in_array($method->getName(), ['getKey', 'getTable', 'getFillable', 'getCasts', 'getHidden', 'toArray', 'toJson'])) {
                continue;
            }

            // Check if method returns BelongsToMany
            try {
                if ($method->getNumberOfParameters() === 0) {
                    $returnType = $method->getReturnType();
                    if ($returnType && $returnType->getName() === BelongsToMany::class) {
                        $relation = $model->{$method->getName()}();
                        if ($relation instanceof BelongsToMany) {
                            $relatedModel = $relation->getRelated();
                            $config['belongsToMany'][$method->getName()] = [
                                'name' => $method->getName(),
                                'model' => get_class($relatedModel),
                                'table' => $relation->getTable(),
                                'foreignPivotKey' => $relation->getForeignPivotKeyName(),
                                'relatedPivotKey' => $relation->getRelatedPivotKeyName(),
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Skip methods that throw errors
                continue;
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

        // Fields to allow even if hidden (needed for import)
        $allowHidden = ['password'];

        foreach ($config['fillable'] as $field) {
            // Skip tenant_id
            if ($field === 'tenant_id') {
                continue;
            }

            // Skip hidden fields except those in allowHidden list
            if (in_array($field, $config['hidden']) && !in_array($field, $allowHidden)) {
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

        // Add BelongsToMany relationship columns
        foreach ($config['belongsToMany'] as $relationName => $relationConfig) {
            $columns[] = static::createBelongsToManyColumn($relationName, $relationConfig, $config);
        }

        return $columns;
    }

    /**
     * Create a BelongsToMany relationship column.
     * Accepts comma-separated values that will be resolved to IDs.
     */
    protected static function createBelongsToManyColumn(string $relationName, array $relationConfig, array $config): ImportColumn
    {
        $label = static::getFieldLabel($relationName);

        return ImportColumn::make($relationName)
            ->label($label)
            ->fillRecordUsing(function ($record, $state) use ($relationName, $relationConfig) {
                // Don't fill - BelongsToMany is handled after save via sync
                // Store the raw value for later processing
                if ($state !== null && $state !== '') {
                    $record->_importBelongsToMany[$relationName] = [
                        'value' => $state,
                        'config' => $relationConfig,
                    ];
                }
            })
            ->rules(['nullable', 'string'])
            ->guess(static::getBelongsToManyGuesses($relationName))
            ->example('Value1, Value2, Value3');
    }

    /**
     * Get guesses for BelongsToMany column headers.
     */
    protected static function getBelongsToManyGuesses(string $relationName): array
    {
        $guesses = [];

        // Add variations of the relation name
        $guesses[] = $relationName;
        $guesses[] = Str::headline($relationName);
        $guesses[] = Str::snake($relationName);
        $guesses[] = Str::kebab($relationName);
        $guesses[] = strtolower($relationName);
        $guesses[] = strtoupper($relationName);

        // Add singular/plural variations
        $singular = Str::singular($relationName);
        $plural = Str::plural($relationName);
        $guesses[] = $singular;
        $guesses[] = $plural;
        $guesses[] = Str::headline($singular);
        $guesses[] = Str::headline($plural);

        // Common naming patterns
        $guesses[] = Str::headline($relationName) . 's';
        $guesses[] = Str::snake($relationName) . '_list';
        $guesses[] = Str::snake($relationName) . '_names';

        return array_unique(array_filter($guesses));
    }

    /**
     * Resolve multiple values for a BelongsToMany relationship.
     * Returns an array of IDs.
     */
    public static function resolveBelongsToManyValues(string $relatedModelClass, mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        // Parse comma-separated or pipe-separated values
        $values = is_array($value)
            ? $value
            : array_filter(array_map('trim', preg_split('/[,|;]/', (string) $value)));

        $ids = [];
        foreach ($values as $singleValue) {
            $resolved = static::resolveRelationship($relatedModelClass, $singleValue);
            if ($resolved) {
                $ids[] = $resolved;
            }
        }

        return array_unique($ids);
    }

    /**
     * Create translatable columns (EN and AR).
     */
    protected static function createTranslatableColumns(string $field, array $config): array
    {
        $columns = [];
        $baseLabel = static::getFieldLabel($field);
        $requiredFields = $config['requiredFields'] ?? [];

        foreach (['en', 'ar'] as $lang) {
            $columnName = "{$field}_{$lang}";
            $label = $baseLabel . ' (' . strtoupper($lang) . ')';
            $isRequired = in_array($columnName, $requiredFields);

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

            // Mark as required if detected
            if ($isRequired) {
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

        $value = trim((string) $value);
        $relatedModel = new $relatedModelClass;
        $tenantId = tenant()?->id ?? session('tenant_id');

        $query = $relatedModelClass::query();

        // Get fillable fields - if empty, try to get actual table columns
        // This handles models that use $guarded instead of $fillable (like Spatie's Role)
        $fillable = $relatedModel->getFillable();
        if (empty($fillable)) {
            try {
                $columns = $relatedModel->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($relatedModel->getTable());
                $fillable = $columns;
            } catch (\Exception $e) {
                $fillable = [];
            }
        }

        // Apply tenant filter if model has tenant_id AND we have a valid tenant_id
        // Skip if null since PostgreSQL schema-based isolation already scopes the data
        if (in_array('tenant_id', $fillable) && $tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        // If value looks like UUID, try direct lookup
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $found = (clone $query)->find($value);
            if ($found) {
                return $found->id;
            }
        }

        // If value looks like an integer ID, try direct lookup
        if (is_numeric($value) && (int) $value > 0) {
            $found = (clone $query)->find((int) $value);
            if ($found) {
                return $found->id;
            }
        }

        // Get translatable fields
        $translatableFields = property_exists($relatedModel, 'translatable')
            ? $relatedModel->translatable
            : [];

        // First, try exact match on identifier fields (code, sku, email, employee_number)
        $identifierFields = ['code', 'sku', 'email', 'employee_number', 'national_id', 'username'];
        foreach ($identifierFields as $searchField) {
            if (!in_array($searchField, $fillable)) {
                continue;
            }

            $searchQuery = clone $query;
            $found = $searchQuery->where($searchField, 'ILIKE', $value)->first();
            if ($found) {
                return $found->id;
            }
        }

        // Try to find by name fields (translatable or regular)
        $nameFields = ['name', 'title', 'full_name', 'display_name'];
        foreach ($nameFields as $searchField) {
            if (!in_array($searchField, $fillable) && !in_array($searchField, $translatableFields)) {
                continue;
            }

            $searchQuery = clone $query;

            if (in_array($searchField, $translatableFields)) {
                // Exact match first
                $found = $searchQuery->where(function ($q) use ($searchField, $value) {
                    $q->whereRaw("{$searchField}->>'en' ILIKE ?", [$value])
                      ->orWhereRaw("{$searchField}->>'ar' ILIKE ?", [$value]);
                })->first();

                if (!$found) {
                    // Then partial match
                    $searchQuery = clone $query;
                    $found = $searchQuery->where(function ($q) use ($searchField, $value) {
                        $q->whereRaw("{$searchField}->>'en' ILIKE ?", ["%{$value}%"])
                          ->orWhereRaw("{$searchField}->>'ar' ILIKE ?", ["%{$value}%"]);
                    })->first();
                }
            } else {
                $found = $searchQuery->where($searchField, 'ILIKE', $value)->first();
                if (!$found) {
                    $searchQuery = clone $query;
                    $found = $searchQuery->where($searchField, 'ILIKE', "%{$value}%")->first();
                }
            }

            if ($found) {
                return $found->id;
            }
        }

        // For staff/user models, try to find by combining first_name + last_name
        if (in_array('first_name', $fillable) && in_array('last_name', $fillable)) {
            $searchQuery = clone $query;
            // Use TRIM to handle extra spaces and normalize the comparison
            $found = $searchQuery->whereRaw("TRIM(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) ILIKE ?", ["%{$value}%"])->first();
            if ($found) {
                return $found->id;
            }
            // Also try matching just first_name or last_name
            $searchQuery = clone $query;
            $found = $searchQuery->where(function ($q) use ($value) {
                $q->whereRaw("TRIM(first_name) ILIKE ?", ["%{$value}%"])
                  ->orWhereRaw("TRIM(last_name) ILIKE ?", ["%{$value}%"]);
            })->first();
            if ($found) {
                return $found->id;
            }
        }

        // For models with user relation (like StaffProfile), try to search through the user
        if (in_array('user_id', $fillable) && method_exists($relatedModel, 'user')) {
            $searchQuery = clone $query;
            $found = $searchQuery->whereHas('user', function ($q) use ($value) {
                $q->where('email', 'ILIKE', $value)
                  ->orWhereRaw("TRIM(CONCAT(TRIM(first_name), ' ', TRIM(last_name))) ILIKE ?", ["%{$value}%"])
                  ->orWhereRaw("TRIM(first_name) ILIKE ?", ["%{$value}%"])
                  ->orWhereRaw("TRIM(last_name) ILIKE ?", ["%{$value}%"]);
            })->first();
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
        $isRequired = in_array($field, $config['requiredFields'] ?? []);

        $column = ImportColumn::make($field)
            ->label($label)
            ->guess(static::getColumnGuesses($field));

        // Mark as required if detected
        if ($isRequired) {
            $column->requiredMapping();
        }

        // Special handling for password field - hash it
        if ($field === 'password') {
            $column->fillRecordUsing(function ($record, $state) {
                if ($state !== null && $state !== '') {
                    $record->password = \Illuminate\Support\Facades\Hash::make($state);
                }
            });
            return $column;
        }

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
     * Detect required fields from model based on database schema.
     */
    protected static function detectRequiredFields(Model $model): array
    {
        $required = [];
        $fillable = $model->getFillable();
        $translatable = property_exists($model, 'translatable') ? ($model->translatable ?? []) : [];

        // Skip these fields - they are auto-managed
        $skipFields = [
            'id', 'tenant_id', 'branch_id', 'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by', 'password', 'remember_token',
        ];

        // Skip auto-generated sequence column (protected property, use reflection)
        if (property_exists($model, 'sequenceColumn')) {
            try {
                $reflection = new \ReflectionClass($model);
                $prop = $reflection->getProperty('sequenceColumn');
                $prop->setAccessible(true);
                $skipFields[] = $prop->getValue($model);
            } catch (\Exception $e) {
                // Ignore if can't access
            }
        }

        try {
            // Get database schema information
            $connection = $model->getConnection();
            $table = $model->getTable();
            $columns = $connection->getSchemaBuilder()->getColumns($table);

            foreach ($columns as $column) {
                $columnName = $column['name'];

                // Skip if not fillable or in skip list
                if (!in_array($columnName, $fillable) || in_array($columnName, $skipFields)) {
                    continue;
                }

                // Skip translatable fields (handled separately)
                if (in_array($columnName, $translatable)) {
                    continue;
                }

                // Check if column is NOT NULL and has no default
                $isNotNull = !$column['nullable'];
                $hasDefault = $column['default'] !== null;

                if ($isNotNull && !$hasDefault) {
                    $required[] = $columnName;
                }
            }
        } catch (\Exception $e) {
            // Fallback to common required fields if schema detection fails
            // Note: 'code' and 'sku' removed as they may be auto-generated
            $commonRequired = [
                'name', 'email', 'first_name', 'last_name',
                'title', 'username', 'brand_name', 'generic_name',
            ];

            foreach ($fillable as $field) {
                if (in_array($field, $skipFields)) {
                    continue;
                }
                if (in_array($field, $commonRequired)) {
                    $required[] = $field;
                }
            }
        }

        // For translatable fields, at least one language should be required if field is required
        foreach ($translatable as $field) {
            if (in_array($field, ['name', 'title'])) {
                $required[] = "{$field}_en"; // At least English is required
            }
        }

        return array_unique($required);
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

    /**
     * Parse enum value (case-insensitive).
     */
    public static function parseEnum(mixed $state, string $enumClass): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }

        $state = trim((string) $state);

        // Try exact match first
        foreach ($enumClass::cases() as $case) {
            if ($case->value === $state || $case->name === $state) {
                return $case->value;
            }
        }

        // Try case-insensitive match
        $lowerState = strtolower($state);
        foreach ($enumClass::cases() as $case) {
            if (strtolower($case->value) === $lowerState || strtolower($case->name) === $lowerState) {
                return $case->value;
            }
        }

        // Try matching by label if the enum has a label method
        if (method_exists($enumClass, 'label')) {
            foreach ($enumClass::cases() as $case) {
                if (strtolower($case->label()) === $lowerState) {
                    return $case->value;
                }
            }
        }

        // Return lowercase as fallback (most enums use lowercase values)
        return strtolower($state);
    }
}
