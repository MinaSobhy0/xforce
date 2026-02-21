<?php

namespace Modules\Booking\Filament\Resources\WorkScheduleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Models\User;
use Modules\Booking\Models\PractitionerScheduleAssignment;

class PractitionersRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Assigned Practitioners';

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

                Forms\Components\Select::make('branch_id')
                    ->label(__('booking::schedules.fields.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder(__('booking::schedules.use_schedule_branch'))
                    ->helperText(__('booking::schedules.override_branch_help')),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('effective_from')
                            ->label(__('booking::schedules.fields.effective_from'))
                            ->placeholder(__('booking::schedules.immediately')),

                        Forms\Components\DatePicker::make('effective_until')
                            ->label(__('booking::schedules.fields.effective_until'))
                            ->placeholder(__('booking::schedules.indefinitely')),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('booking::schedules.fields.is_primary'))
                            ->helperText(__('booking::schedules.primary_help')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('booking::schedules.fields.is_active'))
                            ->default(true),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('booking::schedules.fields.notes'))
                    ->rows(2)
                    ->maxLength(500),
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

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('booking::schedules.fields.branch'))
                    ->placeholder(__('booking::schedules.use_schedule_branch')),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label(__('booking::schedules.fields.from'))
                    ->date()
                    ->placeholder(__('booking::schedules.immediately')),

                Tables\Columns\TextColumn::make('effective_until')
                    ->label(__('booking::schedules.fields.until'))
                    ->date()
                    ->placeholder(__('booking::schedules.indefinitely')),

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

                Tables\Filters\Filter::make('currently_effective')
                    ->label(__('booking::schedules.filters.currently_effective'))
                    ->query(fn ($query) => $query->currentlyEffective())
                    ->toggle()
                    ->default(true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['tenant_id'] = tenant_id();
                        return $data;
                    }),
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
            ->defaultSort('created_at', 'desc');
    }
}
