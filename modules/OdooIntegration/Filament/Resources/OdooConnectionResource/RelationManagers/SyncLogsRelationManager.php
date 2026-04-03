<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Enums\SyncStatus;

class SyncLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncLogs';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('id')
                    ->disabled(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('entityMapping.name')
                    ->label(__('odoo-integration::odoo.fields.entity'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('sync_type')
                    ->label(__('odoo-integration::odoo.fields.sync_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => OdooSyncLog::SYNC_TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('direction')
                    ->label(__('odoo-integration::odoo.fields.direction'))
                    ->badge()
                    ->color(fn ($state) => $state === 'import' ? 'info' : 'success'),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('odoo-integration::odoo.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SyncStatus ? $state->label() : $state)
                    ->color(fn ($state) => $state instanceof SyncStatus ? $state->color() : 'gray'),

                Tables\Columns\TextColumn::make('records_processed')
                    ->label(__('odoo-integration::odoo.fields.processed'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('records_created')
                    ->label(__('odoo-integration::odoo.fields.created'))
                    ->numeric()
                    ->color('success'),

                Tables\Columns\TextColumn::make('records_updated')
                    ->label(__('odoo-integration::odoo.fields.updated'))
                    ->numeric()
                    ->color('info'),

                Tables\Columns\TextColumn::make('records_failed')
                    ->label(__('odoo-integration::odoo.fields.failed'))
                    ->numeric()
                    ->color('danger'),

                Tables\Columns\TextColumn::make('conflicts_detected')
                    ->label(__('odoo-integration::odoo.fields.conflicts'))
                    ->numeric()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('started_at')
                    ->label(__('odoo-integration::odoo.fields.started_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('duration')
                    ->label(__('odoo-integration::odoo.fields.duration')),

                Tables\Columns\TextColumn::make('triggeredByUser.name')
                    ->label(__('odoo-integration::odoo.fields.triggered_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('odoo-integration::odoo.fields.status'))
                    ->options(SyncStatus::options()),

                Tables\Filters\SelectFilter::make('sync_type')
                    ->label(__('odoo-integration::odoo.fields.sync_type'))
                    ->options(OdooSyncLog::SYNC_TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('view_errors')
                    ->label(__('odoo-integration::odoo.actions.view_errors'))
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->visible(fn (OdooSyncLog $record) => !empty($record->errors))
                    ->modalContent(fn (OdooSyncLog $record) => view('odoo-integration::filament.components.error-list', [
                        'errors' => $record->errors,
                    ])),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }
}
