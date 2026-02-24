<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ServiceParameter;

class ServiceParametersRelationManager extends RelationManager
{
    protected static string $relationship = 'parameters';

    protected static ?string $recordTitleAttribute = 'parameter_key';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('services::services.labels.custom_parameters');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('services::services.parameters.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('parameter_key')
                                    ->label(__('services::services.parameters.key'))
                                    ->required()
                                    ->maxLength(50)
                                    ->alphaDash()
                                    ->helperText(__('services::services.parameters.key_help')),

                                Forms\Components\Select::make('parameter_config.type')
                                    ->label(__('services::services.parameters.type'))
                                    ->options([
                                        'text' => __('services::services.parameters.types.text'),
                                        'number' => __('services::services.parameters.types.number'),
                                        'decimal' => __('services::services.parameters.types.decimal'),
                                        'select' => __('services::services.parameters.types.select'),
                                        'boolean' => __('services::services.parameters.types.boolean'),
                                        'textarea' => __('services::services.parameters.types.textarea'),
                                    ])
                                    ->required()
                                    ->live()
                                    ->default('text'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('parameter_config.label.en')
                                    ->label(__('services::services.parameters.label') . ' (English)')
                                    ->required()
                                    ->maxLength(100),

                                Forms\Components\TextInput::make('parameter_config.label.ar')
                                    ->label(__('services::services.parameters.label') . ' (Arabic)')
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('category')
                                    ->label(__('services::services.parameters.category'))
                                    ->options([
                                        'equipment_settings' => __('services::services.parameters.categories.equipment_settings'),
                                        'clinical' => __('services::services.parameters.categories.clinical'),
                                        'safety' => __('services::services.parameters.categories.safety'),
                                        'outcomes' => __('services::services.parameters.categories.outcomes'),
                                    ])
                                    ->default('clinical'),

                                Forms\Components\Toggle::make('is_required')
                                    ->label(__('services::services.parameters.is_required'))
                                    ->default(false),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('services::services.fields.is_active'))
                                    ->default(true),
                            ]),

                        Forms\Components\TextInput::make('display_order')
                            ->label(__('services::services.parameters.display_order'))
                            ->numeric()
                            ->default(0),
                    ]),

                Forms\Components\Section::make(__('services::services.parameters.type_settings'))
                    ->schema([
                        // Number/Decimal settings
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('parameter_config.min')
                                    ->label(__('services::services.parameters.min'))
                                    ->numeric(),

                                Forms\Components\TextInput::make('parameter_config.max')
                                    ->label(__('services::services.parameters.max'))
                                    ->numeric(),

                                Forms\Components\TextInput::make('parameter_config.step')
                                    ->label(__('services::services.parameters.step'))
                                    ->numeric()
                                    ->default(1),

                                Forms\Components\TextInput::make('parameter_config.unit')
                                    ->label(__('services::services.parameters.unit'))
                                    ->maxLength(20),
                            ])
                            ->visible(fn (Forms\Get $get) => in_array($get('parameter_config.type'), ['number', 'decimal'])),

                        // Select options
                        Forms\Components\Repeater::make('parameter_config.options')
                            ->label(__('services::services.parameters.options'))
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\TextInput::make('value')
                                            ->label(__('services::services.parameters.option_value'))
                                            ->required(),

                                        Forms\Components\TextInput::make('label.en')
                                            ->label(__('services::services.parameters.option_label') . ' (EN)')
                                            ->required(),

                                        Forms\Components\TextInput::make('label.ar')
                                            ->label(__('services::services.parameters.option_label') . ' (AR)'),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? $state['value'] ?? null)
                            ->collapsible()
                            ->reorderable()
                            ->visible(fn (Forms\Get $get) => $get('parameter_config.type') === 'select'),

                        // Default value
                        Forms\Components\TextInput::make('parameter_config.default_value')
                            ->label(__('services::services.parameters.default_value'))
                            ->visible(fn (Forms\Get $get) => in_array($get('parameter_config.type'), ['text', 'number', 'decimal', 'textarea'])),

                        Forms\Components\Toggle::make('parameter_config.default_value')
                            ->label(__('services::services.parameters.default_value'))
                            ->visible(fn (Forms\Get $get) => $get('parameter_config.type') === 'boolean'),
                    ]),

                Forms\Components\Section::make(__('services::services.parameters.help_text'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('parameter_config.help_text.en')
                                    ->label(__('services::services.parameters.help_text') . ' (English)')
                                    ->rows(2),

                                Forms\Components\Textarea::make('parameter_config.help_text.ar')
                                    ->label(__('services::services.parameters.help_text') . ' (Arabic)')
                                    ->rows(2),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('parameter_key')
            ->columns([
                Tables\Columns\TextColumn::make('parameter_key')
                    ->label(__('services::services.parameters.key'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parameter_config.label.en')
                    ->label(__('services::services.parameters.label'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('parameter_config.type')
                    ->label(__('services::services.parameters.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'number', 'decimal' => 'info',
                        'select' => 'warning',
                        'boolean' => 'success',
                        'textarea' => 'gray',
                        default => 'primary',
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('services::services.parameters.category'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'equipment_settings' => __('services::services.parameters.categories.equipment_settings'),
                        'clinical' => __('services::services.parameters.categories.clinical'),
                        'safety' => __('services::services.parameters.categories.safety'),
                        'outcomes' => __('services::services.parameters.categories.outcomes'),
                        default => $state ?? '-',
                    }),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('services::services.parameters.is_required'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label(__('services::services.parameters.display_order'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('services::services.parameters.category'))
                    ->options([
                        'equipment_settings' => __('services::services.parameters.categories.equipment_settings'),
                        'clinical' => __('services::services.parameters.categories.clinical'),
                        'safety' => __('services::services.parameters.categories.safety'),
                        'outcomes' => __('services::services.parameters.categories.outcomes'),
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.fields.is_active')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->parameter_mode === 'custom'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('display_order')
            ->defaultSort('display_order')
            ->emptyStateHeading(__('services::services.parameters.no_parameters'))
            ->emptyStateDescription(fn () => $this->getOwnerRecord()->parameter_mode === 'custom'
                ? __('services::services.parameters.no_parameters_description')
                : __('services::services.parameters.use_custom_mode')
            );
    }
}
