<?php

namespace Modules\OdooIntegration\Filament\Resources\OdooConnectionResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\OdooIntegration\Models\OdooSyncLog;
use Modules\OdooIntegration\Enums\SyncStatus;

class SyncLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'syncLogs';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = null;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('odoo-integration::odoo.labels.sync_logs');
    }

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
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->infolist([
                        Infolists\Components\Section::make(__('odoo-integration::odoo.sections.sync_summary'))
                            ->schema([
                                Infolists\Components\Grid::make(4)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('sync_type')
                                            ->label(__('odoo-integration::odoo.fields.sync_type'))
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('direction')
                                            ->label(__('odoo-integration::odoo.fields.direction'))
                                            ->badge(),
                                        Infolists\Components\TextEntry::make('status')
                                            ->label(__('odoo-integration::odoo.fields.status'))
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => $state instanceof SyncStatus ? $state->label() : $state)
                                            ->color(fn ($state) => $state instanceof SyncStatus ? $state->color() : 'gray'),
                                        Infolists\Components\TextEntry::make('duration')
                                            ->label(__('odoo-integration::odoo.fields.duration')),
                                    ]),
                            ]),

                        Infolists\Components\Section::make(__('odoo-integration::odoo.sections.statistics'))
                            ->schema([
                                Infolists\Components\Grid::make(5)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('records_processed')
                                            ->label(__('odoo-integration::odoo.fields.records_processed')),
                                        Infolists\Components\TextEntry::make('records_created')
                                            ->label(__('odoo-integration::odoo.fields.records_created'))
                                            ->color('success'),
                                        Infolists\Components\TextEntry::make('records_updated')
                                            ->label(__('odoo-integration::odoo.fields.records_updated'))
                                            ->color('info'),
                                        Infolists\Components\TextEntry::make('records_failed')
                                            ->label(__('odoo-integration::odoo.fields.records_failed'))
                                            ->color('danger'),
                                        Infolists\Components\TextEntry::make('conflicts_detected')
                                            ->label(__('odoo-integration::odoo.fields.conflicts'))
                                            ->color('warning'),
                                    ]),
                            ]),

                        Infolists\Components\Section::make(__('odoo-integration::odoo.sections.errors'))
                            ->schema([
                                Infolists\Components\TextEntry::make('errors_formatted')
                                    ->label('')
                                    ->getStateUsing(function ($record) {
                                        $errors = $record->errors;
                                        if (empty($errors) || !is_array($errors)) {
                                            return __('odoo-integration::odoo.messages.no_errors');
                                        }

                                        return collect($errors)->map(function ($error) {
                                            return "• " . ($error['message'] ?? 'Unknown error');
                                        })->implode("\n");
                                    })
                                    ->markdown(),
                            ])
                            ->collapsible(),

                        Infolists\Components\Section::make(__('odoo-integration::odoo.sections.timestamps'))
                            ->schema([
                                Infolists\Components\Grid::make(3)
                                    ->schema([
                                        Infolists\Components\TextEntry::make('started_at')
                                            ->label(__('odoo-integration::odoo.fields.started_at'))
                                            ->dateTime(),
                                        Infolists\Components\TextEntry::make('completed_at')
                                            ->label(__('odoo-integration::odoo.fields.completed_at'))
                                            ->dateTime()
                                            ->placeholder('-'),
                                        Infolists\Components\TextEntry::make('triggeredByUser.name')
                                            ->label(__('odoo-integration::odoo.fields.triggered_by'))
                                            ->placeholder('System'),
                                    ]),
                            ]),
                    ]),
            ])
            ->defaultSort('started_at', 'desc')
            ->poll('30s');
    }
}
