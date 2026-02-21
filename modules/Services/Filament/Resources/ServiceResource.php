<?php

namespace Modules\Services\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ConsentTemplate;
use Modules\Equipment\Models\EquipmentType;
use Modules\Services\Filament\Resources\ServiceResource\Pages;
use Modules\Services\Filament\Resources\ServiceResource\RelationManagers;
use Illuminate\Database\Eloquent\Builder;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Services';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getNavigationLabel(): string
    {
        return __('services::services.navigation.services');
    }

    public static function getModelLabel(): string
    {
        return __('services::services.labels.service');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services::services.labels.services');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Service')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Basic Info')
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('code')
                                            ->label('Code')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->visible(fn ($record) => $record !== null),

                                        Forms\Components\Select::make('category_id')
                                            ->label(__('services::services.fields.category'))
                                            ->relationship('category', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->full_path)
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('services::services.fields.is_active'))
                                            ->default(true),
                                    ]),

                                Forms\Components\Section::make('Name & Description')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('name.en')
                                                    ->label(__('services::services.fields.name') . ' (English)')
                                                    ->required()
                                                    ->maxLength(150),

                                                Forms\Components\TextInput::make('name.ar')
                                                    ->label(__('services::services.fields.name') . ' (Arabic)')
                                                    ->required()
                                                    ->maxLength(150),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('short_description.en')
                                                    ->label('Short Description (English)')
                                                    ->rows(2),

                                                Forms\Components\Textarea::make('short_description.ar')
                                                    ->label('Short Description (Arabic)')
                                                    ->rows(2),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\RichEditor::make('description.en')
                                                    ->label(__('services::services.fields.description') . ' (English)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),

                                                Forms\Components\RichEditor::make('description.ar')
                                                    ->label(__('services::services.fields.description') . ' (Arabic)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Scheduling & Pricing')
                                    ->schema([
                                        Forms\Components\Grid::make(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('duration_minutes')
                                                    ->label(__('services::services.fields.duration'))
                                                    ->numeric()
                                                    ->required()
                                                    ->default(30)
                                                    ->suffix('min'),

                                                Forms\Components\TextInput::make('buffer_minutes')
                                                    ->label(__('services::services.fields.buffer_time'))
                                                    ->numeric()
                                                    ->default(0)
                                                    ->suffix('min'),

                                                Forms\Components\TextInput::make('base_price_minor')
                                                    ->label(__('services::services.fields.base_price'))
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0)
                                                    ->suffix('piasters')
                                                    ->helperText('Enter price in piasters (100 piasters = 1 EGP)'),

                                                Forms\Components\TextInput::make('sort_order')
                                                    ->label('Sort Order')
                                                    ->numeric()
                                                    ->default(0),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('recommended_sessions')
                                                    ->label(__('services::services.fields.recommended_sessions'))
                                                    ->numeric()
                                                    ->nullable(),

                                                Forms\Components\TextInput::make('session_interval_days')
                                                    ->label(__('services::services.fields.session_interval_days'))
                                                    ->numeric()
                                                    ->nullable()
                                                    ->suffix('days'),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Online Booking')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_bookable_online')
                                            ->label('Available for Online Booking')
                                            ->default(true),

                                        Forms\Components\FileUpload::make('image_url')
                                            ->label('Service Image')
                                            ->image()
                                            ->directory('services')
                                            ->visibility('public'),

                                        Forms\Components\TagsInput::make('tags')
                                            ->label('Tags')
                                            ->separator(','),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Safety & Medical')
                            ->schema([
                                Forms\Components\Section::make('Skin Type Safety')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Select::make('fitzpatrick_min')
                                                    ->label(__('services::services.fields.fitzpatrick_min'))
                                                    ->options([
                                                        1 => 'Type I - Very fair',
                                                        2 => 'Type II - Fair',
                                                        3 => 'Type III - Medium',
                                                        4 => 'Type IV - Olive',
                                                        5 => 'Type V - Brown',
                                                        6 => 'Type VI - Dark brown/Black',
                                                    ])
                                                    ->nullable(),

                                                Forms\Components\Select::make('fitzpatrick_max')
                                                    ->label(__('services::services.fields.fitzpatrick_max'))
                                                    ->options([
                                                        1 => 'Type I - Very fair',
                                                        2 => 'Type II - Fair',
                                                        3 => 'Type III - Medium',
                                                        4 => 'Type IV - Olive',
                                                        5 => 'Type V - Brown',
                                                        6 => 'Type VI - Dark brown/Black',
                                                    ])
                                                    ->nullable(),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Contraindications')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('contraindications')
                                            ->label(__('services::services.fields.contraindications'))
                                            ->options([
                                                'pregnancy' => 'Pregnancy',
                                                'breastfeeding' => 'Breastfeeding',
                                                'active_infection' => 'Active Skin Infection',
                                                'accutane' => 'Accutane (past 6 months)',
                                                'blood_thinners' => 'Blood Thinners',
                                                'autoimmune' => 'Autoimmune Disease',
                                                'diabetes' => 'Uncontrolled Diabetes',
                                                'keloid_history' => 'History of Keloids',
                                                'herpes' => 'Active Herpes',
                                                'cancer' => 'Active Cancer Treatment',
                                                'pacemaker' => 'Pacemaker/Defibrillator',
                                                'metal_implants' => 'Metal Implants in Treatment Area',
                                            ])
                                            ->columns(3),
                                    ]),

                                Forms\Components\Section::make('Consent Requirements')
                                    ->schema([
                                        Forms\Components\Toggle::make('requires_consent')
                                            ->label(__('services::services.fields.requires_consent'))
                                            ->reactive(),

                                        Forms\Components\Select::make('consent_template_id')
                                            ->label(__('services::services.fields.consent_template'))
                                            ->relationship('consentTemplate', 'id', fn (Builder $query) => $query->active())
                                            ->getOptionLabelFromRecordUsing(fn (ConsentTemplate $record) => $record->translated_name . ' (v' . $record->version . ')')
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Forms\Get $get) => $get('requires_consent')),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Care Instructions')
                            ->schema([
                                Forms\Components\Section::make('Pre-Service Instructions')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\RichEditor::make('pre_care_instructions.en')
                                                    ->label(__('services::services.fields.pre_care') . ' (English)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),

                                                Forms\Components\RichEditor::make('pre_care_instructions.ar')
                                                    ->label(__('services::services.fields.pre_care') . ' (Arabic)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Post-Service Instructions')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\RichEditor::make('post_care_instructions.en')
                                                    ->label(__('services::services.fields.post_care') . ' (English)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),

                                                Forms\Components\RichEditor::make('post_care_instructions.ar')
                                                    ->label(__('services::services.fields.post_care') . ' (Arabic)')
                                                    ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList']),
                                            ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Resources')
                            ->schema([
                                Forms\Components\Section::make('Equipment & Consumables')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('requiredEquipmentTypes')
                                            ->label(__('services::services.fields.equipment_required'))
                                            ->relationship(
                                                name: 'requiredEquipmentTypes',
                                                titleAttribute: 'id',
                                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (EquipmentType $record) => $record->translated_name)
                                            ->columns(2)
                                            ->helperText('Equipment types needed for this service'),

                                        Forms\Components\TagsInput::make('consumables_required')
                                            ->label('Consumables Required')
                                            ->separator(',')
                                            ->helperText('Consumable items needed per session'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('services::services.fields.name'))
                    ->searchable(['name'])
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.translated_name')
                    ->label(__('services::services.fields.category'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label(__('services::services.fields.base_price'))
                    ->sortable(['base_price_minor']),

                Tables\Columns\IconColumn::make('requires_consent')
                    ->label('Consent')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_bookable_online')
                    ->label('Online')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label(__('services::services.fields.category'))
                    ->relationship('category', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->translated_name)
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.fields.is_active')),

                Tables\Filters\TernaryFilter::make('requires_consent')
                    ->label(__('services::services.fields.requires_consent')),

                Tables\Filters\TernaryFilter::make('is_bookable_online')
                    ->label('Online Booking'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BranchPricingRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\PackageItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }
}
