<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;

class PractitionersRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assigned Staff';

    protected static ?string $recordTitleAttribute = 'user_id';

    protected static bool $isLazy = false;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label(__('booking::schedules.fields.practitioner'))
                    ->relationship('practitioner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (User $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'email'])
                    ->preload()
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('booking::schedules.fields.is_primary'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::schedules.fields.is_active'))
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('user_id')
            ->columns([
                Tables\Columns\TextColumn::make('practitioner.full_name')
                    ->label(__('booking::schedules.fields.practitioner'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label(__('booking::schedules.fields.primary'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('booking::schedules.fields.active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('booking::schedules.fields.is_active')),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('is_primary', 'desc');
    }
}
