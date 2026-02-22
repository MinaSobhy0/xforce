<?php

namespace Modules\Staff\Filament\Resources\CommissionPlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\CommissionPlan;
use Modules\Staff\Models\CommissionPlanRule;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;

class ServiceRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'serviceRules';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('staff::commission.labels.service_rules');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_id')
                    ->label(__('staff::commission.fields.service'))
                    ->relationship('service', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText(__('staff::commission.help.service_specific')),

                Forms\Components\Select::make('service_category_id')
                    ->label(__('staff::commission.fields.category'))
                    ->relationship('serviceCategory', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText(__('staff::commission.help.category_fallback')),

                Forms\Components\Select::make('commission_type')
                    ->label(__('staff::commission.fields.commission_type'))
                    ->options(CommissionPlan::TYPES)
                    ->default(CommissionPlan::TYPE_PERCENTAGE)
                    ->required()
                    ->reactive(),

                Forms\Components\TextInput::make('percentage')
                    ->label(__('staff::commission.fields.percentage'))
                    ->numeric()
                    ->suffix('%')
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), [CommissionPlan::TYPE_PERCENTAGE, CommissionPlan::TYPE_TIERED])),

                Forms\Components\TextInput::make('flat_amount')
                    ->label(__('staff::commission.fields.flat_amount'))
                    ->numeric()
                    ->prefix(current_currency())
                    ->step(0.01)
                    ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_FLAT),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('tier_from')
                            ->label(__('staff::commission.fields.tier_from'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01),

                        Forms\Components\TextInput::make('tier_to')
                            ->label(__('staff::commission.fields.tier_to'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('commission_type') === CommissionPlan::TYPE_TIERED),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('staff::commission.fields.is_active'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('staff::commission.fields.applies_to'))
                    ->getStateUsing(fn (CommissionPlanRule $record) => $record->display_name),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::commission.fields.commission_type'))
                    ->formatStateUsing(fn ($state) => CommissionPlan::TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('formatted_value')
                    ->label(__('staff::commission.fields.value'))
                    ->getStateUsing(fn (CommissionPlanRule $record) => $record->formatted_value),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::commission.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['flat_amount_minor'] = (int) round(($data['flat_amount'] ?? 0) * 100);
                        $data['tier_from_minor'] = (int) round(($data['tier_from'] ?? 0) * 100);
                        $data['tier_to_minor'] = (int) round(($data['tier_to'] ?? 0) * 100);
                        unset($data['flat_amount'], $data['tier_from'], $data['tier_to']);
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['flat_amount'] = ($data['flat_amount_minor'] ?? 0) / 100;
                        $data['tier_from'] = ($data['tier_from_minor'] ?? 0) / 100;
                        $data['tier_to'] = ($data['tier_to_minor'] ?? 0) / 100;
                        return $data;
                    })
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['flat_amount_minor'] = (int) round(($data['flat_amount'] ?? 0) * 100);
                        $data['tier_from_minor'] = (int) round(($data['tier_from'] ?? 0) * 100);
                        $data['tier_to_minor'] = (int) round(($data['tier_to'] ?? 0) * 100);
                        unset($data['flat_amount'], $data['tier_from'], $data['tier_to']);
                        return $data;
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
