<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Services\Models\Service;
use Modules\Services\Models\ServiceCategory;
use Modules\Staff\Models\StaffCommission;

class CommissionRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionRules';

    protected static ?string $title = 'Commission Rules';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_id')
                    ->label(__('staff::staff.fields.service'))
                    ->relationship('service', 'id')
                    ->getOptionLabelFromRecordUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Forms\Components\Select::make('service_category_id')
                    ->label(__('staff::staff.fields.category'))
                    ->relationship('serviceCategory', 'id')
                    ->getOptionLabelFromRecordUsing(fn (ServiceCategory $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Applies to all services in this category if no specific service is selected'),

                Forms\Components\Select::make('commission_type')
                    ->label(__('staff::staff.fields.commission_type'))
                    ->options(StaffCommission::TYPES)
                    ->required()
                    ->reactive(),

                Forms\Components\TextInput::make('flat_amount')
                    ->label(__('staff::staff.fields.flat_amount'))
                    ->numeric()
                    ->prefix(current_currency())
                    ->step(0.01)
                    ->visible(fn (Forms\Get $get) => $get('commission_type') === StaffCommission::TYPE_FLAT),

                Forms\Components\TextInput::make('percentage')
                    ->label(__('staff::staff.fields.percentage'))
                    ->numeric()
                    ->suffix('%')
                    ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), [StaffCommission::TYPE_PERCENTAGE, StaffCommission::TYPE_TIERED])),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('tier_from')
                            ->label(__('staff::staff.fields.tier_from'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01),

                        Forms\Components\TextInput::make('tier_to')
                            ->label(__('staff::staff.fields.tier_to'))
                            ->numeric()
                            ->prefix(current_currency())
                            ->step(0.01),
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
                Tables\Columns\TextColumn::make('service.name')
                    ->label(__('staff::staff.fields.service'))
                    ->getStateUsing(fn (StaffCommission $record) => $record->service?->getTranslation('name', app()->getLocale()))
                    ->placeholder('All services'),

                Tables\Columns\TextColumn::make('serviceCategory.name')
                    ->label(__('staff::staff.fields.category'))
                    ->getStateUsing(fn (StaffCommission $record) => $record->serviceCategory?->getTranslation('name', app()->getLocale()))
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('commission_type')
                    ->label(__('staff::staff.fields.type'))
                    ->formatStateUsing(fn ($state) => StaffCommission::TYPES[$state] ?? $state)
                    ->badge(),

                Tables\Columns\TextColumn::make('value')
                    ->label(__('staff::staff.fields.amount'))
                    ->getStateUsing(function (StaffCommission $record) {
                        if ($record->commission_type === StaffCommission::TYPE_FLAT) {
                            return format_money($record->flat_amount_minor ?? 0);
                        }
                        if ($record->commission_type === StaffCommission::TYPE_PERCENTAGE) {
                            return ($record->percentage ?? 0) . '%';
                        }
                        if ($record->commission_type === StaffCommission::TYPE_TIERED) {
                            return ($record->percentage ?? 0) . '% (' . format_money($record->tier_from_minor ?? 0) . ' - ' . format_money($record->tier_to_minor ?? 0) . ')';
                        }
                        return '-';
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
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
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->infolist([
                        Infolists\Components\Section::make(__('staff::staff.sections.commission_rule'))
                            ->schema([
                                Infolists\Components\TextEntry::make('service.name')
                                    ->label(__('staff::staff.fields.service'))
                                    ->getStateUsing(fn (StaffCommission $record) => $record->service?->getTranslation('name', app()->getLocale()))
                                    ->placeholder(__('staff::staff.messages.all_services')),
                                Infolists\Components\TextEntry::make('serviceCategory.name')
                                    ->label(__('staff::staff.fields.category'))
                                    ->getStateUsing(fn (StaffCommission $record) => $record->serviceCategory?->getTranslation('name', app()->getLocale()))
                                    ->placeholder('-'),
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('staff::staff.fields.is_active'))
                                    ->boolean(),
                            ])->columns(3),

                        Infolists\Components\Section::make(__('staff::staff.sections.commission_type'))
                            ->schema([
                                Infolists\Components\TextEntry::make('commission_type')
                                    ->label(__('staff::staff.fields.type'))
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => StaffCommission::TYPES[$state] ?? $state),
                                Infolists\Components\TextEntry::make('flat_amount_minor')
                                    ->label(__('staff::staff.fields.flat_amount'))
                                    ->formatStateUsing(fn ($state) => format_money($state ?? 0))
                                    ->visible(fn (StaffCommission $record) => $record->commission_type === StaffCommission::TYPE_FLAT),
                                Infolists\Components\TextEntry::make('percentage')
                                    ->label(__('staff::staff.fields.percentage'))
                                    ->suffix('%')
                                    ->visible(fn (StaffCommission $record) => in_array($record->commission_type, [StaffCommission::TYPE_PERCENTAGE, StaffCommission::TYPE_TIERED])),
                            ])->columns(3),

                        Infolists\Components\Section::make(__('staff::staff.sections.tier_range'))
                            ->schema([
                                Infolists\Components\TextEntry::make('tier_from_minor')
                                    ->label(__('staff::staff.fields.tier_from'))
                                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),
                                Infolists\Components\TextEntry::make('tier_to_minor')
                                    ->label(__('staff::staff.fields.tier_to'))
                                    ->formatStateUsing(fn ($state) => format_money($state ?? 0)),
                            ])->columns(2)
                            ->visible(fn (StaffCommission $record) => $record->commission_type === StaffCommission::TYPE_TIERED),
                    ]),

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
            ->bulkActions([]);
    }
}
