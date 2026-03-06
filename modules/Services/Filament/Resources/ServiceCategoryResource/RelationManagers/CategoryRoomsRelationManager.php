<?php

namespace Modules\Services\Filament\Resources\ServiceCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Models\Room;

class CategoryRoomsRelationManager extends RelationManager
{
    protected static string $relationship = 'rooms';

    protected static ?string $title = 'Service Rooms';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('room_id')
                    ->label(__('services::services.rooms.room'))
                    ->options(function () {
                        return Room::query()
                            ->where('is_bookable', true)
                            ->get()
                            ->mapWithKeys(fn ($room) => [
                                $room->id => $room->translated_name ?? $room->name
                            ]);
                    })
                    ->required()
                    ->searchable(),

                Forms\Components\Toggle::make('is_primary')
                    ->label(__('services::services.rooms.is_primary'))
                    ->helperText(__('services::services.rooms.is_primary_help'))
                    ->default(false),

                Forms\Components\TextInput::make('priority')
                    ->label(__('services::services.rooms.priority'))
                    ->helperText(__('services::services.rooms.priority_help'))
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('services::services.rooms.room'))
                    ->formatStateUsing(fn ($record) => $record->translated_name ?? $record->name)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('services::services.rooms.type'))
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('services::services.rooms.branch'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity')
                    ->label(__('services::services.rooms.capacity'))
                    ->sortable(),

                Tables\Columns\IconColumn::make('pivot.is_primary')
                    ->label(__('services::services.rooms.is_primary'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('pivot.priority')
                    ->label(__('services::services.rooms.priority'))
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_primary')
                    ->label(__('services::services.rooms.is_primary'))
                    ->queries(
                        true: fn ($query) => $query->wherePivot('is_primary', true),
                        false: fn ($query) => $query->wherePivot('is_primary', false),
                    ),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label(__('services::services.rooms.add_room'))
                    ->icon('heroicon-o-plus')
                    ->modalHeading(__('services::services.rooms.add_room'))
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        Forms\Components\Select::make('recordId')
                            ->label(__('services::services.rooms.room'))
                            ->options(function () {
                                $attachedIds = $this->ownerRecord->rooms()->pluck('rooms.id')->toArray();
                                return Room::query()
                                    ->where('is_bookable', true)
                                    ->whereNotIn('id', $attachedIds)
                                    ->get()
                                    ->mapWithKeys(fn ($room) => [
                                        $room->id => $room->translated_name ?? $room->name
                                    ]);
                            })
                            ->searchable()
                            ->required(),
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('services::services.rooms.is_primary'))
                            ->default(false),
                        Forms\Components\TextInput::make('priority')
                            ->label(__('services::services.rooms.priority'))
                            ->numeric()
                            ->default(0),
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('togglePrimary')
                    ->label(fn ($record) => $record->pivot->is_primary
                        ? __('services::services.rooms.unset_primary')
                        : __('services::services.rooms.set_primary'))
                    ->icon(fn ($record) => $record->pivot->is_primary
                        ? 'heroicon-o-star'
                        : 'heroicon-o-star')
                    ->color(fn ($record) => $record->pivot->is_primary ? 'warning' : 'gray')
                    ->action(function ($record) {
                        // If setting as primary, unset all others first
                        if (!$record->pivot->is_primary) {
                            $this->ownerRecord->rooms()->update(['is_primary' => false]);
                        }
                        $this->ownerRecord->rooms()->updateExistingPivot(
                            $record->id,
                            ['is_primary' => !$record->pivot->is_primary]
                        );
                    }),
                Tables\Actions\DetachAction::make()
                    ->label(__('services::services.actions.remove')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make()
                        ->label(__('services::services.actions.remove_selected')),
                ]),
            ])
            ->defaultSort('pivot_priority');
    }
}
