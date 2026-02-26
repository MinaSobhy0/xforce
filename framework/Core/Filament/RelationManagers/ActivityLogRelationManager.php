<?php

namespace XLinic\Framework\Core\Filament\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Auth\Models\User;

/**
 * Generic Activity Log RelationManager for all resources.
 *
 * This relation manager can be added to any resource that has a model
 * using the HasActivity trait to display a complete activity history.
 */
class ActivityLogRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('core::activity.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                Tables\Columns\TextColumn::make('event')
                    ->label(__('core::activity.columns.event'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __("core::activity.events.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label(__('core::activity.columns.description'))
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->description)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('causer.email')
                    ->label(__('core::activity.columns.changed_by'))
                    ->formatStateUsing(function ($record) {
                        if (!$record->causer) {
                            return __('core::activity.system');
                        }
                        $name = trim(($record->causer->first_name ?? '') . ' ' . ($record->causer->last_name ?? ''));
                        return $name ?: $record->causer->email;
                    })
                    ->icon('heroicon-o-user')
                    ->searchable(['first_name', 'last_name', 'email']),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('core::activity.columns.when'))
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at->format('Y-m-d H:i:s')),

                Tables\Columns\ViewColumn::make('properties')
                    ->label(__('core::activity.columns.changes'))
                    ->view('filament.tables.columns.activity-properties')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event')
                    ->label(__('core::activity.filters.event_type'))
                    ->options([
                        'created' => __('core::activity.events.created'),
                        'updated' => __('core::activity.events.updated'),
                        'deleted' => __('core::activity.events.deleted'),
                        'restored' => __('core::activity.events.restored'),
                    ]),

                Tables\Filters\SelectFilter::make('causer_id')
                    ->label(__('core::activity.filters.changed_by'))
                    ->options(function () {
                        return User::whereHas('activities')
                            ->get()
                            ->mapWithKeys(fn ($user) => [
                                $user->id => trim($user->first_name . ' ' . $user->last_name) ?: $user->email
                            ]);
                    })
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label(__('core::activity.filters.from_date')),
                        Forms\Components\DatePicker::make('to_date')
                            ->label(__('core::activity.filters.to_date')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['to_date'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators['from_date'] = __('core::activity.filters.from') . ': ' . $data['from_date'];
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators['to_date'] = __('core::activity.filters.to') . ': ' . $data['to_date'];
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(__('core::activity.view.title'))
                    ->form([
                        Forms\Components\Section::make(__('core::activity.view.event_info'))
                            ->schema([
                                Forms\Components\TextInput::make('event')
                                    ->label(__('core::activity.columns.event'))
                                    ->formatStateUsing(fn ($state) => __("core::activity.events.{$state}"))
                                    ->disabled(),

                                Forms\Components\TextInput::make('description')
                                    ->label(__('core::activity.columns.description'))
                                    ->disabled()
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make('causer_name')
                                    ->label(__('core::activity.columns.changed_by'))
                                    ->formatStateUsing(fn ($record) => $record->causer
                                        ? trim($record->causer->first_name . ' ' . $record->causer->last_name) ?: $record->causer->email
                                        : __('core::activity.system'))
                                    ->disabled(),

                                Forms\Components\TextInput::make('created_at')
                                    ->label(__('core::activity.columns.when'))
                                    ->formatStateUsing(fn ($state) => $state?->format('Y-m-d H:i:s'))
                                    ->disabled(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(__('core::activity.view.changes'))
                            ->schema([
                                Forms\Components\ViewField::make('properties')
                                    ->view('filament.tables.columns.activity-properties-detail')
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(fn ($record) => empty($record->properties['old']) && empty($record->properties['attributes'])),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading(__('core::activity.empty.heading'))
            ->emptyStateDescription(__('core::activity.empty.description'))
            ->emptyStateIcon('heroicon-o-clock');
    }
}
