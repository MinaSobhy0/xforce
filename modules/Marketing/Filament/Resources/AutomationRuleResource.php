<?php

namespace Modules\Marketing\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Marketing\Filament\Resources\AutomationRuleResource\Pages;
use Modules\Marketing\Models\AutomationRule;
use Modules\Marketing\Models\MessageTemplate;

class AutomationRuleResource extends Resource
{
    protected static ?string $model = AutomationRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string
    {
        return __('marketing::marketing.navigation.automation');
    }

    public static function getModelLabel(): string
    {
        return __('marketing::marketing.labels.automation_rule');
    }

    public static function getPluralModelLabel(): string
    {
        return __('marketing::marketing.labels.automation_rules');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('marketing::marketing.sections.rule_details'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name.en')
                                    ->label(__('marketing::marketing.fields.name_en'))
                                    ->required(),

                                Forms\Components\TextInput::make('name.ar')
                                    ->label(__('marketing::marketing.fields.name_ar'))
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Textarea::make('description.en')
                                    ->label(__('marketing::marketing.fields.description_en'))
                                    ->rows(2),

                                Forms\Components\Textarea::make('description.ar')
                                    ->label(__('marketing::marketing.fields.description_ar'))
                                    ->rows(2),
                            ]),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.trigger'))
                    ->schema([
                        Forms\Components\Select::make('trigger_type')
                            ->label(__('marketing::marketing.fields.trigger_type'))
                            ->options(AutomationRule::triggerTypes())
                            ->required()
                            ->live(),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('timing_type')
                                    ->label(__('marketing::marketing.fields.timing_type'))
                                    ->options(AutomationRule::timingTypes())
                                    ->default('immediate')
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('timing_value')
                                    ->label(__('marketing::marketing.fields.timing_value'))
                                    ->numeric()
                                    ->default(0)
                                    ->visible(fn (Forms\Get $get) => $get('timing_type') !== 'immediate'),

                                Forms\Components\Select::make('timing_unit')
                                    ->label(__('marketing::marketing.fields.timing_unit'))
                                    ->options(AutomationRule::timingUnits())
                                    ->default('hours')
                                    ->visible(fn (Forms\Get $get) => $get('timing_type') !== 'immediate'),
                            ]),
                    ]),

                Forms\Components\Section::make(__('marketing::marketing.sections.message'))
                    ->schema([
                        Forms\Components\Select::make('channel')
                            ->label(__('marketing::marketing.fields.channel'))
                            ->options(MessageTemplate::channels())
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('template_id')
                            ->label(__('marketing::marketing.fields.template'))
                            ->options(function (Forms\Get $get) {
                                $channel = $get('channel');
                                if (!$channel) {
                                    return [];
                                }
                                return MessageTemplate::where('channel', $channel)
                                    ->where('is_active', true)
                                    ->pluck('name', 'id')
                                    ->map(fn ($name) => is_array($name) ? ($name['en'] ?? reset($name)) : $name);
                            })
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('marketing::marketing.sections.conditions'))
                    ->schema([
                        Forms\Components\Repeater::make('conditions_json')
                            ->label(__('marketing::marketing.fields.conditions'))
                            ->schema([
                                Forms\Components\Select::make('field')
                                    ->label(__('marketing::marketing.fields.condition_field'))
                                    ->options([
                                        'treatment_id' => __('marketing::marketing.condition_fields.treatment'),
                                        'branch_id' => __('marketing::marketing.condition_fields.branch'),
                                        'is_vip' => __('marketing::marketing.condition_fields.is_vip'),
                                        'is_new_patient' => __('marketing::marketing.condition_fields.is_new_patient'),
                                    ])
                                    ->required(),

                                Forms\Components\Select::make('operator')
                                    ->label(__('marketing::marketing.fields.operator'))
                                    ->options([
                                        'equals' => '=',
                                        'not_equals' => '!=',
                                        'in' => __('marketing::marketing.operators.in'),
                                        'not_in' => __('marketing::marketing.operators.not_in'),
                                    ])
                                    ->default('equals'),

                                Forms\Components\TextInput::make('value')
                                    ->label(__('marketing::marketing.fields.value'))
                                    ->required(),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('marketing::marketing.actions.add_condition'))
                            ->collapsible()
                            ->defaultItems(0),
                    ])
                    ->collapsible(),

                Forms\Components\Section::make(__('marketing::marketing.sections.settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('marketing::marketing.fields.is_active'))
                            ->default(true),

                        Forms\Components\TextInput::make('priority')
                            ->label(__('marketing::marketing.fields.priority'))
                            ->numeric()
                            ->default(0)
                            ->helperText(__('marketing::marketing.helpers.priority')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('marketing::marketing.fields.name'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('trigger_type')
                    ->label(__('marketing::marketing.fields.trigger'))
                    ->formatStateUsing(fn (string $state) => AutomationRule::triggerTypes()[$state] ?? $state),

                Tables\Columns\BadgeColumn::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->colors([
                        'success' => 'whatsapp',
                        'info' => 'sms',
                        'primary' => 'email',
                    ]),

                Tables\Columns\TextColumn::make('timing_display')
                    ->label(__('marketing::marketing.fields.timing'))
                    ->getStateUsing(function (AutomationRule $record) {
                        if ($record->timing_type === 'immediate') {
                            return __('marketing::marketing.timing.immediate');
                        }
                        $type = $record->timing_type === 'before'
                            ? __('marketing::marketing.timing.before')
                            : __('marketing::marketing.timing.after');
                        return "{$record->timing_value} {$record->timing_unit} {$type}";
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('marketing::marketing.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('priority')
                    ->label(__('marketing::marketing.fields.priority'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('trigger_type')
                    ->label(__('marketing::marketing.fields.trigger'))
                    ->options(AutomationRule::triggerTypes()),

                Tables\Filters\SelectFilter::make('channel')
                    ->label(__('marketing::marketing.fields.channel'))
                    ->options(MessageTemplate::channels()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('marketing::marketing.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (AutomationRule $record) => $record->is_active
                        ? __('marketing::marketing.actions.deactivate')
                        : __('marketing::marketing.actions.activate'))
                    ->icon(fn (AutomationRule $record) => $record->is_active
                        ? 'heroicon-o-pause'
                        : 'heroicon-o-play')
                    ->color(fn (AutomationRule $record) => $record->is_active ? 'warning' : 'success')
                    ->action(fn (AutomationRule $record) => $record->update(['is_active' => !$record->is_active])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('priority', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutomationRules::route('/'),
            'create' => Pages\CreateAutomationRule::route('/create'),
            'edit' => Pages\EditAutomationRule::route('/{record}/edit'),
        ];
    }
}
