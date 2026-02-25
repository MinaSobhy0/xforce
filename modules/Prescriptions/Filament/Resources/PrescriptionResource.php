<?php

namespace Modules\Prescriptions\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Prescriptions\Filament\Resources\PrescriptionResource\Pages;
use Modules\Prescriptions\Filament\Resources\PrescriptionResource\RelationManagers;
use Modules\Prescriptions\Models\Prescription;
use Modules\Prescriptions\Models\PrescriptionItem;
use Modules\Prescriptions\Models\MedicineCatalog;
use Modules\Prescriptions\Services\PrescriptionPdfService;
use Modules\Patients\Models\Patient;
use Modules\Core\Models\Branch;
use Modules\Auth\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PrescriptionResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Prescription::class;

    protected static ?string $moduleCode = 'prescriptions';

    protected static ?string $permissionKey = 'prescriptions';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 25;

    protected static ?string $recordTitleAttribute = 'prescription_number';

    public static function getNavigationLabel(): string
    {
        return __('prescriptions::prescription.prescriptions');
    }

    public static function getModelLabel(): string
    {
        return __('prescriptions::prescription.prescription');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prescriptions::prescription.prescriptions');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::draft()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('prescriptions::prescription.sections.prescription_details'))
                            ->schema([
                                Forms\Components\Select::make('patient_id')
                                    ->label(__('prescriptions::prescription.fields.patient'))
                                    ->relationship('patient', 'first_name')
                                    ->getOptionLabelFromRecordUsing(fn (Patient $record) => $record->display_name)
                                    ->searchable(['first_name', 'last_name', 'phone', 'code'])
                                    ->preload()
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('branch_id')
                                    ->label(__('prescriptions::prescription.fields.branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->default(fn () => current_branch_id())
                                    ->disabled(fn () => current_branch_id() !== null)
                                    ->dehydrated(),

                                Forms\Components\Select::make('prescriber_id')
                                    ->label(__('prescriptions::prescription.fields.prescriber'))
                                    ->relationship(
                                        'prescriber',
                                        'first_name',
                                        fn (Builder $query) => $query->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'doctor']))
                                    )
                                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                                    ->searchable(['first_name', 'last_name'])
                                    ->preload()
                                    ->required()
                                    ->default(fn () => auth()->id()),

                                Forms\Components\Textarea::make('diagnosis')
                                    ->label(__('prescriptions::prescription.fields.diagnosis'))
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\DatePicker::make('valid_until')
                                    ->label(__('prescriptions::prescription.fields.valid_until'))
                                    ->default(fn () => now()->addDays(config('prescriptions.default_validity_days', 30))),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(__('prescriptions::prescription.sections.medications'))
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\Select::make('medicine_catalog_id')
                                            ->label(__('prescriptions::prescription.catalog.quick_add'))
                                            ->options(function () {
                                                return MedicineCatalog::query()
                                                    ->withSystemMedicines()
                                                    ->active()
                                                    ->orderBy('category')
                                                    ->orderBy('brand_name')
                                                    ->get()
                                                    ->mapWithKeys(fn ($m) => [$m->id => $m->full_name])
                                                    ->toArray();
                                            })
                                            ->searchable()
                                            ->placeholder(__('prescriptions::prescription.catalog.select_medicine'))
                                            ->live()
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if (!$state) return;

                                                $medicine = MedicineCatalog::find($state);
                                                if (!$medicine) return;

                                                $data = $medicine->toPrescriptionItemData();
                                                $set('medication_name', $data['medication_name']);
                                                $set('generic_name', $data['generic_name']);
                                                $set('form', $data['form']);
                                                $set('dosage', $data['dosage']);
                                                $set('dosage_unit', $data['dosage_unit']);
                                                $set('frequency', $data['frequency']);
                                                $set('duration', $data['duration']);
                                                $set('duration_unit', $data['duration_unit']);
                                                $set('route', $data['route']);
                                                $set('instructions', $data['instructions']);
                                            })
                                            ->createOptionForm([
                                                Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.basic_info'))
                                                    ->schema([
                                                        Forms\Components\TextInput::make('brand_name')
                                                            ->label(__('prescriptions::prescription.catalog.fields.brand_name'))
                                                            ->required()
                                                            ->maxLength(255),

                                                        Forms\Components\TextInput::make('generic_name')
                                                            ->label(__('prescriptions::prescription.catalog.fields.generic_name'))
                                                            ->maxLength(255),

                                                        Forms\Components\Select::make('category')
                                                            ->label(__('prescriptions::prescription.catalog.fields.category'))
                                                            ->options(MedicineCatalog::CATEGORIES)
                                                            ->searchable(),

                                                        Forms\Components\Select::make('form')
                                                            ->label(__('prescriptions::prescription.fields.form'))
                                                            ->options(PrescriptionItem::FORMS)
                                                            ->searchable(),
                                                    ])
                                                    ->columns(2),

                                                Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.strength'))
                                                    ->schema([
                                                        Forms\Components\TextInput::make('strength')
                                                            ->label(__('prescriptions::prescription.catalog.fields.strength'))
                                                            ->maxLength(50),

                                                        Forms\Components\Select::make('strength_unit')
                                                            ->label(__('prescriptions::prescription.catalog.fields.strength_unit'))
                                                            ->options(PrescriptionItem::DOSAGE_UNITS),
                                                    ])
                                                    ->columns(2),

                                                Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.default_prescription'))
                                                    ->description(__('prescriptions::prescription.catalog.sections.default_prescription_desc'))
                                                    ->schema([
                                                        Forms\Components\Select::make('default_frequency')
                                                            ->label(__('prescriptions::prescription.fields.frequency'))
                                                            ->options(PrescriptionItem::FREQUENCIES)
                                                            ->searchable(),

                                                        Forms\Components\Select::make('default_route')
                                                            ->label(__('prescriptions::prescription.fields.route'))
                                                            ->options(PrescriptionItem::ROUTES)
                                                            ->searchable(),

                                                        Forms\Components\TextInput::make('default_duration')
                                                            ->label(__('prescriptions::prescription.fields.duration'))
                                                            ->numeric()
                                                            ->minValue(1),

                                                        Forms\Components\Select::make('default_duration_unit')
                                                            ->label(__('prescriptions::prescription.fields.duration_unit'))
                                                            ->options(PrescriptionItem::DURATION_UNITS)
                                                            ->default('days'),

                                                        Forms\Components\Select::make('default_instructions')
                                                            ->label(__('prescriptions::prescription.fields.instructions'))
                                                            ->options(PrescriptionItem::INSTRUCTIONS)
                                                            ->searchable()
                                                            ->columnSpan(2),
                                                    ])
                                                    ->columns(2),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                $medicine = MedicineCatalog::create([
                                                    'brand_name' => $data['brand_name'],
                                                    'generic_name' => $data['generic_name'] ?? null,
                                                    'category' => $data['category'] ?? null,
                                                    'form' => $data['form'] ?? null,
                                                    'strength' => $data['strength'] ?? null,
                                                    'strength_unit' => $data['strength_unit'] ?? null,
                                                    'default_frequency' => $data['default_frequency'] ?? null,
                                                    'default_route' => $data['default_route'] ?? null,
                                                    'default_duration' => $data['default_duration'] ?? null,
                                                    'default_duration_unit' => $data['default_duration_unit'] ?? 'days',
                                                    'default_instructions' => $data['default_instructions'] ?? null,
                                                    'default_dosage' => $data['strength'] ?? null,
                                                    'default_dosage_unit' => $data['strength_unit'] ?? null,
                                                    'is_active' => true,
                                                    'is_system' => false,
                                                ]);

                                                return $medicine->id;
                                            })
                                            ->createOptionModalHeading(__('prescriptions::prescription.catalog.create_medicine'))
                                            ->columnSpanFull()
                                            ->dehydrated(false),

                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('medication_name')
                                                    ->label(__('prescriptions::prescription.fields.medication_name'))
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpan(2),

                                                Forms\Components\TextInput::make('generic_name')
                                                    ->label(__('prescriptions::prescription.fields.generic_name'))
                                                    ->maxLength(255)
                                                    ->columnSpan(2),
                                            ]),

                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Select::make('form')
                                                    ->label(__('prescriptions::prescription.fields.form'))
                                                    ->options(PrescriptionItem::FORMS)
                                                    ->searchable(),

                                                Forms\Components\TextInput::make('dosage')
                                                    ->label(__('prescriptions::prescription.fields.dosage'))
                                                    ->maxLength(50),

                                                Forms\Components\Select::make('dosage_unit')
                                                    ->label(__('prescriptions::prescription.fields.dosage_unit'))
                                                    ->options(PrescriptionItem::DOSAGE_UNITS),

                                                Forms\Components\Select::make('route')
                                                    ->label(__('prescriptions::prescription.fields.route'))
                                                    ->options(PrescriptionItem::ROUTES)
                                                    ->searchable()
                                                    ->default('oral'),
                                            ]),

                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Select::make('frequency')
                                                    ->label(__('prescriptions::prescription.fields.frequency'))
                                                    ->options(PrescriptionItem::FREQUENCIES)
                                                    ->searchable()
                                                    ->required(),

                                                Forms\Components\TextInput::make('duration')
                                                    ->label(__('prescriptions::prescription.fields.duration'))
                                                    ->numeric()
                                                    ->minValue(1),

                                                Forms\Components\Select::make('duration_unit')
                                                    ->label(__('prescriptions::prescription.fields.duration_unit'))
                                                    ->options(PrescriptionItem::DURATION_UNITS)
                                                    ->default('days'),

                                                Forms\Components\TextInput::make('quantity')
                                                    ->label(__('prescriptions::prescription.fields.quantity'))
                                                    ->numeric()
                                                    ->minValue(1),
                                            ]),

                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\Select::make('instructions')
                                                    ->label(__('prescriptions::prescription.fields.instructions'))
                                                    ->options(PrescriptionItem::INSTRUCTIONS)
                                                    ->searchable()
                                                    ->columnSpan(2),

                                                Forms\Components\TextInput::make('refills_allowed')
                                                    ->label(__('prescriptions::prescription.fields.refills'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->columnSpan(2),
                                            ]),

                                        Forms\Components\Textarea::make('special_instructions')
                                            ->label(__('prescriptions::prescription.fields.special_instructions'))
                                            ->rows(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->defaultItems(1)
                                    ->addActionLabel(__('prescriptions::prescription.actions.add_medication'))
                                    ->reorderable()
                                    ->reorderableWithButtons()
                                    ->cloneable()
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['medication_name'] ?? null),
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('prescriptions::prescription.sections.status'))
                            ->schema([
                                Forms\Components\Placeholder::make('prescription_number_display')
                                    ->label(__('prescriptions::prescription.fields.prescription_number'))
                                    ->content(fn (?Prescription $record) => $record?->prescription_number ?? __('prescriptions::prescription.placeholders.auto_generated'))
                                    ->hiddenOn('create'),

                                Forms\Components\Placeholder::make('status_display')
                                    ->label(__('prescriptions::prescription.fields.status'))
                                    ->content(fn (?Prescription $record) => $record?->status_label ?? __('prescriptions::prescription.statuses.draft'))
                                    ->hiddenOn('create'),

                                Forms\Components\Placeholder::make('medication_count')
                                    ->label(__('prescriptions::prescription.fields.medication_count'))
                                    ->content(fn (?Prescription $record) => $record?->medication_count ?? 0)
                                    ->hiddenOn('create'),
                            ]),

                        Forms\Components\Section::make(__('prescriptions::prescription.sections.notes'))
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('prescriptions::prescription.fields.notes'))
                                    ->placeholder(__('prescriptions::prescription.placeholders.additional_instructions'))
                                    ->rows(4),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prescription_number')
                    ->label(__('prescriptions::prescription.fields.prescription_number'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('patient.full_name')
                    ->label(__('prescriptions::prescription.fields.patient'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('prescriber.first_name')
                    ->label(__('prescriptions::prescription.fields.prescriber'))
                    ->formatStateUsing(fn ($record) => $record->prescriber?->first_name . ' ' . $record->prescriber?->last_name)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('diagnosis')
                    ->label(__('prescriptions::prescription.fields.diagnosis'))
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label(__('prescriptions::prescription.fields.medications'))
                    ->counts('items')
                    ->badge()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('prescriptions::prescription.fields.status'))
                    ->colors([
                        'gray' => Prescription::STATUS_DRAFT,
                        'success' => Prescription::STATUS_FINALIZED,
                        'danger' => Prescription::STATUS_CANCELLED,
                    ])
                    ->formatStateUsing(fn (string $state): string => Prescription::STATUSES[$state] ?? $state),

                Tables\Columns\IconColumn::make('is_printed')
                    ->label(__('prescriptions::prescription.fields.printed'))
                    ->boolean()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label(__('prescriptions::prescription.fields.valid_until'))
                    ->date()
                    ->sortable()
                    ->color(fn (Prescription $record) => $record->is_expired ? 'danger' : null),

                Tables\Columns\TextColumn::make('issued_at')
                    ->label(__('prescriptions::prescription.fields.issued_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('prescriptions::prescription.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Prescription::STATUSES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('prescriber_id')
                    ->label(__('prescriptions::prescription.fields.prescriber'))
                    ->relationship('prescriber', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('prescriptions::prescription.fields.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\Filter::make('expired')
                    ->label(__('prescriptions::prescription.filters.expired_only'))
                    ->query(fn (Builder $query) => $query->expired()),

                Tables\Filters\Filter::make('valid')
                    ->label(__('prescriptions::prescription.filters.valid_only'))
                    ->query(fn (Builder $query) => $query->valid()),

                Tables\Filters\Filter::make('issued_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label(__('prescriptions::prescription.filters.from')),
                        Forms\Components\DatePicker::make('until')
                            ->label(__('prescriptions::prescription.filters.until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('issued_at', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('issued_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (Prescription $record) => $record->isEditable()),

                    Tables\Actions\Action::make('finalize')
                        ->label(__('prescriptions::prescription.actions.finalize'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading(__('prescriptions::prescription.modals.finalize_heading'))
                        ->modalDescription(__('prescriptions::prescription.modals.finalize_description'))
                        ->visible(fn (Prescription $record) => $record->isDraft() && $record->items()->count() > 0)
                        ->action(fn (Prescription $record) => $record->finalize()),

                    Tables\Actions\Action::make('print')
                        ->label(__('prescriptions::prescription.actions.print'))
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->visible(fn (Prescription $record) => $record->canPrint())
                        ->url(fn (Prescription $record) => route('prescriptions.print', $record))
                        ->openUrlInNewTab()
                        ->after(fn (Prescription $record) => $record->markPrinted()),

                    Tables\Actions\Action::make('cancel')
                        ->label(__('prescriptions::prescription.actions.cancel'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (Prescription $record) => $record->canTransitionTo(Prescription::STATUS_CANCELLED))
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label(__('prescriptions::prescription.fields.cancellation_reason'))
                                ->required(),
                        ])
                        ->action(fn (Prescription $record, array $data) => $record->cancel($data['reason'])),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => false),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\TextEntry::make('prescription_number')
                            ->label(__('prescriptions::prescription.fields.prescription_number'))
                            ->weight(FontWeight::Bold)
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->copyable(),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => Prescription::STATUSES[$state] ?? $state)
                            ->color(fn (string $state): string => Prescription::STATUS_COLORS[$state] ?? 'gray'),

                        Infolists\Components\IconEntry::make('is_printed')
                            ->label(__('prescriptions::prescription.fields.printed'))
                            ->boolean(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('prescriptions::prescription.sections.patient_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('patient.full_name')
                            ->label(__('prescriptions::prescription.fields.patient')),

                        Infolists\Components\TextEntry::make('patient.phone')
                            ->label(__('prescriptions::prescription.fields.phone')),

                        Infolists\Components\TextEntry::make('patient.age')
                            ->label(__('prescriptions::prescription.fields.age'))
                            ->suffix(' ' . __('prescriptions::prescription.years')),

                        Infolists\Components\TextEntry::make('patient.gender')
                            ->label(__('prescriptions::prescription.fields.gender')),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make(__('prescriptions::prescription.sections.prescription_info'))
                    ->schema([
                        Infolists\Components\TextEntry::make('prescriber.first_name')
                            ->label(__('prescriptions::prescription.fields.prescriber'))
                            ->formatStateUsing(fn ($record) => $record->prescriber?->first_name . ' ' . $record->prescriber?->last_name),

                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('prescriptions::prescription.fields.branch')),

                        Infolists\Components\TextEntry::make('diagnosis')
                            ->label(__('prescriptions::prescription.fields.diagnosis'))
                            ->columnSpanFull()
                            ->placeholder(__('prescriptions::prescription.placeholders.no_diagnosis')),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('prescriptions::prescription.sections.dates'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('prescriptions::prescription.fields.created'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('issued_at')
                            ->label(__('prescriptions::prescription.fields.issued_at'))
                            ->dateTime()
                            ->placeholder(__('prescriptions::prescription.placeholders.not_issued')),

                        Infolists\Components\TextEntry::make('valid_until')
                            ->label(__('prescriptions::prescription.fields.valid_until'))
                            ->date()
                            ->color(fn (Prescription $record) => $record->is_expired ? 'danger' : 'success'),

                        Infolists\Components\TextEntry::make('print_count')
                            ->label(__('prescriptions::prescription.fields.print_count')),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make(__('prescriptions::prescription.sections.notes'))
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->label(__('prescriptions::prescription.fields.notes'))
                            ->placeholder(__('prescriptions::prescription.placeholders.no_notes')),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrescriptions::route('/'),
            'create' => Pages\CreatePrescription::route('/create'),
            'view' => Pages\ViewPrescription::route('/{record}'),
            'edit' => Pages\EditPrescription::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['patient', 'prescriber', 'branch']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('prescriptions::prescription.fields.patient') => $record->patient?->full_name,
            __('prescriptions::prescription.fields.diagnosis') => $record->diagnosis,
        ];
    }
}
