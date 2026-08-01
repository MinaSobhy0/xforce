<?php

namespace Modules\Services\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ParameterTemplate;
use Modules\Services\Filament\Resources\ParameterTemplateResource\Pages;

class ParameterTemplateResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ParameterTemplate::class;

    protected static ?string $moduleCode = 'services';

    protected static ?string $permissionKey = 'parameter_templates';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationParentItem(): ?string
    {
        return __('core::core.nav_folders.templates');
    }

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'template_name';

    public static function getNavigationLabel(): string
    {
        return __('services::services.navigation.parameter_templates');
    }

    public static function getModelLabel(): string
    {
        return __('services::services.labels.parameter_template');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services::services.labels.parameter_templates');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('template_name')
                                    ->label('Template Name')
                                    ->required()
                                    ->maxLength(200),

                                Forms\Components\TextInput::make('template_code')
                                    ->label('Template Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('Unique code for this template (e.g., LASER_HAIR_REMOVAL)')
                                    ->disabled(fn ($record) => $record?->is_system),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('service_category')
                                    ->label('Service Category')
                                    ->maxLength(100)
                                    ->helperText('e.g., laser, injectable, skin_rejuvenation'),

                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Parameters')
                    ->description('Define the parameters that will be collected during treatment sessions')
                    ->schema([
                        Forms\Components\Repeater::make('parameters')
                            ->label('')
                            ->schema([
                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('key')
                                            ->label('Parameter Key')
                                            ->required()
                                            ->maxLength(100)
                                            ->helperText('Unique identifier (e.g., wavelength, fluence)'),

                                        Forms\Components\Select::make('type')
                                            ->label('Type')
                                            ->required()
                                            ->options([
                                                'text' => 'Text',
                                                'number' => 'Number',
                                                'decimal' => 'Decimal',
                                                'select' => 'Dropdown',
                                                'boolean' => 'Yes/No',
                                                'textarea' => 'Long Text',
                                                'date' => 'Date',
                                                'time' => 'Time',
                                            ])
                                            ->live(),

                                        Forms\Components\TextInput::make('unit')
                                            ->label('Unit')
                                            ->maxLength(50)
                                            ->helperText('e.g., nm, J, ms, %'),

                                        Forms\Components\Toggle::make('required')
                                            ->label('Required')
                                            ->default(false),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('label.en')
                                            ->label('Label (English)')
                                            ->required()
                                            ->maxLength(200),

                                        Forms\Components\TextInput::make('label.ar')
                                            ->label('Label (Arabic)')
                                            ->maxLength(200),
                                    ]),

                                Forms\Components\Grid::make(4)
                                    ->schema([
                                        Forms\Components\TextInput::make('min')
                                            ->label('Min Value')
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('max')
                                            ->label('Max Value')
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('step')
                                            ->label('Step')
                                            ->numeric()
                                            ->visible(fn (Forms\Get $get) => in_array($get('type'), ['number', 'decimal'])),

                                        Forms\Components\TextInput::make('default_value')
                                            ->label('Default Value'),
                                    ]),

                                Forms\Components\Repeater::make('options')
                                    ->label('Options')
                                    ->schema([
                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('value')
                                                    ->label('Value')
                                                    ->required(),

                                                Forms\Components\TextInput::make('label.en')
                                                    ->label('Label (EN)')
                                                    ->required(),

                                                Forms\Components\TextInput::make('label.ar')
                                                    ->label('Label (AR)'),
                                            ]),
                                    ])
                                    ->visible(fn (Forms\Get $get) => $get('type') === 'select')
                                    ->collapsible()
                                    ->defaultItems(0)
                                    ->columnSpanFull(),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('help_text.en')
                                            ->label('Help Text (English)')
                                            ->maxLength(500),

                                        Forms\Components\TextInput::make('help_text.ar')
                                            ->label('Help Text (Arabic)')
                                            ->maxLength(500),
                                    ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['label']['en'] ?? $state['key'] ?? 'New Parameter')
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
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('template_code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('service_category')
                    ->label('Category')
                    ->searchable()
                    ->badge(),

                Tables\Columns\TextColumn::make('parameters')
                    ->label('Parameters')
                    ->formatStateUsing(fn ($state) => count($state ?? []) . ' parameters')
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('service_category')
                    ->options([
                        'laser' => 'Laser',
                        'ipl' => 'IPL',
                        'injectable' => 'Injectable',
                        'skin_rejuvenation' => 'Skin Rejuvenation',
                        'body' => 'Body',
                    ]),

                Tables\Filters\TernaryFilter::make('is_system')
                    ->label('System Template'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->label('Duplicate')
                    ->beforeReplicaSaved(function (ParameterTemplate $replica): void {
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
            'index' => Pages\ListParameterTemplates::route('/'),
            'create' => Pages\CreateParameterTemplate::route('/create'),
            'view' => Pages\ViewParameterTemplate::route('/{record}'),
            'edit' => Pages\EditParameterTemplate::route('/{record}/edit'),
        ];
    }
}
