<?php

namespace Modules\Equipment\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentParameterTemplate;
use Modules\Equipment\Filament\Resources\EquipmentParameterTemplateResource\Pages;

class EquipmentParameterTemplateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = EquipmentParameterTemplate::class;

    protected static ?string $moduleCode = 'equipment';

    protected static ?string $permissionKey = 'equipment_parameter_templates';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'template_name';

    public static function getNavigationLabel(): string
    {
        return __('equipment::equipment.navigation.parameter_templates');
    }

    public static function getModelLabel(): string
    {
        return __('equipment::equipment.labels.parameter_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipment::equipment.labels.parameter_templates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('equipment::equipment.template.info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('template_name')
                                    ->label(__('equipment::equipment.template.name'))
                                    ->required()
                                    ->maxLength(200),

                                Forms\Components\TextInput::make('template_code')
                                    ->label(__('equipment::equipment.template.code'))
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('equipment::equipment.template.code_help'))
                                    ->disabled(fn ($record) => $record?->is_system),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('equipment_category')
                                    ->label(__('equipment::equipment.template.category'))
                                    ->options(Equipment::CATEGORIES)
                                    ->searchable()
                                    ->helperText(__('equipment::equipment.template.category_help')),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('equipment::equipment.active'))
                                    ->default(true),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('equipment::equipment.description'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('equipment::equipment.template.parameters'))
                    ->description(__('equipment::equipment.template.parameters_desc'))
                    ->schema([
                        Forms\Components\Repeater::make('parameters')
                            ->label('')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('key')
                                            ->label(__('equipment::equipment.parameters.key'))
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText(__('equipment::equipment.parameters.key_help')),

                                        Forms\Components\Select::make('type')
                                            ->label(__('equipment::equipment.parameters.value_type'))
                                            ->required()
                                            ->options([
                                                'text' => __('equipment::equipment.template.types.text'),
                                                'number' => __('equipment::equipment.template.types.number'),
                                                'decimal' => __('equipment::equipment.template.types.decimal'),
                                                'select' => __('equipment::equipment.template.types.select'),
                                                'boolean' => __('equipment::equipment.template.types.boolean'),
                                                'textarea' => __('equipment::equipment.template.types.textarea'),
                                            ])
                                            ->live(),

                                        Forms\Components\TextInput::make('unit')
                                            ->label(__('equipment::equipment.parameters.unit'))
                                            ->maxLength(50)
                                            ->helperText(__('equipment::equipment.template.unit_help')),

                                        Forms\Components\Select::make('category')
                                            ->label(__('equipment::equipment.parameters.category'))
                                            ->options([
                                                'energy' => __('equipment::equipment.template.categories.energy'),
                                                'timing' => __('equipment::equipment.template.categories.timing'),
                                                'spot' => __('equipment::equipment.template.categories.spot'),
                                                'cooling' => __('equipment::equipment.template.categories.cooling'),
                                                'other' => __('equipment::equipment.template.categories.other'),
                                            ])
                                            ->default('other'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('label.en')
                                            ->label(__('equipment::equipment.template.label_en'))
                                            ->required()
                                            ->maxLength(200),

                                        Forms\Components\TextInput::make('label.ar')
                                            ->label(__('equipment::equipment.template.label_ar'))
                                            ->maxLength(200),
                                    ]),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('min')
                                            ->label(__('equipment::equipment.parameters.min_value'))
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('max')
                                            ->label(__('equipment::equipment.parameters.max_value'))
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('step')
                                            ->label(__('equipment::equipment.parameters.step'))
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('default_value')
                                            ->label(__('equipment::equipment.parameters.default_value')),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('required')
                                            ->label(__('equipment::equipment.parameters.is_required'))
                                            ->default(false),

                                        Forms\Components\Toggle::make('cumulative')
                                            ->label(__('equipment::equipment.parameters.is_cumulative'))
                                            ->helperText(__('equipment::equipment.parameters.is_cumulative_help'))
                                            ->default(false),
                                    ]),

                                Forms\Components\Repeater::make('options')
                                    ->label(__('equipment::equipment.parameters.options'))
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('value')
                                                    ->label(__('equipment::equipment.parameters.option_value'))
                                                    ->required(),

                                                Forms\Components\TextInput::make('label.en')
                                                    ->label(__('equipment::equipment.template.label_en'))
                                                    ->required(),

                                                Forms\Components\TextInput::make('label.ar')
                                                    ->label(__('equipment::equipment.template.label_ar')),
                                            ]),
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'select')
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('help_text.en')
                                            ->label(__('equipment::equipment.template.help_en'))
                                            ->maxLength(500),

                                        Forms\Components\TextInput::make('help_text.ar')
                                            ->label(__('equipment::equipment.template.help_ar'))
                                            ->maxLength(500),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? $state['key'] ?? __('equipment::equipment.template.new_parameter'))
                            ->collapsible()
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template_name')
                    ->label(__('equipment::equipment.template.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template_code')
                    ->label(__('equipment::equipment.template.code'))
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('equipment_category')
                    ->label(__('equipment::equipment.template.category'))
                    ->formatStateUsing(fn ($state) => Equipment::CATEGORIES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('parameters')
                    ->label(__('equipment::equipment.template.parameters'))
                    ->formatStateUsing(fn ($state) => count($state ?? []) . ' ' . __('equipment::equipment.template.parameters_count'))
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('equipment_count')
                    ->label(__('equipment::equipment.template.equipment_using'))
                    ->counts('equipment')
                    ->badge()
                    ->color('success'),

                Tables\Columns\IconColumn::make('is_system')
                    ->label(__('equipment::equipment.template.system'))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('equipment::equipment.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('equipment::equipment.template.updated'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('equipment_category')
                    ->label(__('equipment::equipment.template.category'))
                    ->options(Equipment::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label(__('equipment::equipment.template.system')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('equipment::equipment.active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->label(__('equipment::equipment.template.duplicate'))
                    ->beforeReplicaSaved(function (EquipmentParameterTemplate $replica): void {
                        $replica->template_name = $replica->template_name . ' (Copy)';
                        $replica->template_code = $replica->template_code . '_COPY_' . time();
                        $replica->is_system = false;
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->filter(fn ($r) => !$r->is_system)->each->delete();
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipmentParameterTemplates::route('/'),
            'create' => Pages\CreateEquipmentParameterTemplate::route('/create'),
            'view' => Pages\ViewEquipmentParameterTemplate::route('/{record}'),
            'edit' => Pages\EditEquipmentParameterTemplate::route('/{record}/edit'),
        ];
    }
}
