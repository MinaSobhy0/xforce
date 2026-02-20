<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Staff\Models\StaffCommission;
use Modules\Treatments\Models\Treatment;
use Modules\Treatments\Models\TreatmentCategory;

class CommissionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRules';

    protected static ?string $title = 'Commission Rules';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('treatment_id')
                    ->label(__('staff::staff.fields.treatment'))
                    ->relationship('treatment', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Treatment $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('treatment_category_id')
                    ->label(__('staff::staff.fields.category'))
                    ->relationship('treatmentCategory', 'id')
                    ->getOptionLabelFromRecordUsing(fn (TreatmentCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Applies to all treatments in this category if no specific treatment is selected'),

                Forms\Components\Select::make('commission_type')
                    ->label(__('staff::staff.fields.commission_type'))
                    ->options(StaffCommission::TYPES)
                    ->required()
                    ->reactive(),

                Forms\Components\TextInput::make('flat_amount_minor')
                    ->label(__('staff::staff.fields.flat_amount'))
                    ->numeric()
                    ->suffix('cents')
                    ->visible(fn (Forms\Get $get) => $get('commission_type') === StaffCommission::TYPE_FLAT),

                Forms\Components\TextInput::make('percentage')
                    ->label(__('staff::staff.fields.percentage'))
                    ->numeric()
                    ->suffix('%')
                    ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), [StaffCommission::TYPE_PERCENTAGE, StaffCommission::TYPE_TIERED])),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('tier_from_minor')
                            ->label(__('staff::staff.fields.tier_from'))
                            ->numeric()
                            ->suffix('cents'),

                        Forms\Components\TextInput::make('tier_to_minor')
                            ->label(__('staff::staff.fields.tier_to'))
                            ->numeric()
                            ->suffix('cents'),
                    ])
                    ->visible(fn (Forms\Get $get) => $get('commission_type') === StaffCommission::TYPE_TIERED),

                Forms\Components\Toggle::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('treatment.name')
                    ->label(__('staff::staff.fields.treatment'))
                    ->getStateUsing(fn (StaffCommission $record) => $record->treatment?->getTranslation('name', app()->getLocale()))
                    ->placeholder('All treatments'),

                Tables\Columns\TextColumn::make('treatmentCategory.name')
                    ->label(__('staff::staff.fields.category'))
                    ->getStateUsing(fn (StaffCommission $record) => $record->treatmentCategory?->getTranslation('name', app()->getLocale()))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::staff.fields.type'))
                    ->formatStateUsing(fn ($state) => StaffCommission::TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('staff::staff.fields.rule')),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
