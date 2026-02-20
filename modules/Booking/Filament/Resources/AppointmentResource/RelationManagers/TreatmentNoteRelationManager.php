<?php

namespace Modules\Booking\Filament\Resources\AppointmentResource\RelationManagers;

use Modules\Booking\Models\AppointmentTreatmentNote;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TreatmentNoteRelationManager extends RelationManager
{
    protected static string $relationship = 'treatmentNote';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('booking::treatment_notes.sections.treatment'))
                    ->schema([
                        Forms\Components\CheckboxList::make('areas_treated')
                            ->label(__('booking::treatment_notes.fields.areas_treated'))
                            ->options(AppointmentTreatmentNote::COMMON_AREAS)
                            ->columns(4),
                    ]),

                Forms\Components\Section::make(__('booking::treatment_notes.sections.machine_settings'))
                    ->schema([
                        Forms\Components\Grid::make(4)
                            ->schema([
                                Forms\Components\TextInput::make('machine_settings.energy')
                                    ->label(__('booking::treatment_notes.fields.energy'))
                                    ->placeholder('e.g., 12 J/cm²'),

                                Forms\Components\TextInput::make('machine_settings.spot_size')
                                    ->label(__('booking::treatment_notes.fields.spot_size'))
                                    ->placeholder('e.g., 18mm'),

                                Forms\Components\TextInput::make('machine_settings.pulse_duration')
                                    ->label(__('booking::treatment_notes.fields.pulse_duration'))
                                    ->placeholder('e.g., 20ms'),

                                Forms\Components\TextInput::make('machine_settings.frequency')
                                    ->label(__('booking::treatment_notes.fields.frequency'))
                                    ->placeholder('e.g., 2 Hz'),
                            ]),

                        Forms\Components\TextInput::make('shots_fired')
                            ->label(__('booking::treatment_notes.fields.shots_fired'))
                            ->numeric()
                            ->minValue(0),
                    ]),

                Forms\Components\Section::make(__('booking::treatment_notes.sections.patient_response'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('skin_reaction')
                                    ->label(__('booking::treatment_notes.fields.skin_reaction'))
                                    ->options(AppointmentTreatmentNote::SKIN_REACTIONS),

                                Forms\Components\Select::make('patient_comfort')
                                    ->label(__('booking::treatment_notes.fields.patient_comfort'))
                                    ->options(AppointmentTreatmentNote::PATIENT_COMFORT),
                            ]),
                    ]),

                Forms\Components\Section::make(__('booking::treatment_notes.sections.post_care'))
                    ->schema([
                        Forms\Components\CheckboxList::make('post_care_given')
                            ->label(__('booking::treatment_notes.fields.post_care_given'))
                            ->options(AppointmentTreatmentNote::POST_CARE_OPTIONS)
                            ->columns(2),
                    ]),

                Forms\Components\Section::make(__('booking::treatment_notes.sections.follow_up'))
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('follow_up_recommended')
                                    ->label(__('booking::treatment_notes.fields.follow_up_recommended'))
                                    ->live(),

                                Forms\Components\TextInput::make('follow_up_days')
                                    ->label(__('booking::treatment_notes.fields.follow_up_days'))
                                    ->numeric()
                                    ->suffix(__('booking::appointments.days'))
                                    ->visible(fn (Forms\Get $get) => $get('follow_up_recommended')),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('booking::treatment_notes.fields.notes'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('formatted_areas')
                    ->label(__('booking::treatment_notes.fields.areas_treated'))
                    ->wrap(),

                Tables\Columns\TextColumn::make('shots_fired')
                    ->label(__('booking::treatment_notes.fields.shots_fired'))
                    ->numeric(),

                Tables\Columns\TextColumn::make('skin_reaction_label')
                    ->label(__('booking::treatment_notes.fields.skin_reaction')),

                Tables\Columns\TextColumn::make('patient_comfort_label')
                    ->label(__('booking::treatment_notes.fields.patient_comfort')),

                Tables\Columns\IconColumn::make('follow_up_recommended')
                    ->label(__('booking::treatment_notes.fields.follow_up_recommended'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('createdBy.full_name')
                    ->label(__('booking::treatment_notes.fields.created_by')),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('booking::treatment_notes.fields.created_at'))
                    ->dateTime(),
            ])
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by_user_id'] = auth()->id();
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
