<?php

namespace Modules\Services\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\ServiceCategory;
use Modules\Services\Models\ParameterTemplate;
use Modules\Services\Filament\Resources\ServiceCategoryResource\Pages;
use Modules\Services\Filament\Resources\ServiceCategoryResource\RelationManagers;
use Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Builder;

class ServiceCategoryResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = ServiceCategory::class;

    protected static ?string $moduleCode = 'services';

    protected static ?string $permissionKey = 'services';

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('services::services.navigation.categories');
    }

    public static function getModelLabel(): string
    {
        return __('services::services.labels.category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('services::services.labels.categories');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Category')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(__('services::services.tabs.basic_info'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\Section::make()
                                    ->schema([
                                        Forms\Components\Select::make('parent_id')
                                            ->label(__('services::services.fields.parent_category'))
                                            ->relationship(
                                                'parent',
                                                'id',
                                                fn (Builder $query) => $query->roots()->active()
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->translated_name)
                                            ->searchable()
                                            ->preload()
                                            ->nullable(),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\TextInput::make('name.en')
                                                    ->label(__('services::services.fields.name') . ' (English)')
                                                    ->required()
                                                    ->maxLength(100),

                                                Forms\Components\TextInput::make('name.ar')
                                                    ->label(__('services::services.fields.name') . ' (Arabic)')
                                                    ->required()
                                                    ->maxLength(100),
                                            ]),

                                        Forms\Components\Grid::make(2)
                                            ->schema([
                                                Forms\Components\Textarea::make('description.en')
                                                    ->label(__('services::services.fields.description') . ' (English)')
                                                    ->rows(3),

                                                Forms\Components\Textarea::make('description.ar')
                                                    ->label(__('services::services.fields.description') . ' (Arabic)')
                                                    ->rows(3),
                                            ]),

                                        Forms\Components\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('icon')
                                                    ->label('Icon')
                                                    ->placeholder('heroicon-o-sparkles')
                                                    ->maxLength(50),

                                                Forms\Components\ColorPicker::make('color')
                                                    ->label('Color'),

                                                Forms\Components\TextInput::make('sort_order')
                                                    ->label('Sort Order')
                                                    ->numeric()
                                                    ->default(0),
                                            ]),

                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('services::services.fields.is_active'))
                                            ->default(true),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('services::services.tabs.dynamic_parameters'))
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema([
                                Forms\Components\Section::make(__('services::services.sections.default_template'))
                                    ->description(__('services::services.sections.default_template_description'))
                                    ->schema([
                                        Forms\Components\Select::make('default_parameter_template_id')
                                            ->label(__('services::services.fields.default_parameter_template'))
                                            ->relationship(
                                                'defaultParameterTemplate',
                                                'template_name',
                                                fn (Builder $query) => $query->active()
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText(__('services::services.fields.default_parameter_template_help'))
                                            ->live()
                                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('_template_preview', $state)),

                                        Forms\Components\Placeholder::make('template_preview')
                                            ->label(__('services::services.fields.template_preview'))
                                            ->content(function (Forms\Get $get) {
                                                $templateId = $get('default_parameter_template_id');
                                                if (!$templateId) {
                                                    return __('services::services.placeholders.no_template_selected');
                                                }

                                                $template = ParameterTemplate::find($templateId);
                                                if (!$template) {
                                                    return __('services::services.placeholders.template_not_found');
                                                }

                                                $params = $template->getParameterDefinitions();
                                                if (empty($params)) {
                                                    return __('services::services.placeholders.no_parameters');
                                                }

                                                $preview = collect($params)->map(function ($param) {
                                                    $label = $param['label'] ?? $param['key'] ?? 'Unknown';
                                                    // Handle translatable labels (array format)
                                                    if (is_array($label)) {
                                                        $label = $label[app()->getLocale()] ?? $label['en'] ?? array_values($label)[0] ?? 'Unknown';
                                                    }
                                                    $type = $param['type'] ?? 'text';
                                                    $required = ($param['required'] ?? false) ? '*' : '';
                                                    return "- {$label} ({$type}){$required}";
                                                })->join("\n");

                                                return $preview;
                                            })
                                            ->visible(fn (Forms\Get $get) => $get('default_parameter_template_id')),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make(__('services::services.tabs.accounting'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\Section::make(__('services::services.sections.revenue_accounts'))
                                    ->description(__('services::services.sections.revenue_accounts_description'))
                                    ->schema([
                                        Forms\Components\Select::make('unearned_revenue_account_id')
                                            ->label(__('services::services.fields.unearned_revenue_account'))
                                            ->relationship(
                                                'unearnedRevenueAccount',
                                                'name',
                                                fn (Builder $query) => $query
                                                    ->where('is_active', true)
                                                    ->whereIn('type', [ChartOfAccount::TYPE_CURRENT_LIABILITY])
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->code} - {$record->getTranslation('name', app()->getLocale())}")
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText(__('services::services.fields.unearned_revenue_account_help')),

                                        Forms\Components\Select::make('service_revenue_account_id')
                                            ->label(__('services::services.fields.service_revenue_account'))
                                            ->relationship(
                                                'serviceRevenueAccount',
                                                'name',
                                                fn (Builder $query) => $query
                                                    ->where('is_active', true)
                                                    ->whereIn('type', [ChartOfAccount::TYPE_INCOME, ChartOfAccount::TYPE_OTHER_INCOME])
                                            )
                                            ->getOptionLabelFromRecordUsing(fn (ChartOfAccount $record) => "{$record->code} - {$record->getTranslation('name', app()->getLocale())}")
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->helperText(__('services::services.fields.service_revenue_account_help')),
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
                Tables\Columns\TextColumn::make('translated_name')
                    ->label(__('services::services.fields.name'))
                    ->searchable(['name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.translated_name')
                    ->label(__('services::services.fields.parent_category'))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->sortable(),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Subcategories')
                    ->counts('children')
                    ->sortable(),

                Tables\Columns\TextColumn::make('defaultParameterTemplate.template_name')
                    ->label(__('services::services.fields.default_template'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('services::services.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('services::services.fields.parent_category'))
                    ->relationship('parent', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->translated_name),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('services::services.fields.is_active')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ServiceCategory $record) => $record->canDelete()),
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
            RelationManagers\CategoryEquipmentRelationManager::class,
            RelationManagers\CategoryStaffRelationManager::class,
            RelationManagers\CategoryRoomsRelationManager::class,
            RelationManagers\CategoryConsumablesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceCategories::route('/'),
            'create' => Pages\CreateServiceCategory::route('/create'),
            'view' => Pages\ViewServiceCategory::route('/{record}'),
            'edit' => Pages\EditServiceCategory::route('/{record}/edit'),
        ];
    }
}
