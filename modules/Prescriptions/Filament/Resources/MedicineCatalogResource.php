<?php

namespace Modules\Prescriptions\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Modules\Prescriptions\Filament\Resources\MedicineCatalogResource\Pages;
use Modules\Prescriptions\Models\MedicineCatalog;
use Modules\Prescriptions\Models\PrescriptionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MedicineCatalogResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = MedicineCatalog::class;

    protected static ?string $moduleCode = 'prescriptions';

    protected static ?string $permissionKey = 'medicine_catalog';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 26;

    protected static ?string $recordTitleAttribute = 'brand_name';

    public static function getNavigationLabel(): string
    {
        return __('prescriptions::prescription.medicine_catalog');
    }

    public static function getModelLabel(): string
    {
        return __('prescriptions::prescription.medicine');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prescriptions::prescription.medicines');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.basic_info'))
                            ->schema([
                                Forms\Components\TextInput::make('brand_name')
                                    ->label(__('prescriptions::prescription.catalog.fields.brand_name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('generic_name')
                                    ->label(__('prescriptions::prescription.catalog.fields.generic_name'))
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('manufacturer')
                                    ->label(__('prescriptions::prescription.catalog.fields.manufacturer'))
                                    ->maxLength(255),

                                Forms\Components\Select::make('category')
                                    ->label(__('prescriptions::prescription.catalog.fields.category'))
                                    ->options(MedicineCatalog::CATEGORIES)
                                    ->searchable(),

                                Forms\Components\TextInput::make('drug_class')
                                    ->label(__('prescriptions::prescription.catalog.fields.drug_class'))
                                    ->maxLength(255),

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
                                Forms\Components\TextInput::make('default_dosage')
                                    ->label(__('prescriptions::prescription.fields.dosage'))
                                    ->maxLength(50),

                                Forms\Components\Select::make('default_dosage_unit')
                                    ->label(__('prescriptions::prescription.fields.dosage_unit'))
                                    ->options(PrescriptionItem::DOSAGE_UNITS),

                                Forms\Components\Select::make('default_frequency')
                                    ->label(__('prescriptions::prescription.fields.frequency'))
                                    ->options(PrescriptionItem::FREQUENCIES)
                                    ->searchable(),

                                Forms\Components\TextInput::make('default_duration')
                                    ->label(__('prescriptions::prescription.fields.duration'))
                                    ->numeric()
                                    ->minValue(1),

                                Forms\Components\Select::make('default_duration_unit')
                                    ->label(__('prescriptions::prescription.fields.duration_unit'))
                                    ->options(PrescriptionItem::DURATION_UNITS)
                                    ->default('days'),

                                Forms\Components\Select::make('default_route')
                                    ->label(__('prescriptions::prescription.fields.route'))
                                    ->options(PrescriptionItem::ROUTES)
                                    ->searchable(),

                                Forms\Components\Select::make('default_instructions')
                                    ->label(__('prescriptions::prescription.fields.instructions'))
                                    ->options(PrescriptionItem::INSTRUCTIONS)
                                    ->searchable(),
                            ])
                            ->columns(2)
                            ->collapsible(),

                        Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.clinical_info'))
                            ->schema([
                                Forms\Components\Textarea::make('description')
                                    ->label(__('prescriptions::prescription.catalog.fields.description'))
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\Textarea::make('indications')
                                    ->label(__('prescriptions::prescription.catalog.fields.indications'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('contraindications')
                                    ->label(__('prescriptions::prescription.catalog.fields.contraindications'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('side_effects')
                                    ->label(__('prescriptions::prescription.catalog.fields.side_effects'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('warnings')
                                    ->label(__('prescriptions::prescription.catalog.fields.warnings'))
                                    ->rows(2),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('prescriptions::prescription.catalog.sections.status'))
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('prescriptions::prescription.catalog.fields.is_active'))
                                    ->default(true),

                                Forms\Components\Toggle::make('requires_prescription')
                                    ->label(__('prescriptions::prescription.catalog.fields.requires_prescription'))
                                    ->default(true),

                                Forms\Components\Toggle::make('is_controlled')
                                    ->label(__('prescriptions::prescription.catalog.fields.is_controlled'))
                                    ->default(false)
                                    ->helperText(__('prescriptions::prescription.catalog.fields.is_controlled_help')),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('brand_name')
                    ->label(__('prescriptions::prescription.catalog.fields.brand_name'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('generic_name')
                    ->label(__('prescriptions::prescription.catalog.fields.generic_name'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('strength_display')
                    ->label(__('prescriptions::prescription.catalog.fields.strength'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('form_label')
                    ->label(__('prescriptions::prescription.fields.form'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('category_label')
                    ->label(__('prescriptions::prescription.catalog.fields.category'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('frequency_label')
                    ->label(__('prescriptions::prescription.fields.frequency'))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_controlled')
                    ->label(__('prescriptions::prescription.catalog.fields.is_controlled'))
                    ->boolean()
                    ->trueIcon('heroicon-o-shield-exclamation')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('prescriptions::prescription.catalog.fields.is_active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('prescriptions::prescription.catalog.fields.is_system'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->trueColor('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('prescriptions::prescription.fields.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('prescriptions::prescription.catalog.fields.category'))
                    ->options(MedicineCatalog::CATEGORIES)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('form')
                    ->label(__('prescriptions::prescription.fields.form'))
                    ->options(PrescriptionItem::FORMS)
                    ->multiple(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('prescriptions::prescription.catalog.fields.is_active')),

                Tables\Filters\TernaryFilter::make('is_controlled')
                    ->label(__('prescriptions::prescription.catalog.fields.is_controlled')),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('prescriptions::prescription.catalog.fields.is_system')),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->visible(fn (MedicineCatalog $record) => $record->canEdit()),
                    Tables\Actions\DeleteAction::make()
                        ->visible(fn (MedicineCatalog $record) => $record->canDelete()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Filter out system records
                            return $records->filter(fn ($record) => $record->canDelete());
                        }),
                ]),
            ])
            ->defaultSort('brand_name');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMedicineCatalog::route('/'),
            'create' => Pages\CreateMedicineCatalog::route('/create'),
            'edit' => Pages\EditMedicineCatalog::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSystemMedicines();
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('prescriptions::prescription.catalog.fields.generic_name') => $record->generic_name,
            __('prescriptions::prescription.catalog.fields.category') => $record->category_label,
        ];
    }
}
