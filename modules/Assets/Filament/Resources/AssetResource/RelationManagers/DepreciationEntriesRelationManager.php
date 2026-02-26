<?php

namespace Modules\Assets\Filament\Resources\AssetResource\RelationManagers;

use Modules\Assets\Models\AssetDepreciationEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DepreciationEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'depreciationEntries';

    protected static ?string $recordTitleAttribute = 'period_label';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('assets::assets.depreciation_entry.plural');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('period_label')
                    ->required()
                    ->maxLength(20),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('period_label')
            ->columns([
                Tables\Columns\TextColumn::make('period_label')
                    ->label(__('assets::assets.depreciation_entry.fields.period'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_start')
                    ->label(__('assets::assets.depreciation_entry.fields.period_start'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('period_end')
                    ->label(__('assets::assets.depreciation_entry.fields.period_end'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('depreciation_amount_minor')
                    ->label(__('assets::assets.depreciation_entry.fields.amount'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('accumulated_depreciation_minor')
                    ->label(__('assets::assets.depreciation_entry.fields.accumulated'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('book_value_minor')
                    ->label(__('assets::assets.depreciation_entry.fields.book_value'))
                    ->formatStateUsing(fn ($state) => format_money($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('assets::assets.depreciation_entry.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => AssetDepreciationEntry::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => AssetDepreciationEntry::STATUS_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('journalEntry.code')
                    ->label(__('assets::assets.depreciation_entry.fields.journal_entry'))
                    ->url(fn ($record) => $record->journal_entry_id
                        ? route('filament.tenant.resources.journal-entries.view', ['record' => $record->journal_entry_id])
                        : null)
                    ->default('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('assets::assets.depreciation_entry.fields.status'))
                    ->options(AssetDepreciationEntry::STATUSES),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('period_label', 'desc');
    }
}
