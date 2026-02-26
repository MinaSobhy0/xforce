<?php

namespace App\Filament\Actions;

use App\Models\ImportMapping;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Concerns\CanImportRecords;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Csv\Reader as CsvReader;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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
                ->rules(['required', 'extensions:csv,txt,xlsx'])
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

                    $csvStream = $this->getUploadedFileStream($state);

                    if (!$csvStream) {
                        return;
                    }

                    $csvReader = CsvReader::createFromStream($csvStream);

                    if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                        $csvReader->setDelimiter($csvDelimiter);
                    }

                    $csvReader->setHeaderOffset($this->getHeaderOffset() ?? 0);

                    $csvColumns = $csvReader->getHeader();

                    $lowercaseCsvColumnValues = array_map(Str::lower(...), $csvColumns);
                    $lowercaseCsvColumnKeys = array_combine(
                        $lowercaseCsvColumnValues,
                        $csvColumns,
                    );

                    // Auto-map columns based on name similarity
                    $set('columnMap', array_reduce(
                        $this->getImporter()::getColumns(),
                        function (array $carry, ImportColumn $column) use ($lowercaseCsvColumnKeys, $lowercaseCsvColumnValues) {
                            $carry[$column->getName()] = $lowercaseCsvColumnKeys[
                                Arr::first(
                                    array_intersect(
                                        $lowercaseCsvColumnValues,
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
                    $csvFile = Arr::first((array)($get('file') ?? []));

                    if (!$csvFile instanceof TemporaryUploadedFile) {
                        return [];
                    }

                    $csvStream = $this->getUploadedFileStream($csvFile);

                    if (!$csvStream) {
                        return [];
                    }

                    $csvReader = CsvReader::createFromStream($csvStream);

                    if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                        $csvReader->setDelimiter($csvDelimiter);
                    }

                    $csvReader->setHeaderOffset($this->getHeaderOffset() ?? 0);

                    $csvColumns = $csvReader->getHeader();
                    $csvColumnOptions = array_combine($csvColumns, $csvColumns);

                    return array_map(
                        fn(ImportColumn $column): Select => $column->getSelect()
                            ->options(['' => __('core::import.modal.form.skip_column')] + $csvColumnOptions)
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
