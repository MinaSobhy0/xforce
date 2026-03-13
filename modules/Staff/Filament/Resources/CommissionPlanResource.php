<?php

namespace Modules\Staff\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\CommissionPlan;
use Modules\Staff\Filament\Resources\CommissionPlanResource\Pages;
use Modules\Staff\Filament\Resources\CommissionPlanResource\RelationManagers;

class CommissionPlanResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = CommissionPlan::class;

    protected static ?string $moduleCode = 'staff';

    protected static ?string $permissionKey = 'commission_plans';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'HR';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('staff::commission.navigation.plans');
    }

    public static function getModelLabel(): string
    {
        return __('staff::commission.labels.plan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('staff::commission.labels.plans');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('staff::commission.sections.plan_details'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('staff::commission.fields.name'))
                            ->required()
                            ->maxLength(100),

                        Forms\Components\Textarea::make('description')
                            ->label(__('staff::commission.fields.description'))
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('staff::commission.fields.is_active'))
                            ->default(true),
                    ]),

                Forms\Components\Section::make(__('staff::commission.sections.default_commission'))
                    ->description(__('staff::commission.sections.default_commission_description'))
                    ->schema([
                        Forms\Components\Select::make('commission_type')
                            ->label(__('staff::commission.fields.commission_type'))
                            ->options(CommissionPlan::TYPES)
                            ->default(CommissionPlan::TYPE_PERCENTAGE)
                            ->required()
                            ->live()
                            ->helperText(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_TIERED
                                ? __('staff::commission.help.tiered_requires_rules')
                                : null),

                        Forms\Components\TextInput::make('default_percentage')
                            ->label(__('staff::commission.fields.percentage'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(10)
                            ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_PERCENTAGE),

                        Forms\Components\TextInput::make('default_flat_amount')
                            ->label(__('staff::commission.fields.flat_amount'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01)
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_FLAT),

                        Forms\Components\Placeholder::make('tiered_info')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString(
                                '<div class="text-sm text-warning-600 dark:text-warning-400 bg-warning-50 dark:bg-warning-950 p-3 rounded-lg">' .
                                '<strong>' . __('staff::commission.help.tiered_title') . '</strong><br>' .
                                __('staff::commission.help.tiered_description') .
                                '</div>'
                            ))
                            ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_TIERED),
                    ]),

                Forms\Components\Section::make(__('staff::commission.sections.new_patient_commission'))
                    ->description(__('staff::commission.sections.new_patient_commission_description'))
                    ->schema([
                        Forms\Components\Toggle::make('new_patient_commission_enabled')
                            ->label(__('staff::commission.fields.new_patient_enabled'))
                            ->default(false)
                            ->live(),

                        Forms\Components\Select::make('new_patient_commission_type')
                            ->label(__('staff::commission.fields.commission_type'))
                            ->options([
                                CommissionPlan::TYPE_PERCENTAGE => __('staff::commission.commission_types.percentage'),
                                CommissionPlan::TYPE_FLAT => __('staff::commission.commission_types.flat'),
                            ])
                            ->default(CommissionPlan::TYPE_PERCENTAGE)
                            ->live()
                            ->visible(fn (Forms\Get $get) => $get('new_patient_commission_enabled')),

                        Forms\Components\TextInput::make('new_patient_percentage')
                            ->label(__('staff::commission.fields.percentage'))
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('new_patient_commission_enabled') && $get('new_patient_commission_type') === CommissionPlan::TYPE_PERCENTAGE),

                        Forms\Components\TextInput::make('new_patient_flat_amount')
                            ->label(__('staff::commission.fields.flat_amount'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01)
                            ->default(0)
                            ->visible(fn (Forms\Get $get) => $get('new_patient_commission_enabled') && $get('new_patient_commission_type') === CommissionPlan::TYPE_FLAT),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('staff::commission.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::commission.fields.commission_type'))
                    ->formatStateUsing(fn ($state) => CommissionPlan::TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('formatted_default')
                    ->label(__('staff::commission.fields.default_value'))
                    ->getStateUsing(fn (CommissionPlan $record) => $record->formatted_default),

                Tables\Columns\TextColumn::make('formatted_new_patient_commission')
                    ->label(__('staff::commission.fields.new_patient_commission'))
                    ->getStateUsing(fn (CommissionPlan $record) => $record->formatted_new_patient_commission)
                    ->badge()
                    ->color(fn (CommissionPlan $record) => $record->hasNewPatientCommission() ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('staff_profiles_count')
                    ->label(__('staff::commission.fields.assigned_staff'))
                    ->counts('staffProfiles')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('service_rules_count')
                    ->label(__('staff::commission.fields.service_rules'))
                    ->counts('serviceRules')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::commission.fields.is_active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('staff::commission.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('staff::commission.fields.is_active')),
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
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ServiceRulesRelationManager::class,
            RelationManagers\StaffProfilesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissionPlans::route('/'),
            'create' => Pages\CreateCommissionPlan::route('/create'),
            'view' => Pages\ViewCommissionPlan::route('/{record}'),
            'edit' => Pages\EditCommissionPlan::route('/{record}/edit'),
        ];
    }
}
