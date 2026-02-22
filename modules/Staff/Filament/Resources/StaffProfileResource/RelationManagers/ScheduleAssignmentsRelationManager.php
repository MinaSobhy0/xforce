<?php

namespace Modules\Staff\Filament\Resources\StaffProfileResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

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
                    ->required(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\Toggle::make('is_primary')
                            ->label(__('staff::staff.fields.is_primary'))
                            ->default(true),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('staff::staff.fields.is_active'))
                            ->default(true),
                    ]),
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
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = $this->getOwnerRecord()->user_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->infolist([
                        Infolists\Components\Section::make(__('staff::staff.sections.schedule_details'))
                            ->schema([
                                Infolists\Components\ColorEntry::make('workSchedule.color')
                                    ->label(__('booking::schedules.fields.color')),
                                Infolists\Components\TextEntry::make('workSchedule.name')
                                    ->label(__('staff::staff.fields.work_schedule'))
                                    ->weight('bold'),
                                Infolists\Components\IconEntry::make('is_primary')
                                    ->label(__('staff::staff.fields.is_primary'))
                                    ->boolean(),
                                Infolists\Components\IconEntry::make('is_active')
                                    ->label(__('staff::staff.fields.is_active'))
                                    ->boolean(),
                            ])->columns(4),

                        Infolists\Components\Section::make(__('staff::staff.sections.schedule_summary'))
                            ->schema([
                                Infolists\Components\TextEntry::make('workSchedule.schedule_summary')
                                    ->label(__('staff::staff.fields.schedule'))
                                    ->columnSpanFull(),
                            ]),
                    ]),

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
