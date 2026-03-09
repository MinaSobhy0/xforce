<?php

namespace Modules\Packages\Filament\Resources\PackageSubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class UsagesRelationManager extends RelationManager
{
    protected static string $relationship = 'usages';

    protected static ?string $title = 'Session Usage History';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('packages::packages.subscriptions.relations.usages');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('service.translated_name')
                    ->label(__('packages::packages.fields.service'))
                    ->searchable(),

                Tables\Columns\TextColumn::make('quantity_used')
                    ->label(__('packages::packages.subscriptions.fields.quantity_used'))
                    ->suffix(fn ($record) => ' ' . ($record->unit_type === 'pulse' ? __('packages::packages.labels.pulses') : __('packages::packages.labels.sessions'))),

                Tables\Columns\TextColumn::make('unit_type')
                    ->label(__('packages::packages.subscriptions.fields.unit_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'pulse' ? __('packages::packages.labels.pulses') : __('packages::packages.labels.sessions')),

                Tables\Columns\TextColumn::make('appointment.date')
                    ->label(__('packages::packages.subscriptions.fields.appointment_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('used_at')
                    ->label(__('packages::packages.subscriptions.fields.used_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('packages::packages.fields.notes'))
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('used_at', 'desc');
    }
}
