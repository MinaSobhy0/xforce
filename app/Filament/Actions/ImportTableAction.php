<?php

namespace App\Filament\Actions;

use App\Filament\Imports\DynamicImporterFactory;
use App\Models\ImportMapping;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanImportRecords;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\FailedImportRow;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader as CsvReader;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportTableAction extends Action
{
    use CanImportRecords;

    protected ?string $resourceClass = null;

    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    public function resourceClass(?string $resourceClass): static
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('core::import.action_label'));
        $this->icon('heroicon-o-arrow-up-tray');
        $this->color('gray');

        $this->modalHeading(fn(): string => __('core::import.modal.heading', ['label' => $this->getPluralModelLabel()]));
        $this->modalDescription(fn() => $this->getModalAction('downloadExample'));
        $this->modalSubmitActionLabel(__('core::import.modal.actions.import'));
        $this->modalWidth('2xl');

        $this->form(fn(): array => $this->getImportForm());

        $this->registerModalActions([
            Action::make('downloadExample')
                ->label(__('core::import.modal.actions.download_template'))
                ->link()
                ->action(fn() => $this->downloadTemplate()),
        ]);

        // Define the actual import action
        $this->action(function (array $data): void {
            $this->processImport($data);
        });
    }

    /**
     * Process the import with the uploaded file and column mappings.
     */
    protected function processImport(array $data): void
    {
        // Handle different file data structures from Livewire
        $file = $data['file'] ?? null;

        // If file is an array (from Livewire file upload), get the first element
        if (is_array($file)) {
            $file = Arr::first($file);
        }

        // If file is a string path, convert it to TemporaryUploadedFile
        if (is_string($file) && !empty($file)) {
            if (str_contains($file, 'livewire-tmp')) {
                $file = TemporaryUploadedFile::createFromLivewire($file);
            }
        }

        if (!$file instanceof TemporaryUploadedFile) {
            Notification::make()
                ->title(__('core::import.notifications.no_file'))
                ->danger()
                ->send();
            return;
        }

        $columnMap = $data['columnMap'] ?? [];
        $importMode = $data['import_mode'] ?? 'create_and_update';

        // Read data from file
        $rows = $this->readFileData($file);

        if (empty($rows)) {
            Notification::make()
                ->title(__('core::import.notifications.empty_file'))
                ->warning()
                ->send();
            return;
        }

        // Get the importer
        $importerClass = $this->getImporter();
        $modelClass = $importerClass::getModel();
        $config = DynamicImporterFactory::getConfig($modelClass);

        // Create import record
        $import = Import::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $file->getRealPath(),
            'importer' => $importerClass,
            'total_rows' => count($rows),
            'processed_rows' => 0,
            'successful_rows' => 0,
            'user_id' => auth()->id(),
        ]);

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        // Process each row
        foreach ($rows as $rowIndex => $row) {
            try {
                $result = $this->processRow($row, $columnMap, $importMode, $config, $modelClass);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                    if ($result['error']) {
                        $errors[] = [
                            'row' => $rowIndex + 2, // +2 because row 1 is header and index starts at 0
                            'error' => $result['error'],
                        ];

                        // Save failed row
                        FailedImportRow::create([
                            'import_id' => $import->id,
                            'data' => $row,
                            'validation_error' => $result['error'],
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                ];

                Log::error('Import row failed', [
                    'row' => $rowIndex + 2,
                    'error' => $e->getMessage(),
                    'data' => $row,
                ]);

                FailedImportRow::create([
                    'import_id' => $import->id,
                    'data' => $row,
                    'validation_error' => $e->getMessage(),
                ]);
            }
        }

        // Update import record
        $import->update([
            'processed_rows' => count($rows),
            'successful_rows' => $successCount,
            'completed_at' => now(),
        ]);

        // Save mapping if requested
        $this->callAfterAction($data);

        // Show notification with results
        if ($failedCount === 0) {
            Notification::make()
                ->title(__('core::import.notifications.success'))
                ->body(__('core::import.notifications.success_body', [
                    'count' => $successCount,
                ]))
                ->success()
                ->send();
        } elseif ($successCount > 0) {
            Notification::make()
                ->title(__('core::import.notifications.partial_success'))
                ->body(__('core::import.notifications.partial_success_body', [
                    'success' => $successCount,
                    'failed' => $failedCount,
                ]))
                ->warning()
                ->persistent()
                ->send();

            // Show first few errors
            $this->showErrors($errors);
        } else {
            Notification::make()
                ->title(__('core::import.notifications.failed'))
                ->body(__('core::import.notifications.failed_body', [
                    'count' => $failedCount,
                ]))
                ->danger()
                ->persistent()
                ->send();

            $this->showErrors($errors);
        }
    }

    /**
     * Show error notifications for failed rows.
     */
    protected function showErrors(array $errors): void
    {
        $maxErrorsToShow = 5;
        $errorsToShow = array_slice($errors, 0, $maxErrorsToShow);

        foreach ($errorsToShow as $error) {
            Notification::make()
                ->title(__('core::import.notifications.row_error', ['row' => $error['row']]))
                ->body(Str::limit($error['error'], 200))
                ->danger()
                ->send();
        }

        if (count($errors) > $maxErrorsToShow) {
            Notification::make()
                ->title(__('core::import.notifications.more_errors', [
                    'count' => count($errors) - $maxErrorsToShow,
                ]))
                ->warning()
                ->send();
        }
    }

    /**
     * Process a single row of data.
     */
    protected function processRow(array $row, array $columnMap, string $importMode, array $config, string $modelClass): array
    {
        // Map the row data using column mappings
        $mappedData = [];

        foreach ($columnMap as $fieldName => $fileColumn) {
            if (empty($fileColumn)) {
                continue;
            }

            $value = $row[$fileColumn] ?? null;
            if ($value !== null && $value !== '') {
                $mappedData[$fieldName] = $value;
            }
        }

        if (empty($mappedData)) {
            return ['success' => false, 'error' => __('core::import.errors.empty_row')];
        }

        // Process the data using DynamicImporterFactory transformations
        $processedData = $this->processData($mappedData, $config);

        // Find or create record based on import mode
        $record = $this->resolveRecord($processedData, $config, $modelClass, $importMode);

        if ($record === null) {
            return ['success' => false, 'error' => __('core::import.errors.cannot_resolve_record')];
        }

        // Check import mode restrictions
        $isNew = !$record->exists;
        if ($isNew && $importMode === 'update_only') {
            return ['success' => false, 'error' => __('core::import.errors.record_not_found_for_update')];
        }
        if (!$isNew && $importMode === 'create_only') {
            return ['success' => false, 'error' => __('core::import.errors.record_already_exists')];
        }

        // Fill the record with processed data
        $this->fillRecord($record, $processedData, $config);

        // Validate and save
        try {
            $record->save();

            // Sync BelongsToMany relationships after save
            $this->syncBelongsToManyRelationships($record, $processedData, $config);

            return ['success' => true, 'error' => null];
        } catch (\Illuminate\Database\QueryException $e) {
            return ['success' => false, 'error' => $this->parseQueryException($e)];
        }
    }

    /**
     * Sync BelongsToMany relationships for a record.
     */
    protected function syncBelongsToManyRelationships(Model $record, array $data, array $config): void
    {
        foreach ($config['belongsToMany'] as $relationName => $relationConfig) {
            // Check if we have data for this relationship
            if (!isset($data[$relationName]) || $data[$relationName] === null || $data[$relationName] === '') {
                continue;
            }

            // Resolve the values to IDs
            $ids = DynamicImporterFactory::resolveBelongsToManyValues(
                $relationConfig['model'],
                $data[$relationName]
            );

            // Sync the relationship (this will add new ones without removing existing)
            if (!empty($ids) && method_exists($record, $relationName)) {
                $record->{$relationName}()->syncWithoutDetaching($ids);
            }
        }
    }

    /**
     * Process data using DynamicImporterFactory transformations.
     */
    protected function processData(array $data, array $config): array
    {
        $processed = [];

        foreach ($data as $field => $value) {
            // Handle translatable fields (e.g., name_en, name_ar)
            if (preg_match('/^(.+)_(en|ar)$/', $field, $matches)) {
                $baseField = $matches[1];
                $lang = $matches[2];

                if (in_array($baseField, $config['translatable'])) {
                    if (!isset($processed[$baseField])) {
                        $processed[$baseField] = [];
                    }
                    $processed[$baseField][$lang] = $value;
                    continue;
                }
            }

            // Handle BelongsToMany relationships - keep raw value for later sync
            if (isset($config['belongsToMany'][$field])) {
                $processed[$field] = $value; // Keep raw value, will be resolved during sync
                continue;
            }

            // Handle BelongsTo relationship fields
            if (isset($config['relationships'][$field])) {
                $resolved = DynamicImporterFactory::resolveRelationship(
                    $config['relationships'][$field]['model'],
                    $value
                );
                if ($resolved) {
                    $processed[$field] = $resolved;
                }
                continue;
            }

            // Handle casts
            $cast = $config['casts'][$field] ?? 'string';

            switch (true) {
                case $cast === 'boolean':
                    $processed[$field] = DynamicImporterFactory::parseBoolean($value);
                    break;

                case $cast === 'integer':
                    if (Str::endsWith($field, '_minor')) {
                        $processed[$field] = DynamicImporterFactory::parseMonetary($value);
                    } else {
                        $processed[$field] = (int) $value;
                    }
                    break;

                case Str::startsWith($cast, 'decimal'):
                    $processed[$field] = (float) $value;
                    break;

                case $cast === 'date':
                case $cast === 'datetime':
                    $processed[$field] = DynamicImporterFactory::parseDate($value);
                    break;

                case $cast === 'array':
                case $cast === 'json':
                    $processed[$field] = DynamicImporterFactory::parseArray($value);
                    break;

                default:
                    // Check for constants
                    if (isset($config['constants'][$field])) {
                        $processed[$field] = DynamicImporterFactory::resolveConstant($value, $config['constants'][$field]);
                    } else {
                        $processed[$field] = $value;
                    }
                    break;
            }
        }

        return $processed;
    }

    /**
     * Resolve or create a record based on processed data.
     */
    protected function resolveRecord(array $data, array $config, string $modelClass, string $importMode): ?Model
    {
        $tenantId = tenant()?->id ?? session('tenant_id');

        // Try to find existing record by unique fields
        $uniqueFields = $config['uniqueFields'] ?? [];

        foreach ($uniqueFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $query = $modelClass::query();

                if (in_array('tenant_id', $config['fillable'])) {
                    $query->where('tenant_id', $tenantId);
                }

                $existing = $query->where($field, $data[$field])->first();
                if ($existing) {
                    return $existing;
                }
            }
        }

        // Try translatable name/title fields
        foreach (['name', 'title'] as $translatableField) {
            if (in_array($translatableField, $config['translatable']) && isset($data[$translatableField])) {
                $translations = $data[$translatableField];

                if (!is_array($translations)) {
                    continue;
                }

                $query = $modelClass::query();

                if (in_array('tenant_id', $config['fillable'])) {
                    $query->where('tenant_id', $tenantId);
                }

                $query->where(function ($q) use ($translatableField, $translations) {
                    foreach ($translations as $lang => $value) {
                        if (!empty($value)) {
                            $q->orWhereRaw("{$translatableField}->>'$lang' ILIKE ?", [$value]);
                        }
                    }
                });

                $existing = $query->first();
                if ($existing) {
                    return $existing;
                }
            }
        }

        // Create new model instance
        $model = new $modelClass();

        if (in_array('tenant_id', $config['fillable'])) {
            $model->tenant_id = $tenantId;
        }

        return $model;
    }

    /**
     * Fill record with processed data.
     */
    protected function fillRecord(Model $record, array $data, array $config): void
    {
        foreach ($data as $field => $value) {
            // Skip non-fillable fields
            if (!in_array($field, $config['fillable']) && !in_array($field, $config['translatable'])) {
                continue;
            }

            // Handle translatable fields
            if (in_array($field, $config['translatable']) && is_array($value)) {
                $existing = $record->{$field} ?? [];
                if (!is_array($existing)) {
                    $existing = [];
                }
                $record->{$field} = array_merge($existing, $value);
                continue;
            }

            $record->{$field} = $value;
        }
    }

    /**
     * Parse query exception for user-friendly error message.
     */
    protected function parseQueryException(\Illuminate\Database\QueryException $e): string
    {
        $message = $e->getMessage();

        // Check for unique constraint violation
        if (Str::contains($message, ['UNIQUE constraint', 'Duplicate entry', 'unique_violation', 'violates unique constraint'])) {
            preg_match('/Key \(([^)]+)\)/', $message, $matches);
            $field = $matches[1] ?? 'field';
            return __('core::import.errors.duplicate_value', ['field' => $field]);
        }

        // Check for foreign key violation
        if (Str::contains($message, ['FOREIGN KEY constraint', 'foreign key constraint', 'violates foreign key constraint'])) {
            return __('core::import.errors.invalid_relationship');
        }

        // Check for null constraint violation
        if (Str::contains($message, ['NOT NULL constraint', 'null value in column', 'violates not-null constraint'])) {
            preg_match('/column "([^"]+)"/', $message, $matches);
            $field = $matches[1] ?? 'field';
            return __('core::import.errors.required_field', ['field' => Str::headline($field)]);
        }

        Log::error('Import query exception', ['message' => $message]);
        return __('core::import.errors.database_error');
    }

    /**
     * Read data from uploaded file (CSV or Excel).
     */
    protected function readFileData(TemporaryUploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->readExcelData($filePath);
        }

        return $this->readCsvData($file);
    }

    /**
     * Read data from Excel file.
     */
    protected function readExcelData(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $rows = [];
            $headers = [];
            $rowIterator = $worksheet->getRowIterator();

            foreach ($rowIterator as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $rowData = [];
                foreach ($cellIterator as $cell) {
                    $rowData[] = $cell->getValue();
                }

                // First row is headers
                if ($rowIndex === 1) {
                    $headers = array_filter($rowData, fn($v) => $v !== null && $v !== '');
                    continue;
                }

                // Skip empty rows
                $nonEmpty = array_filter($rowData, fn($v) => $v !== null && $v !== '');
                if (empty($nonEmpty)) {
                    continue;
                }

                // Map data to headers
                $mapped = [];
                foreach ($headers as $i => $header) {
                    $mapped[$header] = $rowData[$i] ?? null;
                }

                $rows[] = $mapped;
            }

            return $rows;
        } catch (\Exception $e) {
            Log::error('Excel read error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Read data from CSV file.
     */
    protected function readCsvData(TemporaryUploadedFile $file): array
    {
        try {
            $csvStream = $this->getUploadedFileStream($file);

            if (!$csvStream) {
                return [];
            }

            $csvReader = CsvReader::createFromStream($csvStream);

            if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                $csvReader->setDelimiter($csvDelimiter);
            }

            $csvReader->setHeaderOffset($this->getHeaderOffset() ?? 0);

            $records = [];
            foreach ($csvReader->getRecords() as $record) {
                $records[] = $record;
            }

            return $records;
        } catch (\Exception $e) {
            Log::error('CSV read error', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Extract column headers from uploaded file (CSV or Excel).
     */
    protected function getFileHeaders(TemporaryUploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $filePath = $file->getRealPath();

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->getExcelHeaders($filePath);
        }

        return $this->getCsvHeaders($file);
    }

    /**
     * Get headers from Excel file.
     */
    protected function getExcelHeaders(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $headerRow = $worksheet->getRowIterator(1, 1)->current();
            $cellIterator = $headerRow->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $headers = [];
            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if ($value !== null && $value !== '') {
                    $headers[] = (string) $value;
                }
            }

            return $headers;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get headers from CSV file.
     */
    protected function getCsvHeaders(TemporaryUploadedFile $file): array
    {
        try {
            $csvStream = $this->getUploadedFileStream($file);

            if (!$csvStream) {
                return [];
            }

            $csvReader = CsvReader::createFromStream($csvStream);

            if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                $csvReader->setDelimiter($csvDelimiter);
            }

            $csvReader->setHeaderOffset($this->getHeaderOffset() ?? 0);

            return $csvReader->getHeader();
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getImportForm(): array
    {
        return [
            // Step 1: File Upload
            FileUpload::make('file')
                ->label(__('core::import.modal.form.file.label'))
                ->placeholder(__('core::import.modal.form.file.placeholder'))
                ->acceptedFileTypes([
                    'text/csv',
                    'text/x-csv',
                    'application/csv',
                    'application/x-csv',
                    'text/comma-separated-values',
                    'text/x-comma-separated-values',
                    'text/plain',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->rules(['required', 'extensions:csv,txt,xlsx,xls'])
                ->afterStateUpdated(function (FileUpload $component, Component $livewire, Forms\Set $set, ?TemporaryUploadedFile $state) {
                    if (!$state instanceof TemporaryUploadedFile) {
                        return;
                    }

                    try {
                        $livewire->validateOnly($component->getStatePath());
                    } catch (ValidationException $exception) {
                        $component->state([]);
                        throw $exception;
                    }

                    $fileColumns = $this->getFileHeaders($state);

                    if (empty($fileColumns)) {
                        return;
                    }

                    $lowercaseColumnValues = array_map(Str::lower(...), $fileColumns);
                    $lowercaseColumnKeys = array_combine(
                        $lowercaseColumnValues,
                        $fileColumns,
                    );

                    // Auto-map columns based on name similarity
                    $set('columnMap', array_reduce(
                        $this->getImporter()::getColumns(),
                        function (array $carry, ImportColumn $column) use ($lowercaseColumnKeys, $lowercaseColumnValues) {
                            $carry[$column->getName()] = $lowercaseColumnKeys[
                                Arr::first(
                                    array_intersect(
                                        $lowercaseColumnValues,
                                        $column->getGuesses(),
                                    ),
                                )
                            ] ?? null;

                            return $carry;
                        },
                        []
                    ));
                })
                ->storeFiles(false)
                ->visibility('private')
                ->required()
                ->live(),

            // Import Mode Selection
            Radio::make('import_mode')
                ->label(__('core::import.modal.form.import_mode.label'))
                ->options([
                    'create_only' => __('core::import.modal.form.import_mode.options.create_only'),
                    'create_and_update' => __('core::import.modal.form.import_mode.options.create_and_update'),
                    'update_only' => __('core::import.modal.form.import_mode.options.update_only'),
                ])
                ->default('create_and_update')
                ->inline()
                ->visible(fn(Forms\Get $get): bool => Arr::first((array)($get('file') ?? [])) instanceof TemporaryUploadedFile),

            // Saved Mappings Selector
            Select::make('saved_mapping_id')
                ->label(__('core::import.modal.form.saved_mapping.label'))
                ->placeholder(__('core::import.modal.form.saved_mapping.placeholder'))
                ->options(fn() => $this->getSavedMappingsOptions())
                ->afterStateUpdated(function (Forms\Set $set, $state) {
                    if (!$state) {
                        return;
                    }

                    $mapping = ImportMapping::find($state);
                    if ($mapping && is_array($mapping->column_map)) {
                        $set('columnMap', $mapping->column_map);
                    }
                })
                ->live()
                ->visible(fn(Forms\Get $get): bool => Arr::first((array)($get('file') ?? [])) instanceof TemporaryUploadedFile),

            // Column Mapping Fieldset
            Fieldset::make(__('core::import.modal.form.columns.label'))
                ->columns(1)
                ->inlineLabel()
                ->schema(function (Forms\Get $get): array {
                    $file = Arr::first((array)($get('file') ?? []));

                    if (!$file instanceof TemporaryUploadedFile) {
                        return [];
                    }

                    $fileColumns = $this->getFileHeaders($file);

                    if (empty($fileColumns)) {
                        return [];
                    }

                    $columnOptions = array_combine($fileColumns, $fileColumns);

                    return array_map(
                        fn(ImportColumn $column): Select => $column->getSelect()
                            ->options(['' => __('core::import.modal.form.skip_column')] + $columnOptions)
                            ->label($column->getLabel() . ($column->isMappingRequired() ? ' *' : '')),
                        $this->getImporter()::getColumns(),
                    );
                })
                ->statePath('columnMap')
                ->visible(fn(Forms\Get $get): bool => Arr::first((array)($get('file') ?? [])) instanceof TemporaryUploadedFile),

            // Save Mapping Option
            Checkbox::make('save_mapping')
                ->label(__('core::import.modal.form.save_mapping.label'))
                ->live()
                ->visible(fn(Forms\Get $get): bool => Arr::first((array)($get('file') ?? [])) instanceof TemporaryUploadedFile),

            TextInput::make('mapping_name')
                ->label(__('core::import.modal.form.mapping_name.label'))
                ->placeholder(__('core::import.modal.form.mapping_name.placeholder'))
                ->required(fn(Forms\Get $get): bool => (bool)$get('save_mapping'))
                ->visible(fn(Forms\Get $get): bool => (bool)$get('save_mapping') && Arr::first((array)($get('file') ?? [])) instanceof TemporaryUploadedFile),

            // Additional options from the importer
            ...$this->getImporter()::getOptionsFormComponents(),
        ];
    }

    protected function getSavedMappingsOptions(): array
    {
        $tenantId = tenant()?->id ?? session('tenant_id');
        $resourceClass = $this->getResourceClass();

        if (!$resourceClass) {
            return [];
        }

        return ImportMapping::query()
            ->forResource($resourceClass)
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhere('user_id', auth()->id());
            })
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $columns = $this->getImporter()::getColumns();

        $csv = \League\Csv\Writer::createFromFileObject(new \SplTempFileObject);
        $csv->setOutputBOM(\League\Csv\Bom::Utf8);

        if (filled($csvDelimiter = $this->getCsvDelimiter())) {
            $csv->setDelimiter($csvDelimiter);
        }

        $csv->insertOne(array_map(
            fn(ImportColumn $column): string => $column->getExampleHeader(),
            $columns,
        ));

        $columnExamples = array_map(
            fn(ImportColumn $column): array => $column->getExamples(),
            $columns,
        );

        $exampleRowsCount = array_reduce(
            $columnExamples,
            fn(int $count, array $exampleData): int => max($count, count($exampleData)),
            initial: 0,
        );

        $exampleRows = [];

        foreach ($columnExamples as $exampleData) {
            for ($i = 0; $i < $exampleRowsCount; $i++) {
                $exampleRows[$i][] = $exampleData[$i] ?? '';
            }
        }

        $csv->insertAll($exampleRows);

        $filename = Str::slug($this->getPluralModelLabel()) . '-import-template.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv->toString();
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getPluralModelLabel(): string
    {
        if ($this->getResourceClass()) {
            return $this->getResourceClass()::getPluralModelLabel();
        }

        return Str::plural(
            Str::headline(
                class_basename($this->getImporter()::getModel())
            )
        );
    }

    /**
     * Override the action to add mapping saving logic.
     */
    public function callAfterAction(array $data): void
    {
        // Save mapping if requested
        if (!empty($data['save_mapping']) && !empty($data['mapping_name']) && !empty($data['columnMap'])) {
            $tenantId = tenant()?->id ?? session('tenant_id');

            ImportMapping::create([
                'tenant_id' => $tenantId,
                'user_id' => auth()->id(),
                'resource_class' => $this->getResourceClass(),
                'name' => $data['mapping_name'],
                'column_map' => $data['columnMap'],
                'options' => Arr::except($data, ['file', 'columnMap', 'save_mapping', 'mapping_name', 'saved_mapping_id']),
            ]);

            Notification::make()
                ->title(__('core::import.notifications.mapping_saved'))
                ->success()
                ->send();
        }
    }
}
