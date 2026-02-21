<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Booking\Models\WorkSchedule;

class ScheduleAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleAssignments';

    protected static ?string $recordTitleAttribute = 'work_schedule_id';

    protected static bool $isLazy = false;

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('staff::staff.relation_managers.schedule_assignments');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('work_schedule_id')
                    ->label(__('staff::staff.fields.work_schedule'))
                    ->relationship('workSchedule', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                        if ($state) {
                            $schedule = WorkSchedule::find($state);
                            if ($schedule && $schedule->branch_id) {
                                $set('branch_id', null); // Use schedule's branch
                            }
                        }
                    }),

                Forms\Components\Select::make('branch_id')
                    ->label(__('staff::staff.fields.branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder(__('booking::schedules.use_schedule_branch'))
                    ->helperText(__('booking::schedules.override_branch_help')),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('effective_from')
                            ->label(__('staff::staff.fields.effective_from'))
                            ->placeholder(__('booking::schedules.immediately')),

                        Forms\Components\DatePicker::make('effective_until')
                            ->label(__('staff::staff.fields.effective_until'))
                            ->placeholder(__('booking::schedules.indefinitely')),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('staff::staff.fields.is_primary'))
                            ->helperText(__('staff::staff.fields.is_primary_help')),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('staff::staff.fields.is_active'))
                            ->default(true),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label(__('staff::staff.fields.notes'))
                    ->rows(2)
                    ->maxLength(500),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('work_schedule_id')
            ->columns([
                Tables\Columns\ColorColumn::make('workSchedule.color')
                    ->label('')
                    ->width(10),

                Tables\Columns\TextColumn::make('workSchedule.name')
                    ->label(__('staff::staff.fields.work_schedule'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('workSchedule.schedule_summary')
                    ->label(__('staff::staff.fields.schedule'))
                    ->wrap()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('branch.name')
                    ->label(__('staff::staff.fields.branch'))
                    ->placeholder(__('booking::schedules.use_schedule_branch')),

                Tables\Columns\TextColumn::make('effective_from')
                    ->label(__('staff::staff.fields.effective_from'))
                    ->date()
                    ->placeholder(__('booking::schedules.immediately')),

                Tables\Columns\TextColumn::make('effective_until')
                    ->label(__('staff::staff.fields.effective_until'))
                    ->date()
                    ->placeholder(__('booking::schedules.indefinitely')),

                Tables\Columns\IconColumn::make('is_primary')
                    ->label(__('staff::staff.fields.is_primary'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('staff::staff.fields.is_active'))
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('staff::staff.fields.is_active')),

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
                        $data['user_id'] = $this->getOwnerRecord()->user_id;
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
