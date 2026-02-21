<?php

namespace Modules\Services\Filament\Resources\ServiceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PackageItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'packageItems';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('package.name')
                    ->label(__('packages::packages.package'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '') : $state)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sessions_included')
                    ->label(__('packages::packages.fields.sessions_included'))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('unit_price_minor')
                    ->label(__('packages::packages.fields.unit_price'))
                    ->money('EGP', divideBy: 100)
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('package.is_active')
                    ->label(__('packages::packages.fields.active'))
                    ->boolean(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn ($record) => route('filament.tenant.resources.packages.edit', $record->package_id)),
            ])
            ->bulkActions([]);
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('packages::packages.packages');
    }
}
