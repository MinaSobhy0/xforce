<?php

namespace Modules\Equipment\Filament\Resources\EquipmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use App\Filament\Resources\RelationManagers\BaseRelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Equipment\Models\EquipmentMaintenanceLog;

class MaintenanceLogsRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'maintenanceLogs';

    protected static ?string $title = 'Maintenance History';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label(__('equipment::equipment.maintenance_type'))
                    ->options(EquipmentMaintenanceLog::TYPES)
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label(__('equipment::equipment.description'))
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('performed_by')
                            ->label(__('equipment::equipment.performed_by')),

                        Forms\Components\TextInput::make('cost_minor')
                            ->label(__('equipment::equipment.cost'))
                            ->numeric()
                            ->prefix(current_currency()),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DateTimePicker::make('performed_at')
                            ->label(__('equipment::equipment.performed_at'))
                            ->required()
                            ->default(now()),

                        Forms\Components\DatePicker::make('next_due_date')
                            ->label(__('equipment::equipment.next_due_date')),
                    ]),

                Forms\Components\KeyValue::make('parts_replaced')
                    ->label(__('equipment::equipment.parts_replaced'))
                    ->keyLabel('Part')
                    ->valueLabel('Details')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('equipment::equipment.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EquipmentMaintenanceLog::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('equipment::equipment.description'))
                    ->limit(50)
                    ->wrap(),

                Tables\Columns\TextColumn::make('performed_by')
                    ->label(__('equipment::equipment.performed_by')),

                Tables\Columns\TextColumn::make('cost')
                    ->label(__('equipment::equipment.cost'))
                    ->money(current_currency()),

                Tables\Columns\TextColumn::make('performed_at')
                    ->label(__('equipment::equipment.performed_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('next_due_date')
                    ->label(__('equipment::equipment.next_due'))
                    ->date()
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : null),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(EquipmentMaintenanceLog::TYPES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('performed_at', 'desc');
    }
}
