<?php

namespace Modules\Patients\Filament\Resources\PatientResource\RelationManagers;

use Modules\Patients\Models\PatientAmrTest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;

class AmrTestsRelationManager extends RelationManager
{
    protected static string $relationship = 'amrTests';

    protected static ?string $title = 'AMR Tests';

    protected static ?string $icon = 'heroicon-o-beaker';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section 1: Specimen Information
                Forms\Components\Section::make(__('patients::amr.specimen_information'))
                    ->schema([
                        Forms\Components\DatePicker::make('collection_date')
                            ->label(__('patients::amr.collection_date'))
                            ->required()
                            ->default(now())
                            ->native(false),

                        Forms\Components\DatePicker::make('result_date')
                            ->label(__('patients::amr.result_date'))
                            ->native(false),

                        Forms\Components\Select::make('specimen_source')
                            ->label(__('patients::amr.specimen_source'))
                            ->options(PatientAmrTest::SPECIMEN_SOURCES)
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('specimen_site')
                            ->label(__('patients::amr.specimen_site'))
                            ->placeholder(__('patients::amr.specimen_site_placeholder'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('lab_accession_number')
                            ->label(__('patients::amr.lab_accession_number'))
                            ->maxLength(100),

                        Forms\Components\TextInput::make('laboratory_name')
                            ->label(__('patients::amr.laboratory_name'))
                            ->maxLength(255),
                    ])
                    ->columns(3),

                // Section 2: Organism Identification
                Forms\Components\Section::make(__('patients::amr.organism_identification'))
                    ->schema([
                        Forms\Components\Select::make('organism_name')
                            ->label(__('patients::amr.organism_name'))
                            ->options(PatientAmrTest::COMMON_ORGANISMS)
                            ->required()
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('organism_name')
                                    ->label(__('patients::amr.custom_organism'))
                                    ->required(),
                            ])
                            ->createOptionUsing(fn (array $data) => $data['organism_name']),

                        Forms\Components\TextInput::make('organism_code')
                            ->label(__('patients::amr.organism_code'))
                            ->maxLength(50)
                            ->placeholder('e.g., STAAU'),

                        Forms\Components\Toggle::make('is_mdro')
                            ->label(__('patients::amr.is_mdro'))
                            ->helperText(__('patients::amr.is_mdro_help'))
                            ->live()
                            ->default(false),

                        Forms\Components\CheckboxList::make('mdro_types')
                            ->label(__('patients::amr.mdro_types'))
                            ->options(PatientAmrTest::MDRO_TYPES)
                            ->columns(3)
                            ->visible(fn (Get $get) => $get('is_mdro')),
                    ])
                    ->columns(2),

                // Section 3: Antibiotic Sensitivity Results
                Forms\Components\Section::make(__('patients::amr.antibiotic_results'))
                    ->schema([
                        Forms\Components\Repeater::make('antibiotic_results')
                            ->label('')
                            ->schema([
                                Forms\Components\Select::make('antibiotic')
                                    ->label(__('patients::amr.antibiotic'))
                                    ->options(PatientAmrTest::getAntibioticOptions())
                                    ->required()
                                    ->searchable()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('sensitivity')
                                    ->label(__('patients::amr.sensitivity'))
                                    ->options(PatientAmrTest::SENSITIVITY_LEVELS)
                                    ->required(),

                                Forms\Components\TextInput::make('mic')
                                    ->label(__('patients::amr.mic'))
                                    ->numeric()
                                    ->placeholder('e.g., 32'),

                                Forms\Components\Select::make('mic_unit')
                                    ->label(__('patients::amr.mic_unit'))
                                    ->options([
                                        'mcg/ml' => 'mcg/ml',
                                        'mg/L' => 'mg/L',
                                    ])
                                    ->default('mcg/ml'),
                            ])
                            ->columns(5)
                            ->itemLabel(fn (array $state): ?string =>
                                isset($state['antibiotic'])
                                    ? PatientAmrTest::getAntibioticLabel($state['antibiotic']) . ' - ' . ($state['sensitivity'] ?? 'N/A')
                                    : null
                            )
                            ->collapsible()
                            ->defaultItems(0)
                            ->addActionLabel(__('patients::amr.add_antibiotic_result')),
                    ]),

                // Section 4: Clinical Notes
                Forms\Components\Section::make(__('patients::amr.clinical_information'))
                    ->schema([
                        Forms\Components\Textarea::make('clinical_notes')
                            ->label(__('patients::amr.clinical_notes'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('recommendations')
                            ->label(__('patients::amr.recommendations'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('organism_name')
            ->defaultSort('collection_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('collection_date')
                    ->label(__('patients::amr.date'))
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('specimen_source')
                    ->label(__('patients::amr.specimen'))
                    ->formatStateUsing(fn ($state) => PatientAmrTest::SPECIMEN_SOURCES[$state] ?? $state)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('organism_name')
                    ->label(__('patients::amr.organism'))
                    ->searchable()
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_mdro')
                    ->label(__('patients::amr.mdro'))
                    ->boolean()
                    ->trueIcon('heroicon-s-exclamation-triangle')
                    ->trueColor('danger')
                    ->falseIcon(''),

                Tables\Columns\TextColumn::make('resistant_antibiotics')
                    ->label(__('patients::amr.resistant'))
                    ->badge()
                    ->color('danger')
                    ->formatStateUsing(function ($record) {
                        $resistant = $record->resistant_antibiotics;
                        if (empty($resistant)) return null;
                        return collect($resistant)
                            ->take(3)
                            ->map(fn ($a) => PatientAmrTest::getAntibioticLabel($a))
                            ->join(', ') . (count($resistant) > 3 ? ' +' . (count($resistant) - 3) : '');
                    })
                    ->wrap(),

                Tables\Columns\TextColumn::make('sensitive_antibiotics')
                    ->label(__('patients::amr.sensitive'))
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(function ($record) {
                        $sensitive = $record->sensitive_antibiotics;
                        if (empty($sensitive)) return null;
                        return collect($sensitive)
                            ->take(3)
                            ->map(fn ($a) => PatientAmrTest::getAntibioticLabel($a))
                            ->join(', ') . (count($sensitive) > 3 ? ' +' . (count($sensitive) - 3) : '');
                    })
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label(__('patients::amr.verified'))
                    ->boolean()
                    ->trueIcon('heroicon-s-check-badge')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-clock')
                    ->falseColor('warning'),

                Tables\Columns\TextColumn::make('laboratory_name')
                    ->label(__('patients::amr.laboratory'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('specimen_source')
                    ->label(__('patients::amr.specimen'))
                    ->options(PatientAmrTest::SPECIMEN_SOURCES),

                Tables\Filters\TernaryFilter::make('is_mdro')
                    ->label(__('patients::amr.mdro_only')),

                Tables\Filters\TernaryFilter::make('verified')
                    ->label(__('patients::amr.verified'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('verified_at'),
                        false: fn ($query) => $query->whereNull('verified_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->label(__('patients::amr.verify'))
                    ->visible(fn ($record) => !$record->is_verified)
                    ->requiresConfirmation()
                    ->modalHeading(__('patients::amr.verify_test'))
                    ->modalDescription(__('patients::amr.verify_description'))
                    ->action(function ($record) {
                        $record->verify(auth()->id());
                        Notification::make()
                            ->success()
                            ->title(__('patients::amr.test_verified'))
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
