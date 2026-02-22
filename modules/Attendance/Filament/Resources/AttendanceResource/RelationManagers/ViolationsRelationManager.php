<?php

namespace Modules\Attendance\Filament\Resources\AttendanceResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Attendance\Models\AttendanceViolation;

class ViolationsRelationManager extends RelationManager
{
    protected static string $relationship = 'violations';

    protected static ?string $title = 'Violations';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('violation_type')
                    ->label(__('attendance::attendance.violation_type'))
                    ->options(AttendanceViolation::TYPES)
                    ->required()
                    ->disabled(),

                Forms\Components\DatePicker::make('violation_date')
                    ->label(__('attendance::attendance.violation_date'))
                    ->required()
                    ->disabled(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TimePicker::make('scheduled_time')
                            ->label(__('attendance::attendance.scheduled_time'))
                            ->disabled(),

                        Forms\Components\TimePicker::make('actual_time')
                            ->label(__('attendance::attendance.actual_time'))
                            ->disabled(),
                    ]),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('violation_minutes')
                            ->label(__('attendance::attendance.violation_minutes'))
                            ->suffix('min')
                            ->disabled(),

                        Forms\Components\TextInput::make('penalty_amount')
                            ->label(__('attendance::attendance.penalty_amount'))
                            ->prefix('EGP')
                            ->disabled(),
                    ]),

                Forms\Components\Select::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->options(AttendanceViolation::STATUSES)
                    ->disabled(),

                Forms\Components\Textarea::make('employee_notes')
                    ->label(__('attendance::attendance.employee_notes'))
                    ->rows(2),

                Forms\Components\Textarea::make('manager_notes')
                    ->label(__('attendance::attendance.manager_notes'))
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('violation_type')
            ->columns([
                Tables\Columns\TextColumn::make('violation_type')
                    ->label(__('attendance::attendance.violation_type'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceViolation::TYPES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceViolation::TYPE_COLORS[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('violation_date')
                    ->label(__('attendance::attendance.violation_date'))
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_violation_duration')
                    ->label(__('attendance::attendance.violation_minutes')),

                Tables\Columns\TextColumn::make('formatted_penalty')
                    ->label(__('attendance::attendance.penalty_amount')),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('attendance::attendance.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => AttendanceViolation::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AttendanceViolation::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AttendanceViolation::STATUSES),
            ])
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label(__('attendance::attendance.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (AttendanceViolation $record) => $record->canBeApproved())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('manager_notes')
                            ->label(__('attendance::attendance.manager_notes'))
                            ->rows(2),
                    ])
                    ->action(function (AttendanceViolation $record, array $data) {
                        if ($record->approve(auth()->user(), $data['manager_notes'] ?? null)) {
                            Notification::make()
                                ->title(__('attendance::attendance.violation_approved'))
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('waive')
                    ->label(__('attendance::attendance.waive'))
                    ->icon('heroicon-o-x-mark')
                    ->color('warning')
                    ->visible(fn (AttendanceViolation $record) => $record->canBeWaived())
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('waived_reason')
                            ->label(__('attendance::attendance.waived_reason'))
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (AttendanceViolation $record, array $data) {
                        if ($record->waive(auth()->user(), $data['waived_reason'])) {
                            Notification::make()
                                ->title(__('attendance::attendance.violation_waived'))
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('violation_date', 'desc');
    }
}
