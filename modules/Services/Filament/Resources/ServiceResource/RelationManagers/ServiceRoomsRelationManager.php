<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Models\Room;
use Filament\Notifications\Notification;

class ServiceRoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    protected static ?string $title = 'Rooms';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_id')
                    ->label(__('services::services.rooms.room'))
                    ->options(
                        Room::query()
                            ->active()
                            ->bookable()
                            ->get()
                            ->mapWithKeys(fn ($room) => [$room->id => $room->getDisplayName()])
                    )
                    ->required()
                    ->searchable(),

                Forms\Components\Toggle::make('is_primary')
                    ->label(__('services::services.rooms.is_primary'))
                    ->helperText(__('services::services.rooms.is_primary_help'))
                    ->default(false),

                Forms\Components\TextInput::make('priority')
                    ->label(__('services::services.rooms.priority'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('services::services.rooms.priority_help')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->select([
                'rooms.id',
                'rooms.tenant_id',
                'rooms.branch_id',
                'rooms.name',
                'rooms.code',
                'rooms.room_type',
                'rooms.is_active',
                'rooms.is_bookable',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('services::services.rooms.room'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('services::services.rooms.branch'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_primary')
                    ->label(__('services::services.rooms.is_primary'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('pivot.priority')
                    ->label(__('services::services.rooms.priority'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('room_type')
                    ->label(__('services::services.rooms.type'))
                    ->formatStateUsing(fn ($state) => Room::TYPES[$state] ?? ucfirst($state)),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->active()->bookable()->select([
                        'rooms.id',
                        'rooms.branch_id',
                        'rooms.name',
                        'rooms.code',
                    ]))
                    ->recordTitle(fn (Room $record) => $record->getDisplayName())
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('services::services.rooms.is_primary'))
                            ->default(false),
                        Forms\Components\TextInput::make('priority')
                            ->label(__('services::services.rooms.priority'))
                            ->numeric()
                            ->default(0),
                    ])
                    ->after(function ($data, $record) {
                        // If this room is set as primary, unset other primary rooms
                        if ($data['is_primary'] ?? false) {
                            $this->ownerRecord->rooms()
                                ->wherePivot('room_id', '!=', $record->id)
                                ->updateExistingPivot(
                                    $this->ownerRecord->rooms()
                                        ->wherePivot('is_primary', true)
                                        ->pluck('rooms.id')
                                        ->toArray(),
                                    ['is_primary' => false]
                                );
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('setPrimary')
                    ->label(__('services::services.rooms.set_as_primary'))
                    ->icon('heroicon-o-star')
                    ->color('warning')
                    ->visible(fn ($record) => !$record->pivot->is_primary)
                    ->action(function ($record) {
                        // Unset current primary
                        $this->ownerRecord->rooms()->updateExistingPivot(
                            $this->ownerRecord->rooms()->wherePivot('is_primary', true)->pluck('rooms.id')->toArray(),
                            ['is_primary' => false]
                        );

                        // Set new primary
                        $this->ownerRecord->rooms()->updateExistingPivot($record->id, ['is_primary' => true]);

                        Notification::make()
                            ->success()
                            ->title(__('services::services.rooms.primary_updated'))
                            ->send();
                    }),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ])
            ->defaultSort('pivot_priority');
    }
}
