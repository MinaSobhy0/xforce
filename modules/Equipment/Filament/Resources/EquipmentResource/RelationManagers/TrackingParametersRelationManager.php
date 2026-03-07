<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\RelationManagers;

use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentTrackingParameter;
use Modules\Equipment\Models\EquipmentParameterTemplate;

class TrackingParametersRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'trackingParameters';

    protected static ?string $title = 'Tracking Parameters';

    protected static ?string $icon = 'heroicon-o-adjustments-horizontal';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('equipment::equipment.parameters.basic_info'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('parameter_key')
                                    ->label(__('equipment::equipment.parameters.key'))
                                    ->required()
                                    ->maxLength(50)
                                    ->alphaDash()
                                    ->unique(ignoreRecord: true)
                                    ->helperText(__('equipment::equipment.parameters.key_help')),

                                Forms\Components\TextInput::make('name')
                                    ->label(__('equipment::equipment.parameters.name'))
                                    ->required()
                                    ->maxLength(100),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('value_type')
                                    ->label(__('equipment::equipment.parameters.value_type'))
                                    ->options(EquipmentTrackingParameter::VALUE_TYPES)
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn ($state, callable $set) => $this->resetValueFields($state, $set)),

                                Forms\Components\Select::make('unit')
                                    ->label(__('equipment::equipment.parameters.unit'))
                                    ->options(EquipmentTrackingParameter::COMMON_UNITS)
                                    ->searchable()
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal'])),

                                Forms\Components\Select::make('category')
                                    ->label(__('equipment::equipment.parameters.category'))
                                    ->options(EquipmentTrackingParameter::CATEGORIES)
                                    ->default('other'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label(__('equipment::equipment.parameters.description'))
                            ->rows(2)
                            ->helperText(__('equipment::equipment.parameters.description_help')),
                    ]),

                Forms\Components\Section::make(__('equipment::equipment.parameters.value_config'))
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('min_value')
                                    ->label(__('equipment::equipment.parameters.min_value'))
                                    ->numeric()
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal'])),

                                Forms\Components\TextInput::make('max_value')
                                    ->label(__('equipment::equipment.parameters.max_value'))
                                    ->numeric()
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal'])),

                                Forms\Components\TextInput::make('default_value')
                                    ->label(__('equipment::equipment.parameters.default_value'))
                                    ->numeric()
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal'])),

                                Forms\Components\TextInput::make('step')
                                    ->label(__('equipment::equipment.parameters.step'))
                                    ->numeric()
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal']))
                                    ->helperText(__('equipment::equipment.parameters.step_help')),
                            ]),

                        Forms\Components\Repeater::make('options')
                            ->label(__('equipment::equipment.parameters.options'))
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label(__('equipment::equipment.parameters.option_value'))
                                    ->required(),
                                Forms\Components\TextInput::make('label')
                                    ->label(__('equipment::equipment.parameters.option_label'))
                                    ->required(),
                            ])
                            ->columns(2)
                            ->visible(fn ($get) => $get('value_type') === 'select')
                            ->minItems(1)
                            ->addActionLabel(__('equipment::equipment.parameters.add_option')),
                    ])
                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal', 'select'])),

                Forms\Components\Section::make(__('equipment::equipment.parameters.tracking_config'))
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Toggle::make('is_required')
                                    ->label(__('equipment::equipment.parameters.is_required'))
                                    ->helperText(__('equipment::equipment.parameters.is_required_help')),

                                Forms\Components\Toggle::make('is_cumulative')
                                    ->label(__('equipment::equipment.parameters.is_cumulative'))
                                    ->helperText(__('equipment::equipment.parameters.is_cumulative_help'))
                                    ->visible(fn ($get) => in_array($get('value_type'), ['integer', 'decimal'])),

                                Forms\Components\Toggle::make('track_in_session')
                                    ->label(__('equipment::equipment.parameters.track_in_session'))
                                    ->default(true)
                                    ->helperText(__('equipment::equipment.parameters.track_in_session_help')),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('display_order')
                                    ->label(__('equipment::equipment.parameters.display_order'))
                                    ->numeric()
                                    ->default(0),

                                Forms\Components\Toggle::make('is_active')
                                    ->label(__('equipment::equipment.parameters.is_active'))
                                    ->default(true),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('parameter_key')
                    ->label(__('equipment::equipment.parameters.key'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('equipment::equipment.parameters.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('value_type')
                    ->label(__('equipment::equipment.parameters.type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => EquipmentTrackingParameter::VALUE_TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('unit')
                    ->label(__('equipment::equipment.parameters.unit'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('category')
                    ->label(__('equipment::equipment.parameters.category'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => EquipmentTrackingParameter::CATEGORIES[$state] ?? $state),

                Tables\Columns\IconColumn::make('is_required')
                    ->label(__('equipment::equipment.parameters.required'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('track_in_session')
                    ->label(__('equipment::equipment.parameters.track'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label(__('equipment::equipment.parameters.order'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('equipment::equipment.parameters.active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('source')
                    ->label(__('equipment::equipment.template.source'))
                    ->badge()
                    ->color(fn ($state) => $state === 'template' ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state) => $state === 'template' ? __('equipment::equipment.template.from_template') : __('equipment::equipment.template.manual'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(EquipmentTrackingParameter::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('equipment::equipment.parameters.active')),

                Tables\Filters\TernaryFilter::make('is_required')
                    ->label(__('equipment::equipment.parameters.required')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = app('currentTenant')?->id;
                        $data['source'] = 'manual';
                        return $data;
                    })
                    ->visible(fn (): bool => $this->canEditParentResource()),
                Tables\Actions\Action::make('import_from_template')
                    ->label(__('equipment::equipment.template.import'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->visible(fn (): bool => $this->canEditParentResource())
                    ->form([
                        Forms\Components\Select::make('template_id')
                            ->label(__('equipment::equipment.template.select'))
                            ->options(fn () => EquipmentParameterTemplate::active()
                                ->pluck('template_name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription(__('equipment::equipment.template.import_confirm'))
                    ->action(function (array $data) {
                        $template = EquipmentParameterTemplate::find($data['template_id']);
                        if ($template) {
                            /** @var Equipment $equipment */
                            $equipment = $this->getOwnerRecord();
                            $template->applyToEquipment($equipment);

                            Notification::make()
                                ->success()
                                ->title(__('equipment::equipment.template.applied'))
                                ->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => $this->canEditParentResource()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => $this->canEditParentResource()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('display_order')
            ->reorderable('display_order');
    }

    protected function resetValueFields(string $type, callable $set): void
    {
        if (!in_array($type, ['integer', 'decimal'])) {
            $set('min_value', null);
            $set('max_value', null);
            $set('default_value', null);
            $set('step', null);
            $set('unit', null);
        }

        if ($type !== 'select') {
            $set('options', null);
        }
    }
}
