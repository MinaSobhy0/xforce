<?php

namespace Modules\Services\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ConsentTemplate;
use Modules\Services\Models\ParameterTemplate;
use Modules\Equipment\Models\Equipment;
use Modules\Core\Models\Room;
use Modules\Auth\Models\User;
use Modules\Staff\Models\StaffProfile;
use Modules\Inventory\Models\Product;
use Modules\Services\Filament\Resources\ServiceResource\Pages;
use Modules\Services\Filament\Resources\ServiceResource\RelationManagers;
use Illuminate\Database\Eloquent\Builder;
use XLinic\Framework\Core\Filament\RelationManagers\ActivityLogRelationManager;

class ServiceResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Service::class;

    protected static ?string $moduleCode = 'services';

    protected static ?string $permissionKey = 'services';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 1;

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
                                Forms\Components\Grid::make(4)
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

                                        Forms\Components\Toggle::make('is_consultation')
                                            ->label(__('services::services.fields.is_consultation'))
                                            ->helperText(__('services::services.fields.is_consultation_help'))
                                            ->default(false),
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
                                                Forms\Components\Textarea::make('description.en')
                                                    ->label(__('services::services.fields.description') . ' (English)')
                                                    ->rows(4),

                                                Forms\Components\Textarea::make('description.ar')
                                                    ->label(__('services::services.fields.description') . ' (Arabic)')
                                                    ->rows(4),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Scheduling & Pricing')
                                    ->schema([
                                        Forms\Components\Grid::make(5)
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
                                                    ->prefix(current_currency())
                                                    ->formatStateUsing(fn ($state) => $state ? $state / 100 : null)
                                                    ->dehydrateStateUsing(fn ($state) => $state ? (int) ($state * 100) : 0),

                                                Forms\Components\TextInput::make('max_discount_percent')
                                                    ->label(__('services::services.fields.max_discount'))
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->maxValue(100)
                                                    ->suffix('%')
                                                    ->helperText(__('services::services.fields.max_discount_help')),

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
                                                Forms\Components\Textarea::make('pre_care_instructions.en')
                                                    ->label(__('services::services.fields.pre_care') . ' (English)')
                                                    ->rows(5),

                                                Forms\Components\Textarea::make('pre_care_instructions.ar')
                                                    ->label(__('services::services.fields.pre_care') . ' (Arabic)')
                                                    ->rows(5),
                                            ]),
                                    ]),

                                Forms\Components\Section::make('Post-Service Instructions')
                                    ->schema([
                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('post_care_instructions.en')
                                                    ->label(__('services::services.fields.post_care') . ' (English)')
                                                    ->rows(5),

                                                Forms\Components\Textarea::make('post_care_instructions.ar')
                                                    ->label(__('services::services.fields.post_care') . ' (Arabic)')
                                                    ->rows(5),
                                            ]),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('Resources')
                            ->schema([
                                Forms\Components\Section::make('Equipment & Consumables')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('requiredEquipment')
                                            ->label(__('services::services.fields.equipment_required'))
                                            ->relationship(
                                                name: 'requiredEquipment',
                                                titleAttribute: 'name',
                                                modifyQueryUsing: fn ($query) => $query->where('status', Equipment::STATUS_ACTIVE)
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (Equipment $record) => "{$record->name} ({$record->code})")
                                            ->columns(2)
                                            ->helperText('Equipment needed for this service'),

                                        Forms\Components\Repeater::make('consumables_required')
                                            ->label(__('services::services.fields.consumables_required'))
                                            ->schema([
                                                Forms\Components\Select::make('product_id')
                                                    ->label(__('services::services.consumables.product'))
                                                    ->options(function () {
                                                        return Product::query()
                                                            ->where('is_consumable', true)
                                                            ->where('is_active', true)
                                                            ->get()
                                                            ->mapWithKeys(fn ($product) => [
                                                                $product->id => ($product->getTranslation('name', app()->getLocale()) ?? $product->sku) . " ({$product->sku})"
                                                            ]);
                                                    })
                                                    ->searchable()
                                                    ->required(),
                                                Forms\Components\TextInput::make('quantity')
                                                    ->label(__('services::services.consumables.quantity'))
                                                    ->numeric()
                                                    ->default(1)
                                                    ->minValue(1)
                                                    ->required(),
                                            ])
                                            ->columns(2)
                                            ->defaultItems(0)
                                            ->reorderable(false)
                                            ->addActionLabel(__('services::services.consumables.add_consumable'))
                                            ->helperText(__('services::services.consumables.quantity_help')),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('services::services.tabs.parameters'))
                            ->schema([
                                Forms\Components\Section::make(__('services::services.sections.parameter_configuration'))
                                    ->description(__('services::services.sections.parameter_configuration_description'))
                                    ->schema([
                                        Forms\Components\Toggle::make('has_dynamic_parameters')
                                            ->label(__('services::services.fields.has_dynamic_parameters'))
                                            ->helperText(__('services::services.fields.has_dynamic_parameters_help'))
                                            ->live()
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                if (!$state) {
                                                    $set('parameter_mode', 'none');
                                                    $set('parameter_template_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('parameter_mode')
                                            ->label(__('services::services.fields.parameter_mode'))
                                            ->options([
                                                'none' => __('services::services.parameter_modes.none'),
                                                'template' => __('services::services.parameter_modes.template'),
                                                'custom' => __('services::services.parameter_modes.custom'),
                                            ])
                                            ->default('none')
                                            ->live()
                                            ->visible(fn (Forms\Get $get) => $get('has_dynamic_parameters'))
                                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                                if ($state !== 'template') {
                                                    $set('parameter_template_id', null);
                                                }
                                            }),

                                        Forms\Components\Select::make('parameter_template_id')
                                            ->label(__('services::services.fields.parameter_template'))
                                            ->relationship('parameterTemplate', 'id')
                                            ->getOptionLabelFromRecordUsing(fn (ParameterTemplate $record): string => $record->translated_name ?: $record->id)
                                            ->searchable()
                                            ->preload()
                                            ->visible(fn (Forms\Get $get) => $get('has_dynamic_parameters') && $get('parameter_mode') === 'template')
                                            ->helperText(__('services::services.fields.parameter_template_help')),
                                    ]),

                                Forms\Components\Section::make(__('services::services.sections.template_preview'))
                                    ->description(__('services::services.sections.template_preview_description'))
                                    ->visible(fn (Forms\Get $get) => $get('parameter_mode') === 'template' && $get('parameter_template_id'))
                                    ->schema([
                                        Forms\Components\Placeholder::make('template_parameters')
                                            ->label(__('services::services.fields.template_parameters'))
                                            ->content(function (Forms\Get $get) {
                                                $templateId = $get('parameter_template_id');
                                                if (!$templateId) {
                                                    return '-';
                                                }

                                                $template = ParameterTemplate::find($templateId);
                                                if (!$template || empty($template->parameters)) {
                                                    return __('services::services.messages.no_parameters_defined');
                                                }

                                                $params = collect($template->parameters)->map(function ($p) {
                                                    $label = $p['label'][app()->getLocale()] ?? $p['label']['en'] ?? $p['key'];
                                                    $type = ucfirst($p['type'] ?? 'text');
                                                    $required = ($p['is_required'] ?? false) ? '*' : '';
                                                    return "{$label}{$required} ({$type})";
                                                })->join(', ');

                                                return $params ?: '-';
                                            }),
                                    ]),

                                Forms\Components\Section::make(__('services::services.sections.custom_parameters'))
                                    ->description(__('services::services.sections.custom_parameters_description'))
                                    ->visible(fn (Forms\Get $get) => $get('has_dynamic_parameters') && $get('parameter_mode') === 'custom')
                                    ->schema([
                                        Forms\Components\Placeholder::make('custom_parameters_note')
                                            ->content(__('services::services.messages.custom_parameters_note'))
                                            ->columnSpanFull(),
                                    ]),

                                Forms\Components\Section::make(__('services::services.sections.parameter_presets'))
                                    ->description(__('services::services.sections.parameter_presets_description'))
                                    ->visible(fn (Forms\Get $get) => $get('has_dynamic_parameters') && $get('parameter_mode') !== 'none')
                                    ->schema([
                                        Forms\Components\Placeholder::make('presets_info')
                                            ->content(fn ($record) => $record
                                                ? __('services::services.messages.presets_count', ['count' => $record->parameterPresets()->count()])
                                                : __('services::services.messages.save_first_for_presets')
                                            )
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('services::services.tabs.scheduling'))
                            ->schema([
                                Forms\Components\Section::make(__('services::services.sections.qualified_staff'))
                                    ->description(__('services::services.sections.qualified_staff_description'))
                                    ->schema([
                                        Forms\Components\Select::make('qualified_staff_ids')
                                            ->label(__('services::services.fields.qualified_staff'))
                                            ->options(fn () => StaffProfile::query()
                                                ->with('user')
                                                ->whereHas('user', fn ($q) => $q->where('is_active', true))
                                                ->get()
                                                ->mapWithKeys(fn ($staff) => [
                                                    $staff->id => $staff->user?->full_name ?? $staff->employee_code
                                                ])
                                            )
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->afterStateHydrated(function ($component, $state, $record) {
                                                if ($record) {
                                                    $component->state($record->qualifiedStaff->pluck('id')->toArray());
                                                }
                                            }),
                                    ]),

                                Forms\Components\Section::make(__('services::services.sections.rooms'))
                                    ->description(__('services::services.sections.rooms_description'))
                                    ->schema([
                                        Forms\Components\Select::make('room_ids')
                                            ->label(__('services::services.fields.service_rooms'))
                                            ->options(fn () => Room::query()
                                                ->active()
                                                ->bookable()
                                                ->get()
                                                ->mapWithKeys(fn ($room) => [$room->id => $room->getDisplayName()])
                                            )
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->afterStateHydrated(function ($component, $state, $record) {
                                                if ($record) {
                                                    $component->state($record->rooms->pluck('id')->toArray());
                                                }
                                            }),
                                    ]),


                                Forms\Components\Section::make(__('services::services.sections.time_restrictions'))
                                    ->description(__('services::services.sections.time_restrictions_description'))
                                    ->schema([
                                        Forms\Components\CheckboxList::make('time_slot_restrictions.allowed_days')
                                            ->label(__('services::services.fields.allowed_days'))
                                            ->options([
                                                0 => __('Sunday'),
                                                1 => __('Monday'),
                                                2 => __('Tuesday'),
                                                3 => __('Wednesday'),
                                                4 => __('Thursday'),
                                                5 => __('Friday'),
                                                6 => __('Saturday'),
                                            ])
                                            ->columns(4),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TimePicker::make('time_slot_restrictions.allowed_time_start')
                                                ->label(__('services::services.fields.allowed_time_start'))
                                                ->seconds(false),
                                            Forms\Components\TimePicker::make('time_slot_restrictions.allowed_time_end')
                                                ->label(__('services::services.fields.allowed_time_end'))
                                                ->seconds(false),
                                        ]),

                                        Forms\Components\Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('time_slot_restrictions.min_advance_hours')
                                                ->label(__('services::services.fields.min_advance_hours'))
                                                ->numeric()
                                                ->suffix(__('services::services.fields.hours')),
                                            Forms\Components\TextInput::make('time_slot_restrictions.max_advance_days')
                                                ->label(__('services::services.fields.max_advance_days'))
                                                ->numeric()
                                                ->suffix(__('services::services.fields.days')),
                                        ]),

                                        Forms\Components\TagsInput::make('time_slot_restrictions.blackout_dates')
                                            ->label(__('services::services.fields.blackout_dates'))
                                            ->placeholder('YYYY-MM-DD')
                                            ->helperText(__('services::services.fields.blackout_dates_help')),
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
                    ->sortable(['name'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('category.translated_name')
                    ->label(__('services::services.fields.category')),

                Tables\Columns\TextColumn::make('duration_minutes')
                    ->label('Duration')
                    ->suffix(' min')
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_price')
                    ->label(__('services::services.fields.base_price'))
                    ->sortable(['base_price_minor']),

                Tables\Columns\TextColumn::make('max_discount_percent')
                    ->label(__('services::services.fields.max_discount'))
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_consultation')
                    ->label(__('services::services.fields.is_consultation'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Tables\Filters\TernaryFilter::make('is_consultation')
                    ->label(__('services::services.fields.is_consultation')),
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
            RelationManagers\ServiceParametersRelationManager::class,
            RelationManagers\ParameterPresetsRelationManager::class,
            RelationManagers\AppointmentsRelationManager::class,
            RelationManagers\PackageItemsRelationManager::class,
            ActivityLogRelationManager::class,
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
