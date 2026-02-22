<?php

namespace Modules\Equipment\Filament\Resources;

use App\Traits\ChecksResourcePermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Room;
use Modules\Equipment\Filament\Resources\EquipmentResource\Pages;
use Modules\Equipment\Filament\Resources\EquipmentResource\RelationManagers;
use Modules\Equipment\Models\Equipment;
use Modules\Equipment\Models\EquipmentType;

class EquipmentResource extends Resource
{
    use ChecksResourcePermissions;

    protected static ?string $model = Equipment::class;

    protected static ?string $moduleCode = 'equipment';

    protected static ?string $permissionKey = 'equipment';

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return __('equipment::equipment.equipment');
    }

    public static function getModelLabel(): string
    {
        return __('equipment::equipment.equipment_item');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipment::equipment.equipment');
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $maintenanceDue = static::getModel()::where('next_maintenance_at', '<=', now())->count();
            return $maintenanceDue > 0 ? (string) $maintenanceDue : null;
        } catch (\Exception $e) {
            // Table may not exist in current schema context
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('equipment::equipment.details'))
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('equipment::equipment.name'))
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('equipment_type_id')
                                    ->label(__('equipment::equipment.type'))
                                    ->relationship('type', 'name')
                                    ->getOptionLabelFromRecordUsing(fn (EquipmentType $record) => $record->getTranslation('name', app()->getLocale()))
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Name (English)')
                                            ->required(),
                                        Forms\Components\Select::make('category')
                                            ->options(EquipmentType::CATEGORIES)
                                            ->required(),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Select::make('branch_id')
                                            ->label(__('equipment::equipment.branch'))
                                            ->options(Branch::where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->default(fn () => current_branch_id())
                                            ->disabled(fn () => current_branch_id() !== null)
                                            ->dehydrated()
                                            ->live(),

                                        Forms\Components\Select::make('room_id')
                                            ->label(__('equipment::equipment.room'))
                                            ->options(fn (Forms\Get $get) => Room::where('branch_id', $get('branch_id'))->where('is_active', true)->pluck('name', 'id'))
                                            ->searchable()
                                            ->visible(fn (Forms\Get $get) => filled($get('branch_id'))),
                                    ]),

                                Forms\Components\TextInput::make('serial_number')
                                    ->label(__('equipment::equipment.serial_number'))
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Section::make(__('equipment::equipment.purchase_info'))
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('purchase_date')
                                            ->label(__('equipment::equipment.purchase_date')),

                                        Forms\Components\TextInput::make('purchase_price_minor')
                                            ->label(__('equipment::equipment.purchase_price'))
                                            ->numeric()
                                            ->prefix(current_currency())
                                            ->helperText('Enter price in piasters'),
                                    ]),

                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\DatePicker::make('warranty_expiry')
                                            ->label(__('equipment::equipment.warranty_expiry')),

                                        Forms\Components\TextInput::make('depreciation_years')
                                            ->label(__('equipment::equipment.depreciation_years'))
                                            ->numeric()
                                            ->suffix('years'),
                                    ]),
                            ])
                            ->collapsed(),

                        Forms\Components\Section::make(__('equipment::equipment.notes'))
                            ->schema([
                                Forms\Components\Textarea::make('notes')
                                    ->label(__('equipment::equipment.notes'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('equipment::equipment.status'))
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->label(__('equipment::equipment.status'))
                                    ->options(Equipment::STATUSES)
                                    ->required()
                                    ->default(Equipment::STATUS_ACTIVE),

                                Forms\Components\Placeholder::make('code')
                                    ->label(__('equipment::equipment.code'))
                                    ->content(fn (?Equipment $record): string => $record?->code ?? 'Auto-generated'),
                            ]),

                        Forms\Components\Section::make(__('equipment::equipment.maintenance'))
                            ->schema([
                                Forms\Components\DateTimePicker::make('last_maintenance_at')
                                    ->label(__('equipment::equipment.last_maintenance')),

                                Forms\Components\DateTimePicker::make('next_maintenance_at')
                                    ->label(__('equipment::equipment.next_maintenance')),
                            ]),

                        Forms\Components\Section::make(__('equipment::equipment.shot_counter'))
                            ->schema([
                                Forms\Components\TextInput::make('total_shots_fired')
                                    ->label(__('equipment::equipment.total_shots'))
                                    ->numeric()
                                    ->default(0)
                                    ->disabled(),

                                Forms\Components\Placeholder::make('shots_remaining')
                                    ->label(__('equipment::equipment.shots_remaining'))
                                    ->content(function (?Equipment $record): string {
                                        if (!$record || !$record->type || !$record->type->max_shots) {
                                            return 'N/A';
                                        }
                                        return number_format($record->shots_remaining) . ' / ' . number_format($record->type->max_shots);
                                    }),
                            ])
                            ->visible(fn (?Equipment $record) => $record?->type?->max_shots),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label(__('equipment::equipment.code'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('equipment::equipment.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type.name')
                    ->label(__('equipment::equipment.type'))
                    ->getStateUsing(fn ($record) => $record->type?->getTranslation('name', app()->getLocale()))
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('equipment::equipment.branch'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('room.name')
                    ->label(__('equipment::equipment.room'))
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_shots_fired')
                    ->label(__('equipment::equipment.shots'))
                    ->numeric()
                    ->formatStateUsing(function (Equipment $record) {
                        if (!$record->type || !$record->type->max_shots) {
                            return number_format($record->total_shots_fired);
                        }
                        return number_format($record->total_shots_fired) . ' / ' . number_format($record->type->max_shots);
                    })
                    ->color(function (Equipment $record) {
                        $percentage = $record->shots_percentage;
                        if ($percentage === null) return null;
                        if ($percentage >= 90) return 'danger';
                        if ($percentage >= 75) return 'warning';
                        return null;
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('equipment::equipment.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Equipment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Equipment::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\IconColumn::make('is_maintenance_due')
                    ->label(__('equipment::equipment.maintenance_due'))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-check-circle')
                    ->falseColor('success')
                    ->getStateUsing(fn (Equipment $record) => $record->is_maintenance_due),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(Equipment::STATUSES),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label(__('equipment::equipment.branch'))
                    ->relationship('branch', 'name'),

                Tables\Filters\SelectFilter::make('equipment_type_id')
                    ->label(__('equipment::equipment.type'))
                    ->relationship('type', 'name'),

                Tables\Filters\Filter::make('maintenance_due')
                    ->label(__('equipment::equipment.maintenance_due'))
                    ->query(fn (Builder $query): Builder => $query->where('next_maintenance_at', '<=', now())),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('log_maintenance')
                        ->label(__('equipment::equipment.log_maintenance'))
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->form([
                            Forms\Components\Select::make('type')
                                ->label(__('equipment::equipment.maintenance_type'))
                                ->options(\Modules\Equipment\Models\EquipmentMaintenanceLog::TYPES)
                                ->required(),
                            Forms\Components\Textarea::make('description')
                                ->label(__('equipment::equipment.description'))
                                ->rows(2),
                            Forms\Components\TextInput::make('performed_by')
                                ->label(__('equipment::equipment.performed_by')),
                            Forms\Components\TextInput::make('cost_minor')
                                ->label(__('equipment::equipment.cost'))
                                ->numeric()
                                ->prefix(current_currency()),
                            Forms\Components\DatePicker::make('next_due_date')
                                ->label(__('equipment::equipment.next_due_date')),
                        ])
                        ->action(function (Equipment $record, array $data) {
                            $record->maintenanceLogs()->create([
                                'type' => $data['type'],
                                'description' => $data['description'],
                                'performed_by' => $data['performed_by'],
                                'cost_minor' => $data['cost_minor'],
                                'next_due_date' => $data['next_due_date'],
                                'performed_at' => now(),
                            ]);
                        }),
                    Tables\Actions\Action::make('record_shots')
                        ->label(__('equipment::equipment.record_shots'))
                        ->icon('heroicon-o-bolt')
                        ->color('info')
                        ->visible(fn (Equipment $record) => $record->type?->max_shots)
                        ->form([
                            Forms\Components\TextInput::make('shots_count')
                                ->label(__('equipment::equipment.shots_count'))
                                ->numeric()
                                ->required()
                                ->minValue(1),
                            Forms\Components\TextInput::make('energy_setting')
                                ->label(__('equipment::equipment.energy_setting')),
                            Forms\Components\TextInput::make('spot_size')
                                ->label(__('equipment::equipment.spot_size')),
                            Forms\Components\Textarea::make('notes')
                                ->label(__('equipment::equipment.notes'))
                                ->rows(2),
                        ])
                        ->action(function (Equipment $record, array $data) {
                            $record->recordShots($data['shots_count'], null, $data);
                        }),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('equipment::equipment.details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('code')
                            ->label(__('equipment::equipment.code'))
                            ->copyable(),
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('equipment::equipment.name')),
                        Infolists\Components\TextEntry::make('type.name')
                            ->label(__('equipment::equipment.type')),
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label(__('equipment::equipment.branch')),
                        Infolists\Components\TextEntry::make('room.name')
                            ->label(__('equipment::equipment.room'))
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('serial_number')
                            ->label(__('equipment::equipment.serial_number'))
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('status')
                            ->label(__('equipment::equipment.status'))
                            ->badge()
                            ->formatStateUsing(fn (string $state) => Equipment::STATUSES[$state] ?? $state)
                            ->color(fn (string $state) => Equipment::STATUS_COLORS[$state] ?? 'gray'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MaintenanceLogsRelationManager::class,
            RelationManagers\ShotLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEquipment::route('/'),
            'create' => Pages\CreateEquipment::route('/create'),
            'view' => Pages\ViewEquipment::route('/{record}'),
            'edit' => Pages\EditEquipment::route('/{record}/edit'),
        ];
    }
}
