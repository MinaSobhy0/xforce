<?php

namespace Modules\Projects\Filament\Resources\ProjectResource\RelationManagers;

use Modules\Projects\Models\ProjectStage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name.en')
                    ->label(__('projects::projects.fields.name_en'))
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('name.ar')
                    ->label(__('projects::projects.fields.name_ar'))
                    ->maxLength(255),

                Forms\Components\Select::make('status_type')
                    ->label(__('projects::projects.fields.status_type'))
                    ->options(ProjectStage::STATUS_TYPES)
                    ->required(),

                Forms\Components\ColorPicker::make('color')
                    ->label(__('projects::projects.fields.color'))
                    ->required()
                    ->default('#6B7280'),

                Forms\Components\Toggle::make('fold_by_default')
                    ->label(__('projects::projects.fields.fold_by_default')),

                Forms\Components\Toggle::make('is_final')
                    ->label(__('projects::projects.fields.is_final'))
                    ->helperText(__('projects::projects.helpers.is_final')),

                Forms\Components\TextInput::make('sort_order')
                    ->label(__('projects::projects.fields.sort_order'))
                    ->numeric()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label(''),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('projects::projects.fields.name'))
                    ->formatStateUsing(fn (ProjectStage $record) => $record->display_name),

                Tables\Columns\TextColumn::make('status_type')
                    ->label(__('projects::projects.fields.status_type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'todo' => 'gray',
                        'in_progress' => 'warning',
                        'review' => 'info',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ProjectStage::STATUS_TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('tasks_count')
                    ->label(__('projects::projects.fields.tasks'))
                    ->counts('tasks'),

                Tables\Columns\IconColumn::make('is_final')
                    ->label(__('projects::projects.fields.is_final'))
                    ->boolean(),
            ])
            ->filters([
                //
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
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
