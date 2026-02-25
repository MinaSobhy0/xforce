<?php

namespace Modules\Prescriptions\Filament\Resources\PrescriptionResource\RelationManagers;

use Modules\Prescriptions\Models\PrescriptionItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $recordTitleAttribute = 'medication_name';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('prescriptions::prescription.sections.medications');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('medication_name')
                    ->label(__('prescriptions::prescription.fields.medication_name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                Forms\Components\TextInput::make('generic_name')
                    ->label(__('prescriptions::prescription.fields.generic_name'))
                    ->maxLength(255)
                    ->columnSpan(2),

                Forms\Components\Select::make('form')
                    ->label(__('prescriptions::prescription.fields.form'))
                    ->options(PrescriptionItem::FORMS)
                    ->searchable(),

                Forms\Components\TextInput::make('dosage')
                    ->label(__('prescriptions::prescription.fields.dosage'))
                    ->maxLength(50),

                Forms\Components\Select::make('dosage_unit')
                    ->label(__('prescriptions::prescription.fields.dosage_unit'))
                    ->options(PrescriptionItem::DOSAGE_UNITS),

                Forms\Components\Select::make('frequency')
                    ->label(__('prescriptions::prescription.fields.frequency'))
                    ->options(PrescriptionItem::FREQUENCIES)
                    ->searchable()
                    ->required(),

                Forms\Components\TextInput::make('duration')
                    ->label(__('prescriptions::prescription.fields.duration'))
                    ->numeric()
                    ->minValue(1),

                Forms\Components\Select::make('duration_unit')
                    ->label(__('prescriptions::prescription.fields.duration_unit'))
                    ->options(PrescriptionItem::DURATION_UNITS)
                    ->default('days'),

                Forms\Components\Select::make('route')
                    ->label(__('prescriptions::prescription.fields.route'))
                    ->options(PrescriptionItem::ROUTES)
                    ->searchable()
                    ->default('oral'),

                Forms\Components\TextInput::make('quantity')
                    ->label(__('prescriptions::prescription.fields.quantity'))
                    ->numeric()
                    ->minValue(1),

                Forms\Components\Select::make('instructions')
                    ->label(__('prescriptions::prescription.fields.instructions'))
                    ->options(PrescriptionItem::INSTRUCTIONS)
                    ->searchable(),

                Forms\Components\TextInput::make('refills_allowed')
                    ->label(__('prescriptions::prescription.fields.refills'))
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Forms\Components\Textarea::make('special_instructions')
                    ->label(__('prescriptions::prescription.fields.special_instructions'))
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->columns(4);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('medication_name')
                    ->label(__('prescriptions::prescription.fields.medication_name'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('generic_name')
                    ->label(__('prescriptions::prescription.fields.generic_name'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('form_label')
                    ->label(__('prescriptions::prescription.fields.form')),

                Tables\Columns\TextColumn::make('full_dosage')
                    ->label(__('prescriptions::prescription.fields.dosage')),

                Tables\Columns\TextColumn::make('frequency_label')
                    ->label(__('prescriptions::prescription.fields.frequency')),

                Tables\Columns\TextColumn::make('full_duration')
                    ->label(__('prescriptions::prescription.fields.duration')),

                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('prescriptions::prescription.fields.quantity')),

                Tables\Columns\TextColumn::make('route_label')
                    ->label(__('prescriptions::prescription.fields.route'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('instructions_label')
                    ->label(__('prescriptions::prescription.fields.instructions'))
                    ->toggleable(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->ownerRecord->isEditable()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => $this->ownerRecord->isEditable()),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }
}
